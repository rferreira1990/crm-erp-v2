<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ChangePurchaseOrderStatusRequest;
use App\Http\Requests\Admin\SendPurchaseOrderEmailRequest;
use App\Mail\Admin\PurchaseOrderSentMail;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderReceipt;
use App\Services\Admin\CompanyMailSettingsService;
use App\Services\Admin\PurchaseOrderPdfService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class PurchaseOrderController extends Controller
{
    public function __construct(
        private readonly PurchaseOrderPdfService $purchaseOrderPdfService,
        private readonly CompanyMailSettingsService $companyMailSettingsService
    ) {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', PurchaseOrder::class);

        $companyId = (int) $request->user()->company_id;
        $search = trim((string) $request->query('q', ''));
        $status = trim((string) $request->query('status', ''));

        $purchaseOrders = PurchaseOrder::query()
            ->forCompany($companyId)
            ->with([
                'rfq:id,number',
                'supplier:id,name',
            ])
            ->withCount('items')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($searchQuery) use ($search): void {
                    $searchQuery
                        ->where('number', 'like', '%'.$search.'%')
                        ->orWhere('supplier_name_snapshot', 'like', '%'.$search.'%');
                });
            })
            ->when($status !== '' && in_array($status, PurchaseOrder::statuses(), true), function ($query) use ($status): void {
                $query->where('status', $status);
            })
            ->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.purchase-orders.index', [
            'purchaseOrders' => $purchaseOrders,
            'statusLabels' => PurchaseOrder::statusLabels(),
            'filters' => [
                'q' => $search,
                'status' => $status,
            ],
        ]);
    }

    public function show(Request $request, int $purchaseOrder): View
    {
        $companyId = (int) $request->user()->company_id;
        $purchaseOrderModel = $this->findCompanyPurchaseOrderOrFail($companyId, $purchaseOrder);
        $this->authorize('view', $purchaseOrderModel);

        $purchaseOrderModel->load([
            'rfq:id,number,title',
            'award:id,mode,awarded_at,awarded_total',
            'creator:id,name',
            'assignedUser:id,name',
            'items' => fn ($query) => $query
                ->orderBy('line_order')
                ->orderBy('id')
                ->with([
                    'receiptItems' => fn ($receiptItemsQuery) => $receiptItemsQuery
                        ->with('receipt:id,status')
                        ->select([
                            'id',
                            'purchase_order_item_id',
                            'purchase_order_receipt_id',
                            'received_quantity',
                        ]),
                ]),
            'receipts' => fn ($query) => $query
                ->with('receiver:id,name')
                ->withCount('items')
                ->orderByDesc('receipt_date')
                ->orderByDesc('id'),
        ]);

        return view('admin.purchase-orders.show', [
            'purchaseOrder' => $purchaseOrderModel,
            'statusLabels' => PurchaseOrder::statusLabels(),
            'receiptStatusLabels' => PurchaseOrderReceipt::statusLabels(),
        ]);
    }

    public function generatePdf(Request $request, int $purchaseOrder): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;
        $purchaseOrderModel = $this->findCompanyPurchaseOrderOrFail($companyId, $purchaseOrder);
        $this->authorize('view', $purchaseOrderModel);

        $this->purchaseOrderPdfService->generateAndStore($purchaseOrderModel);

        return redirect()
            ->route('admin.purchase-orders.show', $purchaseOrderModel->id)
            ->with('status', 'PDF da encomenda gerado com sucesso.');
    }

    public function downloadPdf(Request $request, int $purchaseOrder): StreamedResponse
    {
        $companyId = (int) $request->user()->company_id;
        $purchaseOrderModel = $this->findCompanyPurchaseOrderOrFail($companyId, $purchaseOrder);
        $this->authorize('view', $purchaseOrderModel);

        if (! $purchaseOrderModel->pdf_path || ! Storage::disk('local')->exists($purchaseOrderModel->pdf_path)) {
            abort(404);
        }

        return Storage::disk('local')->download(
            $purchaseOrderModel->pdf_path,
            Str::slug($purchaseOrderModel->number).'.pdf'
        );
    }

    public function sendEmail(SendPurchaseOrderEmailRequest $request, int $purchaseOrder): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;
        $purchaseOrderModel = $this->findCompanyPurchaseOrderOrFail($companyId, $purchaseOrder);
        $this->authorize('send', $purchaseOrderModel);

        if (! $purchaseOrderModel->pdf_path || ! Storage::disk('local')->exists($purchaseOrderModel->pdf_path)) {
            $this->purchaseOrderPdfService->generateAndStore($purchaseOrderModel);
            $purchaseOrderModel->refresh();
        }

        $purchaseOrderModel->loadMissing('company');
        $this->companyMailSettingsService->applyRuntimeConfig($purchaseOrderModel->company);

        $to = $request->validated('to');
        $ccRecipients = $request->ccRecipients();
        $subject = $request->validated('subject');
        $message = $request->validated('message');

        $mailer = Mail::to($to);
        if ($ccRecipients !== []) {
            $mailer->cc($ccRecipients);
        }

        try {
            $mailer->send(new PurchaseOrderSentMail($purchaseOrderModel, $subject, $message));
        } catch (Throwable $exception) {
            Log::warning('Purchase order email send failed', [
                'context' => 'purchase_orders',
                'purchase_order_id' => (int) $purchaseOrderModel->id,
                'company_id' => (int) $purchaseOrderModel->company_id,
                'error' => $exception->getMessage(),
            ]);

            return redirect()
                ->route('admin.purchase-orders.show', $purchaseOrderModel->id)
                ->withErrors([
                    'purchase_order_email' => $this->friendlyEmailError($exception),
                ]);
        }

        $purchaseOrderModel->forceFill([
            'status' => PurchaseOrder::STATUS_SENT,
            'sent_at' => now(),
            'email_last_sent_to' => $to,
            'email_last_sent_at' => now(),
        ])->save();

        return redirect()
            ->route('admin.purchase-orders.show', $purchaseOrderModel->id)
            ->with('status', 'Encomenda enviada por email com sucesso.');
    }

    public function changeStatus(ChangePurchaseOrderStatusRequest $request, int $purchaseOrder): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;
        $purchaseOrderModel = $this->findCompanyPurchaseOrderOrFail($companyId, $purchaseOrder);
        $this->authorize('update', $purchaseOrderModel);

        $toStatus = (string) $request->validated('status');
        if (! $purchaseOrderModel->canTransitionTo($toStatus)) {
            throw ValidationException::withMessages([
                'status' => 'Transicao de estado invalida para o estado atual.',
            ]);
        }

        $payload = $purchaseOrderModel->applyStatusTransition($toStatus);
        $purchaseOrderModel->forceFill($payload)->save();

        return redirect()
            ->route('admin.purchase-orders.show', $purchaseOrderModel->id)
            ->with('status', 'Estado da encomenda atualizado com sucesso.');
    }

    private function findCompanyPurchaseOrderOrFail(int $companyId, int $purchaseOrderId): PurchaseOrder
    {
        return PurchaseOrder::query()
            ->forCompany($companyId)
            ->whereKey($purchaseOrderId)
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

        return 'Falha no envio da encomenda por email. Verifique a configuracao SMTP e tente novamente.';
    }
}
