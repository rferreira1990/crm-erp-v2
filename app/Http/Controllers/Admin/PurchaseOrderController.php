<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ChangePurchaseOrderStatusRequest;
use App\Http\Requests\Admin\SendPurchaseOrderEmailRequest;
use App\Http\Requests\Admin\StorePurchaseOrderRequest;
use App\Http\Requests\Admin\UpdatePurchaseOrderRequest;
use App\Models\Article;
use App\Mail\Admin\PurchaseOrderSentMail;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderReceipt;
use App\Models\Supplier;
use App\Models\Unit;
use App\Services\Admin\CompanyMailSettingsService;
use App\Services\Admin\ManualPurchaseOrderService;
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
        private readonly CompanyMailSettingsService $companyMailSettingsService,
        private readonly ManualPurchaseOrderService $manualPurchaseOrderService
    ) {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', PurchaseOrder::class);

        $companyId = (int) $request->user()->company_id;
        $search = trim((string) $request->query('q', ''));
        $status = trim((string) $request->query('status', ''));
        $sourceType = trim((string) $request->query('source_type', ''));
        $supplierId = (int) $request->query('supplier_id', 0);
        $issueDateFrom = trim((string) $request->query('issue_date_from', ''));
        $issueDateTo = trim((string) $request->query('issue_date_to', ''));

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
            ->when($sourceType === PurchaseOrder::SOURCE_MANUAL, function ($query): void {
                $query->whereNull('supplier_quote_request_id')
                    ->whereNull('supplier_quote_award_id');
            })
            ->when($sourceType === PurchaseOrder::SOURCE_RFQ, function ($query): void {
                $query->where(function ($sourceQuery): void {
                    $sourceQuery
                        ->whereNotNull('supplier_quote_request_id')
                        ->orWhereNotNull('supplier_quote_award_id');
                });
            })
            ->when($supplierId > 0, function ($query) use ($supplierId): void {
                $query->where('supplier_id', $supplierId);
            })
            ->when($this->isIsoDate($issueDateFrom), function ($query) use ($issueDateFrom): void {
                $query->whereDate('issue_date', '>=', $issueDateFrom);
            })
            ->when($this->isIsoDate($issueDateTo), function ($query) use ($issueDateTo): void {
                $query->whereDate('issue_date', '<=', $issueDateTo);
            })
            ->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.purchase-orders.index', [
            'purchaseOrders' => $purchaseOrders,
            'statusLabels' => PurchaseOrder::statusLabels(),
            'sourceTypeLabels' => [
                PurchaseOrder::SOURCE_MANUAL => 'Manual',
                PurchaseOrder::SOURCE_RFQ => 'RFQ',
            ],
            'suppliers' => Supplier::query()
                ->forCompany($companyId)
                ->orderBy('name')
                ->get(['id', 'name']),
            'filters' => [
                'q' => $search,
                'status' => $status,
                'source_type' => $sourceType,
                'supplier_id' => $supplierId > 0 ? $supplierId : '',
                'issue_date_from' => $issueDateFrom,
                'issue_date_to' => $issueDateTo,
            ],
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', PurchaseOrder::class);

        $companyId = (int) $request->user()->company_id;

        return view('admin.purchase-orders.create', [
            'suppliers' => Supplier::query()
                ->forCompany($companyId)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'phone', 'mobile', 'address', 'postal_code', 'locality', 'city']),
            'articles' => Article::query()
                ->forCompany($companyId)
                ->where('is_active', true)
                ->with('unit:id,code,name')
                ->orderBy('designation')
                ->get(['id', 'code', 'designation', 'unit_id', 'cost_price']),
            'units' => Unit::query()
                ->visibleToCompany($companyId)
                ->orderBy('code')
                ->get(['id', 'code', 'name']),
            'defaults' => [
                'issue_date' => now()->toDateString(),
            ],
        ]);
    }

    public function store(StorePurchaseOrderRequest $request): RedirectResponse
    {
        $this->authorize('create', PurchaseOrder::class);

        $purchaseOrder = $this->manualPurchaseOrderService->create(
            companyId: (int) $request->user()->company_id,
            createdBy: (int) $request->user()->id,
            payload: $request->validated()
        );

        return redirect()
            ->route('admin.purchase-orders.show', $purchaseOrder->id)
            ->with('status', 'Encomenda criada com sucesso.');
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

    public function edit(Request $request, int $purchaseOrder): View|RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;
        $purchaseOrderModel = $this->findCompanyPurchaseOrderOrFail($companyId, $purchaseOrder);
        $this->authorize('update', $purchaseOrderModel);

        if (! $purchaseOrderModel->isEditableManualDraft()) {
            abort(404);
        }

        if ($purchaseOrderModel->receipts()->exists()) {
            return redirect()
                ->route('admin.purchase-orders.show', $purchaseOrderModel->id)
                ->withErrors([
                    'purchase_order' => 'Nao e possivel editar a encomenda porque ja tem rececoes associadas.',
                ]);
        }

        $purchaseOrderModel->load([
            'items' => fn ($query) => $query->orderBy('line_order')->orderBy('id'),
        ]);

        return view('admin.purchase-orders.edit', [
            'purchaseOrder' => $purchaseOrderModel,
            'suppliers' => Supplier::query()
                ->forCompany($companyId)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'phone', 'mobile', 'address', 'postal_code', 'locality', 'city']),
            'articles' => Article::query()
                ->forCompany($companyId)
                ->where('is_active', true)
                ->with('unit:id,code,name')
                ->orderBy('designation')
                ->get(['id', 'code', 'designation', 'unit_id', 'cost_price']),
            'units' => Unit::query()
                ->visibleToCompany($companyId)
                ->orderBy('code')
                ->get(['id', 'code', 'name']),
        ]);
    }

    public function update(UpdatePurchaseOrderRequest $request, int $purchaseOrder): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;
        $purchaseOrderModel = $this->findCompanyPurchaseOrderOrFail($companyId, $purchaseOrder);
        $this->authorize('update', $purchaseOrderModel);

        if (! $purchaseOrderModel->isEditableManualDraft()) {
            abort(404);
        }

        $purchaseOrderModel = $this->manualPurchaseOrderService->update(
            companyId: $companyId,
            purchaseOrderId: (int) $purchaseOrderModel->id,
            payload: $request->validated()
        );

        return redirect()
            ->route('admin.purchase-orders.show', $purchaseOrderModel->id)
            ->with('status', 'Encomenda atualizada com sucesso.');
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

    private function isIsoDate(string $value): bool
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1;
    }
}
