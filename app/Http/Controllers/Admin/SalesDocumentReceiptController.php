<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CancelSalesDocumentReceiptRequest;
use App\Http\Requests\Admin\SendSalesDocumentReceiptEmailRequest;
use App\Http\Requests\Admin\StoreSalesDocumentReceiptRequest;
use App\Mail\Admin\SalesDocumentReceiptSentMail;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\SalesDocument;
use App\Models\SalesDocumentReceipt;
use App\Services\Admin\CompanyMailSettingsService;
use App\Services\Admin\SalesDocumentPaymentStatusService;
use App\Services\Admin\SalesDocumentReceiptPdfService;
use App\Services\Admin\SalesDocumentReceiptService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class SalesDocumentReceiptController extends Controller
{
    public function __construct(
        private readonly SalesDocumentReceiptService $salesDocumentReceiptService,
        private readonly SalesDocumentReceiptPdfService $salesDocumentReceiptPdfService,
        private readonly SalesDocumentPaymentStatusService $paymentStatusService,
        private readonly CompanyMailSettingsService $companyMailSettingsService
    ) {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', SalesDocumentReceipt::class);

        $companyId = (int) $request->user()->company_id;
        $search = trim((string) $request->query('q', ''));
        $status = trim((string) $request->query('status', ''));
        $customerId = (int) $request->query('customer_id', 0);
        $dateFrom = trim((string) $request->query('date_from', ''));
        $dateTo = trim((string) $request->query('date_to', ''));

        $receipts = SalesDocumentReceipt::query()
            ->forCompany($companyId)
            ->with([
                'customer:id,name',
                'salesDocument:id,number',
                'paymentMethod:id,name',
            ])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($searchQuery) use ($search): void {
                    $searchQuery
                        ->where('number', 'like', '%'.$search.'%')
                        ->orWhereHas('salesDocument', function ($documentQuery) use ($search): void {
                            $documentQuery->where('number', 'like', '%'.$search.'%');
                        })
                        ->orWhereHas('customer', function ($customerQuery) use ($search): void {
                            $customerQuery->where('name', 'like', '%'.$search.'%');
                        });
                });
            })
            ->when($status !== '' && in_array($status, SalesDocumentReceipt::statuses(), true), function ($query) use ($status): void {
                $query->where('status', $status);
            })
            ->when($customerId > 0, function ($query) use ($customerId): void {
                $query->where('customer_id', $customerId);
            })
            ->when($dateFrom !== '', function ($query) use ($dateFrom): void {
                $query->whereDate('receipt_date', '>=', $dateFrom);
            })
            ->when($dateTo !== '', function ($query) use ($dateTo): void {
                $query->whereDate('receipt_date', '<=', $dateTo);
            })
            ->orderByDesc('receipt_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.sales-document-receipts.index', [
            'receipts' => $receipts,
            'statusLabels' => SalesDocumentReceipt::statusLabels(),
            'customers' => Customer::query()
                ->forCompany($companyId)
                ->orderBy('name')
                ->get(['id', 'name']),
            'filters' => [
                'q' => $search,
                'status' => $status,
                'customer_id' => $customerId > 0 ? $customerId : '',
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
        ]);
    }

    public function create(Request $request, int $salesDocument): View
    {
        $companyId = (int) $request->user()->company_id;
        $document = $this->findCompanySalesDocumentOrFail($companyId, $salesDocument);
        $this->authorize('create', SalesDocumentReceipt::class);
        $this->authorize('view', $document);

        if (! $document->canReceivePayments()) {
            abort(404);
        }

        $openAmount = $this->paymentStatusService->openAmount($document);
        if ($openAmount <= 0) {
            abort(404);
        }

        return view('admin.sales-document-receipts.create', [
            'document' => $document->load([
                'customer:id,name,nif,email,phone,mobile',
            ]),
            'openAmount' => $openAmount,
            'paymentMethods' => PaymentMethod::query()
                ->visibleToCompany($companyId)
                ->orderByDesc('is_system')
                ->orderBy('name')
                ->get(['id', 'name', 'is_system']),
        ]);
    }

    public function store(StoreSalesDocumentReceiptRequest $request, int $salesDocument): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;
        $document = $this->findCompanySalesDocumentOrFail($companyId, $salesDocument);
        $this->authorize('create', SalesDocumentReceipt::class);
        $this->authorize('view', $document);

        $receipt = $this->salesDocumentReceiptService->issueReceipt(
            companyId: $companyId,
            salesDocumentId: (int) $document->id,
            createdBy: (int) $request->user()->id,
            payload: $request->validated()
        );

        return redirect()
            ->route('admin.sales-document-receipts.show', $receipt->id)
            ->with('status', 'Recibo emitido com sucesso.');
    }

    public function show(Request $request, int $salesDocumentReceipt): View
    {
        $companyId = (int) $request->user()->company_id;
        $receipt = $this->findCompanyReceiptOrFail($companyId, $salesDocumentReceipt);
        $this->authorize('view', $receipt);

        return view('admin.sales-document-receipts.show', [
            'receipt' => $receipt->load([
                'customer:id,name,nif,email,phone,mobile,address,postal_code,locality,city',
                'salesDocument:id,number,status,payment_status,issue_date,due_date,currency,grand_total',
                'paymentMethod:id,name',
                'creator:id,name',
                'canceller:id,name',
            ]),
            'statusLabels' => SalesDocumentReceipt::statusLabels(),
            'paymentStatusLabels' => SalesDocument::paymentStatusLabels(),
        ]);
    }

    public function cancel(CancelSalesDocumentReceiptRequest $request, int $salesDocumentReceipt): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;
        $receipt = $this->findCompanyReceiptOrFail($companyId, $salesDocumentReceipt);
        $this->authorize('cancel', $receipt);

        $receipt = $this->salesDocumentReceiptService->cancelReceipt(
            companyId: $companyId,
            receiptId: (int) $receipt->id,
            cancelledBy: (int) $request->user()->id
        );

        return redirect()
            ->route('admin.sales-document-receipts.show', $receipt->id)
            ->with('status', 'Recibo cancelado com sucesso.');
    }

    public function generatePdf(Request $request, int $salesDocumentReceipt): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;
        $receipt = $this->findCompanyReceiptOrFail($companyId, $salesDocumentReceipt);
        $this->authorize('pdf', $receipt);

        $this->salesDocumentReceiptPdfService->generateAndStore($receipt);

        return redirect()
            ->route('admin.sales-document-receipts.show', $receipt->id)
            ->with('status', 'PDF do recibo gerado com sucesso.');
    }

    public function downloadPdf(Request $request, int $salesDocumentReceipt): StreamedResponse
    {
        $companyId = (int) $request->user()->company_id;
        $receipt = $this->findCompanyReceiptOrFail($companyId, $salesDocumentReceipt);
        $this->authorize('pdf', $receipt);

        if (! $receipt->pdf_path || ! Storage::disk('local')->exists($receipt->pdf_path)) {
            abort(404);
        }

        return Storage::disk('local')->download(
            $receipt->pdf_path,
            Str::slug($receipt->number).'.pdf'
        );
    }

    public function sendEmail(SendSalesDocumentReceiptEmailRequest $request, int $salesDocumentReceipt): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;
        $receipt = $this->findCompanyReceiptOrFail($companyId, $salesDocumentReceipt);
        $this->authorize('send', $receipt);

        if (! $receipt->pdf_path || ! Storage::disk('local')->exists($receipt->pdf_path)) {
            $this->salesDocumentReceiptPdfService->generateAndStore($receipt);
            $receipt->refresh();
        }

        $receipt->loadMissing('company');
        $this->companyMailSettingsService->applyRuntimeConfig($receipt->company);

        $to = $request->validated('to');
        $ccRecipients = $request->ccRecipients();
        $subject = $request->validated('subject');
        $message = $request->validated('message');

        $mailer = Mail::to($to);
        if ($ccRecipients !== []) {
            $mailer->cc($ccRecipients);
        }

        try {
            $mailer->send(new SalesDocumentReceiptSentMail($receipt, $subject, $message));
        } catch (Throwable $exception) {
            Log::warning('Sales document receipt email send failed', [
                'context' => 'sales_document_receipts',
                'sales_document_receipt_id' => (int) $receipt->id,
                'company_id' => (int) $receipt->company_id,
                'error' => $exception->getMessage(),
            ]);

            return redirect()
                ->route('admin.sales-document-receipts.show', $receipt->id)
                ->withErrors([
                    'sales_document_receipt_email' => $this->friendlyEmailError($exception),
                ]);
        }

        return redirect()
            ->route('admin.sales-document-receipts.show', $receipt->id)
            ->with('status', 'Recibo enviado por email com sucesso.');
    }

    private function findCompanySalesDocumentOrFail(int $companyId, int $documentId): SalesDocument
    {
        return SalesDocument::query()
            ->forCompany($companyId)
            ->whereKey($documentId)
            ->firstOrFail();
    }

    private function findCompanyReceiptOrFail(int $companyId, int $receiptId): SalesDocumentReceipt
    {
        return SalesDocumentReceipt::query()
            ->forCompany($companyId)
            ->whereKey($receiptId)
            ->firstOrFail();
    }

    private function friendlyEmailError(Throwable $exception): string
    {
        $message = mb_strtolower($exception->getMessage());

        if ($exception instanceof TransportExceptionInterface) {
            if (str_contains($message, 'auth') || str_contains($message, '535') || str_contains($message, 'username') || str_contains($message, 'password')) {
                return 'Falha de autenticacao SMTP. Verifique username e password.';
            }

            if (str_contains($message, 'connection') || str_contains($message, 'timed out') || str_contains($message, 'refused') || str_contains($message, 'getaddrinfo') || str_contains($message, 'network')) {
                return 'Falha de ligacao SMTP. Verifique host, porta e encriptacao.';
            }
        }

        return 'Falha no envio do Recibo por email. Verifique a configuracao SMTP e tente novamente.';
    }
}
