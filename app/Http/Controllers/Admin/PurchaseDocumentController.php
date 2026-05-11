<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CancelPurchaseDocumentRequest;
use App\Http\Requests\Admin\ConfirmPurchaseDocumentRequest;
use App\Http\Requests\Admin\StorePurchaseDocumentRequest;
use App\Http\Requests\Admin\UpdatePurchaseDocumentRequest;
use App\Models\Article;
use App\Models\PurchaseDocument;
use App\Models\PurchaseOrder;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\Unit;
use App\Services\Admin\PurchaseDocumentConfirmationService;
use App\Services\Admin\PurchaseDocumentCreationService;
use App\Services\Admin\PurchaseDocumentPaymentStatusService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PurchaseDocumentController extends Controller
{
    public function __construct(
        private readonly PurchaseDocumentCreationService $creationService,
        private readonly PurchaseDocumentConfirmationService $confirmationService,
        private readonly PurchaseDocumentPaymentStatusService $paymentStatusService
    ) {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', PurchaseDocument::class);

        $companyId = (int) $request->user()->company_id;
        $search = trim((string) $request->query('q', ''));
        $status = trim((string) $request->query('status', ''));
        $paymentStatus = trim((string) $request->query('payment_status', ''));
        $supplierId = (int) $request->query('supplier_id', 0);

        $documents = PurchaseDocument::query()
            ->forCompany($companyId)
            ->with([
                'supplier:id,name',
                'purchaseOrder:id,number',
            ])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($searchQuery) use ($search): void {
                    $searchQuery
                        ->where('number', 'like', '%'.$search.'%')
                        ->orWhere('supplier_document_number', 'like', '%'.$search.'%')
                        ->orWhereHas('supplier', fn ($supplierQuery) => $supplierQuery->where('name', 'like', '%'.$search.'%'));
                });
            })
            ->when($status !== '' && in_array($status, PurchaseDocument::statuses(), true), function ($query) use ($status): void {
                $query->where('status', $status);
            })
            ->when($paymentStatus !== '' && in_array($paymentStatus, PurchaseDocument::paymentStatuses(), true), function ($query) use ($paymentStatus): void {
                $query->where('payment_status', $paymentStatus);
            })
            ->when($supplierId > 0, function ($query) use ($supplierId): void {
                $query->where('supplier_id', $supplierId);
            })
            ->withCount('items')
            ->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.purchase-documents.index', [
            'documents' => $documents,
            'statusLabels' => PurchaseDocument::statusLabels(),
            'paymentStatusLabels' => PurchaseDocument::paymentStatusLabels(),
            'suppliers' => Supplier::query()
                ->forCompany($companyId)
                ->orderBy('name')
                ->get(['id', 'name']),
            'filters' => [
                'q' => $search,
                'status' => $status,
                'payment_status' => $paymentStatus,
                'supplier_id' => $supplierId > 0 ? $supplierId : '',
            ],
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', PurchaseDocument::class);

        $companyId = (int) $request->user()->company_id;
        $purchaseOrderId = (int) $request->query('purchase_order_id', 0);
        $defaults = $this->buildCreateDefaults($companyId, $purchaseOrderId > 0 ? $purchaseOrderId : null);

        return view('admin.purchase-documents.create', [
            ...$this->buildFormData($companyId, $defaults['supplier_id']),
            'defaults' => $defaults,
        ]);
    }

    public function store(StorePurchaseDocumentRequest $request): RedirectResponse
    {
        $this->authorize('create', PurchaseDocument::class);

        $document = $this->creationService->createDraft(
            companyId: (int) $request->user()->company_id,
            createdBy: (int) $request->user()->id,
            payload: $request->validated()
        );

        return redirect()
            ->route('admin.purchase-documents.show', $document->id)
            ->with('status', 'Documento de Compra criado com sucesso.');
    }

    public function show(Request $request, int $purchaseDocument): View
    {
        $companyId = (int) $request->user()->company_id;
        $document = $this->findCompanyPurchaseDocumentOrFail($companyId, $purchaseDocument);
        $this->authorize('view', $document);

        $document->load([
            'supplier:id,name,email,phone,mobile,nif',
            'purchaseOrder:id,number,status',
            'creator:id,name',
            'updater:id,name',
            'canceller:id,name',
            'items' => fn ($query) => $query
                ->orderBy('line_order')
                ->orderBy('id')
                ->with([
                    'article:id,code,designation',
                    'unit:id,code,name',
                ]),
            'payments' => fn ($query) => $query
                ->orderByDesc('payment_date')
                ->orderByDesc('id')
                ->with([
                    'paymentMethod:id,name',
                    'creator:id,name',
                    'canceller:id,name',
                ]),
        ]);

        $totalPaid = round((float) $document->payments
            ->where('status', SupplierPayment::STATUS_ISSUED)
            ->sum('amount'), 2);
        $openAmount = $this->paymentStatusService->openAmount($document);
        $stockMovementsCount = StockMovement::query()
            ->forCompany($companyId)
            ->where('reference_type', StockMovement::REFERENCE_PURCHASE_DOCUMENT)
            ->where('reference_id', (int) $document->id)
            ->count();

        return view('admin.purchase-documents.show', [
            'document' => $document,
            'statusLabels' => PurchaseDocument::statusLabels(),
            'paymentStatusLabels' => PurchaseDocument::paymentStatusLabels(),
            'paymentStatuses' => SupplierPayment::statusLabels(),
            'totalPaid' => $totalPaid,
            'openAmount' => $openAmount,
            'stockMovementsCount' => $stockMovementsCount,
        ]);
    }

    public function edit(Request $request, int $purchaseDocument): View
    {
        $companyId = (int) $request->user()->company_id;
        $document = $this->findCompanyPurchaseDocumentOrFail($companyId, $purchaseDocument);
        $this->authorize('update', $document);

        if (! $document->isEditableDraft()) {
            abort(404);
        }

        $document->load([
            'items' => fn ($query) => $query->orderBy('line_order')->orderBy('id'),
        ]);

        $defaults = [
            'supplier_document_number' => $document->supplier_document_number,
            'supplier_id' => $document->supplier_id ? (int) $document->supplier_id : null,
            'purchase_order_id' => $document->purchase_order_id ? (int) $document->purchase_order_id : null,
            'issue_date' => $document->issue_date?->toDateString() ?? now()->toDateString(),
            'due_date' => $document->due_date?->toDateString(),
            'notes' => $document->notes,
            'items' => $document->items->map(function ($item): array {
                return [
                    'purchase_order_item_id' => $item->purchase_order_item_id ? (int) $item->purchase_order_item_id : null,
                    'article_id' => $item->article_id ? (int) $item->article_id : null,
                    'description' => (string) $item->description,
                    'unit_id' => $item->unit_id ? (int) $item->unit_id : null,
                    'unit_name_snapshot' => $item->unit_name_snapshot,
                    'quantity' => number_format((float) $item->quantity, 3, '.', ''),
                    'unit_price' => number_format((float) $item->unit_price, 4, '.', ''),
                    'discount_percent' => number_format((float) ($item->discount_percent ?? 0), 2, '.', ''),
                    'tax_rate' => number_format((float) ($item->tax_rate ?? 0), 2, '.', ''),
                ];
            })->values()->all(),
        ];

        return view('admin.purchase-documents.edit', [
            'document' => $document,
            ...$this->buildFormData($companyId, $document->supplier_id ? (int) $document->supplier_id : null),
            'defaults' => $defaults,
        ]);
    }

    public function update(UpdatePurchaseDocumentRequest $request, int $purchaseDocument): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;
        $document = $this->findCompanyPurchaseDocumentOrFail($companyId, $purchaseDocument);
        $this->authorize('update', $document);

        if (! $document->isEditableDraft()) {
            abort(404);
        }

        $updated = $this->creationService->updateDraft(
            companyId: $companyId,
            documentId: (int) $document->id,
            updatedBy: (int) $request->user()->id,
            payload: $request->validated(),
        );

        return redirect()
            ->route('admin.purchase-documents.show', $updated->id)
            ->with('status', 'Documento de Compra atualizado com sucesso.');
    }

    public function confirm(ConfirmPurchaseDocumentRequest $request, int $purchaseDocument): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;
        $document = $this->findCompanyPurchaseDocumentOrFail($companyId, $purchaseDocument);
        $this->authorize('confirm', $document);

        $confirmed = $this->confirmationService->confirm(
            companyId: $companyId,
            documentId: (int) $document->id,
            performedBy: (int) $request->user()->id,
        );

        return redirect()
            ->route('admin.purchase-documents.show', $confirmed->id)
            ->with('status', 'Documento de Compra confirmado com sucesso.');
    }

    public function cancel(CancelPurchaseDocumentRequest $request, int $purchaseDocument): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;
        $document = $this->findCompanyPurchaseDocumentOrFail($companyId, $purchaseDocument);
        $this->authorize('cancel', $document);

        $cancelled = $this->confirmationService->cancel(
            companyId: $companyId,
            documentId: (int) $document->id,
            performedBy: (int) $request->user()->id,
        );

        return redirect()
            ->route('admin.purchase-documents.show', $cancelled->id)
            ->with('status', 'Documento de Compra cancelado com sucesso.');
    }

    private function findCompanyPurchaseDocumentOrFail(int $companyId, int $documentId): PurchaseDocument
    {
        return PurchaseDocument::query()
            ->forCompany($companyId)
            ->whereKey($documentId)
            ->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    private function buildFormData(int $companyId, ?int $selectedSupplierId): array
    {
        return [
            'selectedSupplierId' => $selectedSupplierId,
            'suppliers' => Supplier::query()
                ->forCompany($companyId)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'nif', 'email', 'phone', 'mobile']),
            'purchaseOrders' => PurchaseOrder::query()
                ->forCompany($companyId)
                ->with(['supplier:id,name'])
                ->orderByDesc('issue_date')
                ->orderByDesc('id')
                ->get(['id', 'number', 'supplier_id', 'status', 'issue_date']),
            'articles' => Article::query()
                ->forCompany($companyId)
                ->where('is_active', true)
                ->with('unit:id,code,name')
                ->orderBy('designation')
                ->get(['id', 'code', 'designation', 'unit_id', 'cost_price', 'vat_rate_id']),
            'units' => Unit::query()
                ->visibleToCompany($companyId)
                ->orderBy('code')
                ->get(['id', 'code', 'name']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildCreateDefaults(int $companyId, ?int $purchaseOrderId): array
    {
        $defaults = [
            'supplier_document_number' => null,
            'supplier_id' => null,
            'purchase_order_id' => null,
            'issue_date' => now()->toDateString(),
            'due_date' => null,
            'notes' => null,
            'items' => [
                [
                    'purchase_order_item_id' => null,
                    'article_id' => null,
                    'description' => null,
                    'unit_id' => null,
                    'unit_name_snapshot' => null,
                    'quantity' => '1.000',
                    'unit_price' => '0.0000',
                    'discount_percent' => '0.00',
                    'tax_rate' => '0.00',
                ],
            ],
        ];

        if (! $purchaseOrderId) {
            return $defaults;
        }

        $purchaseOrder = PurchaseOrder::query()
            ->forCompany($companyId)
            ->with([
                'items' => fn ($query) => $query->orderBy('line_order')->orderBy('id'),
            ])
            ->whereKey($purchaseOrderId)
            ->firstOrFail();

        $items = $purchaseOrder->items->map(function ($item): array {
            return [
                'purchase_order_item_id' => (int) $item->id,
                'article_id' => $item->article_id ? (int) $item->article_id : null,
                'description' => (string) ($item->description ?: '-'),
                'unit_id' => null,
                'unit_name_snapshot' => $item->unit_name,
                'quantity' => number_format((float) $item->quantity, 3, '.', ''),
                'unit_price' => number_format((float) $item->unit_price, 4, '.', ''),
                'discount_percent' => number_format((float) ($item->discount_percent ?? 0), 2, '.', ''),
                'tax_rate' => number_format((float) ($item->vat_percent ?? 0), 2, '.', ''),
            ];
        })->values()->all();

        if ($items === []) {
            $items = $defaults['items'];
        }

        return [
            ...$defaults,
            'supplier_id' => $purchaseOrder->supplier_id ? (int) $purchaseOrder->supplier_id : null,
            'purchase_order_id' => (int) $purchaseOrder->id,
            'issue_date' => now()->toDateString(),
            'notes' => $purchaseOrder->supplier_notes,
            'items' => $items,
        ];
    }
}
