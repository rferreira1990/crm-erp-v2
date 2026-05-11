<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CancelSupplierPaymentRequest;
use App\Http\Requests\Admin\SendSupplierPaymentEmailRequest;
use App\Http\Requests\Admin\StoreSupplierPaymentRequest;
use App\Mail\Admin\SupplierPaymentSentMail;
use App\Models\PaymentMethod;
use App\Models\PurchaseDocument;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Services\Admin\CompanyMailSettingsService;
use App\Services\Admin\PurchaseDocumentPaymentStatusService;
use App\Services\Admin\SupplierPaymentPdfService;
use App\Services\Admin\SupplierPaymentService;
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

class SupplierPaymentController extends Controller
{
    public function __construct(
        private readonly SupplierPaymentService $supplierPaymentService,
        private readonly PurchaseDocumentPaymentStatusService $paymentStatusService,
        private readonly SupplierPaymentPdfService $supplierPaymentPdfService,
        private readonly CompanyMailSettingsService $companyMailSettingsService
    ) {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', SupplierPayment::class);

        $companyId = (int) $request->user()->company_id;
        $search = trim((string) $request->query('q', ''));
        $status = trim((string) $request->query('status', ''));
        $supplierId = (int) $request->query('supplier_id', 0);
        $dateFrom = trim((string) $request->query('date_from', ''));
        $dateTo = trim((string) $request->query('date_to', ''));

        $payments = SupplierPayment::query()
            ->forCompany($companyId)
            ->with([
                'supplier:id,name',
                'purchaseDocument:id,number',
                'paymentMethod:id,name',
            ])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($searchQuery) use ($search): void {
                    $searchQuery
                        ->where('number', 'like', '%'.$search.'%')
                        ->orWhereHas('purchaseDocument', function ($documentQuery) use ($search): void {
                            $documentQuery->where('number', 'like', '%'.$search.'%');
                        })
                        ->orWhereHas('supplier', function ($supplierQuery) use ($search): void {
                            $supplierQuery->where('name', 'like', '%'.$search.'%');
                        });
                });
            })
            ->when($status !== '' && in_array($status, SupplierPayment::statuses(), true), function ($query) use ($status): void {
                $query->where('status', $status);
            })
            ->when($supplierId > 0, function ($query) use ($supplierId): void {
                $query->where('supplier_id', $supplierId);
            })
            ->when($dateFrom !== '', function ($query) use ($dateFrom): void {
                $query->whereDate('payment_date', '>=', $dateFrom);
            })
            ->when($dateTo !== '', function ($query) use ($dateTo): void {
                $query->whereDate('payment_date', '<=', $dateTo);
            })
            ->orderByDesc('payment_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.supplier-payments.index', [
            'payments' => $payments,
            'statusLabels' => SupplierPayment::statusLabels(),
            'suppliers' => Supplier::query()
                ->forCompany($companyId)
                ->orderBy('name')
                ->get(['id', 'name']),
            'filters' => [
                'q' => $search,
                'status' => $status,
                'supplier_id' => $supplierId > 0 ? $supplierId : '',
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
        ]);
    }

    public function create(Request $request, int $purchaseDocument): View
    {
        $this->authorize('create', SupplierPayment::class);

        $companyId = (int) $request->user()->company_id;
        $document = $this->findCompanyPurchaseDocumentOrFail($companyId, $purchaseDocument);
        $this->authorize('view', $document);

        if (! $document->canReceivePayments()) {
            abort(404);
        }

        $openAmount = $this->paymentStatusService->openAmount($document);
        if ($openAmount <= 0) {
            abort(404);
        }

        return view('admin.supplier-payments.create', [
            'document' => $document->load([
                'supplier:id,name,nif,email,phone,mobile',
            ]),
            'openAmount' => $openAmount,
            'paymentMethods' => PaymentMethod::query()
                ->visibleToCompany($companyId)
                ->orderByDesc('is_system')
                ->orderBy('name')
                ->get(['id', 'name', 'is_system']),
        ]);
    }

    public function store(StoreSupplierPaymentRequest $request, int $purchaseDocument): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;
        $document = $this->findCompanyPurchaseDocumentOrFail($companyId, $purchaseDocument);
        $this->authorize('create', SupplierPayment::class);
        $this->authorize('view', $document);

        $payment = $this->supplierPaymentService->issuePayment(
            companyId: $companyId,
            purchaseDocumentId: (int) $document->id,
            createdBy: (int) $request->user()->id,
            payload: $request->validated(),
        );

        return redirect()
            ->route('admin.supplier-payments.show', $payment->id)
            ->with('status', 'Pagamento registado com sucesso.');
    }

    public function show(Request $request, int $supplierPayment): View
    {
        $companyId = (int) $request->user()->company_id;
        $payment = $this->findCompanyPaymentOrFail($companyId, $supplierPayment);
        $this->authorize('view', $payment);

        return view('admin.supplier-payments.show', [
            'payment' => $payment->load([
                'supplier:id,name,nif,email,phone,mobile,address,postal_code,locality,city',
                'purchaseDocument:id,number,status,payment_status,issue_date,due_date,currency,grand_total',
                'paymentMethod:id,name',
                'creator:id,name',
                'canceller:id,name',
            ]),
            'statusLabels' => SupplierPayment::statusLabels(),
            'paymentStatusLabels' => PurchaseDocument::paymentStatusLabels(),
        ]);
    }

    public function cancel(CancelSupplierPaymentRequest $request, int $supplierPayment): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;
        $payment = $this->findCompanyPaymentOrFail($companyId, $supplierPayment);
        $this->authorize('cancel', $payment);

        $payment = $this->supplierPaymentService->cancelPayment(
            companyId: $companyId,
            paymentId: (int) $payment->id,
            cancelledBy: (int) $request->user()->id,
        );

        return redirect()
            ->route('admin.supplier-payments.show', $payment->id)
            ->with('status', 'Pagamento cancelado com sucesso.');
    }

    public function generatePdf(Request $request, int $supplierPayment): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;
        $payment = $this->findCompanyPaymentOrFail($companyId, $supplierPayment);
        $this->authorize('pdf', $payment);

        $this->supplierPaymentPdfService->generateAndStore($payment);

        return redirect()
            ->route('admin.supplier-payments.show', $payment->id)
            ->with('status', 'PDF do pagamento gerado com sucesso.');
    }

    public function downloadPdf(Request $request, int $supplierPayment): StreamedResponse
    {
        $companyId = (int) $request->user()->company_id;
        $payment = $this->findCompanyPaymentOrFail($companyId, $supplierPayment);
        $this->authorize('pdf', $payment);

        if (! $payment->pdf_path) {
            abort(404);
        }

        return $this->localDiskDownload(
            (string) $payment->pdf_path,
            Str::slug($payment->number).'.pdf',
            ['supplier-payments/'.$companyId.'/'.$payment->id.'/pdf']
        );
    }

    public function sendEmail(SendSupplierPaymentEmailRequest $request, int $supplierPayment): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;
        $payment = $this->findCompanyPaymentOrFail($companyId, $supplierPayment);
        $this->authorize('send', $payment);

        if (! $payment->pdf_path || ! Storage::disk('local')->exists($payment->pdf_path)) {
            $this->supplierPaymentPdfService->generateAndStore($payment);
            $payment->refresh();
        }

        $payment->loadMissing(['company', 'supplier:id,name,email']);
        $this->companyMailSettingsService->applyRuntimeConfig($payment->company);

        $to = $request->validated('to');
        $ccRecipients = $request->ccRecipients();
        $subject = $request->validated('subject');
        $message = $request->validated('message');

        $mailer = Mail::to($to);
        if ($ccRecipients !== []) {
            $mailer->cc($ccRecipients);
        }

        try {
            $mailable = new SupplierPaymentSentMail($payment, $subject, $message);
            if (config('mail.queue_enabled')) {
                $mailer->queue($mailable);
            } else {
                $mailer->send($mailable);
            }
        } catch (Throwable $exception) {
            Log::warning('Supplier payment email send failed', [
                'context' => 'supplier_payments',
                'supplier_payment_id' => (int) $payment->id,
                'company_id' => (int) $payment->company_id,
                'error' => $exception->getMessage(),
            ]);

            return redirect()
                ->route('admin.supplier-payments.show', $payment->id)
                ->withErrors([
                    'supplier_payment_email' => $this->friendlyEmailError($exception),
                ]);
        }

        $payment->forceFill([
            'email_last_sent_to' => $to,
            'email_last_sent_at' => now(),
        ])->save();

        return redirect()
            ->route('admin.supplier-payments.show', $payment->id)
            ->with('status', 'Pagamento enviado por email com sucesso.');
    }

    private function findCompanyPurchaseDocumentOrFail(int $companyId, int $documentId): PurchaseDocument
    {
        return PurchaseDocument::query()
            ->forCompany($companyId)
            ->whereKey($documentId)
            ->firstOrFail();
    }

    private function findCompanyPaymentOrFail(int $companyId, int $paymentId): SupplierPayment
    {
        return SupplierPayment::query()
            ->forCompany($companyId)
            ->whereKey($paymentId)
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

        return 'Falha no envio do pagamento por email. Verifique a configuracao SMTP e tente novamente.';
    }
}
