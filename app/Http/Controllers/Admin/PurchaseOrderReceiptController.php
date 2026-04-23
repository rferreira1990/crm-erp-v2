<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PostPurchaseOrderReceiptRequest;
use App\Http\Requests\Admin\ResolvePurchaseOrderReceiptLineRequest;
use App\Http\Requests\Admin\StorePurchaseOrderReceiptRequest;
use App\Http\Requests\Admin\UpdatePurchaseOrderReceiptRequest;
use App\Models\Article;
use App\Models\Category;
use App\Models\ProductFamily;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderReceipt;
use App\Models\PurchaseOrderReceiptItem;
use App\Models\Unit;
use App\Models\VatRate;
use App\Services\Admin\PurchaseOrderReceiptPdfService;
use App\Services\Admin\PurchaseOrderReceiptLineResolutionService;
use App\Services\Admin\PurchaseOrderReceiptService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PurchaseOrderReceiptController extends Controller
{
    public function __construct(
        private readonly PurchaseOrderReceiptService $purchaseOrderReceiptService,
        private readonly PurchaseOrderReceiptPdfService $purchaseOrderReceiptPdfService,
        private readonly PurchaseOrderReceiptLineResolutionService $lineResolutionService
    ) {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', PurchaseOrderReceipt::class);

        $companyId = (int) $request->user()->company_id;
        $search = trim((string) $request->query('q', ''));
        $status = trim((string) $request->query('status', ''));

        $receipts = PurchaseOrderReceipt::query()
            ->forCompany($companyId)
            ->with([
                'purchaseOrder:id,number,supplier_name_snapshot,status',
                'receiver:id,name',
            ])
            ->withCount('items')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($searchQuery) use ($search): void {
                    $searchQuery
                        ->where('number', 'like', '%'.$search.'%')
                        ->orWhereHas('purchaseOrder', function ($poQuery) use ($search): void {
                            $poQuery
                                ->where('number', 'like', '%'.$search.'%')
                                ->orWhere('supplier_name_snapshot', 'like', '%'.$search.'%');
                        });
                });
            })
            ->when(
                $status !== '' && in_array($status, PurchaseOrderReceipt::statuses(), true),
                fn ($query) => $query->where('status', $status)
            )
            ->orderByDesc('receipt_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.purchase-order-receipts.index', [
            'receipts' => $receipts,
            'statusLabels' => PurchaseOrderReceipt::statusLabels(),
            'filters' => [
                'q' => $search,
                'status' => $status,
            ],
        ]);
    }

    public function create(Request $request, int $purchaseOrder): View|RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;
        $purchaseOrderModel = $this->findCompanyPurchaseOrderOrFail($companyId, $purchaseOrder);

        $this->authorize('view', $purchaseOrderModel);
        $this->authorize('create', PurchaseOrderReceipt::class);

        try {
            $lines = $this->purchaseOrderReceiptService->buildCreateLines($purchaseOrderModel);
        } catch (ValidationException $exception) {
            return redirect()
                ->route('admin.purchase-orders.show', $purchaseOrderModel->id)
                ->withErrors($exception->errors());
        }

        return view('admin.purchase-order-receipts.create', [
            'purchaseOrder' => $purchaseOrderModel->loadMissing('supplier:id,name'),
            'lines' => $lines,
        ]);
    }

    public function store(StorePurchaseOrderReceiptRequest $request, int $purchaseOrder): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;
        $purchaseOrderModel = $this->findCompanyPurchaseOrderOrFail($companyId, $purchaseOrder);

        $this->authorize('view', $purchaseOrderModel);
        $this->authorize('create', PurchaseOrderReceipt::class);

        $receipt = $this->purchaseOrderReceiptService->createDraft(
            purchaseOrder: $purchaseOrderModel,
            receivedBy: (int) $request->user()->id,
            payload: $request->validated()
        );

        return redirect()
            ->route('admin.purchase-order-receipts.show', $receipt->id)
            ->with('status', 'Rececao registada em rascunho com sucesso.');
    }

    public function show(Request $request, int $purchaseOrderReceipt): View
    {
        $companyId = (int) $request->user()->company_id;
        $receipt = $this->findCompanyReceiptOrFail($companyId, $purchaseOrderReceipt);
        $this->authorize('view', $receipt);

        $receipt->load([
            'receiver:id,name',
            'purchaseOrder:id,number,status,supplier_name_snapshot,currency,supplier_quote_request_id',
            'purchaseOrder.rfq:id,number',
            'items' => fn ($query) => $query
                ->orderBy('line_order')
                ->orderBy('id')
                ->with('article:id,code,designation'),
            'stockMovements' => fn ($query) => $query
                ->with([
                    'article:id,code,designation',
                    'performer:id,name',
                ])
                ->orderByDesc('movement_date')
                ->orderByDesc('id'),
        ]);

        $unresolvedStockLines = $this->unresolvedStockLines($receipt->items);

        return view('admin.purchase-order-receipts.show', [
            'receipt' => $receipt,
            'statusLabels' => PurchaseOrderReceipt::statusLabels(),
            'purchaseOrderStatusLabels' => PurchaseOrder::statusLabels(),
            'unresolvedStockLines' => $unresolvedStockLines,
            'articleResolutionOptions' => $this->articleResolutionOptions(
                companyId: (int) $receipt->company_id,
                includeArticleCreateOptions: $unresolvedStockLines->isNotEmpty()
            ),
        ]);
    }

    public function generatePdf(Request $request, int $purchaseOrderReceipt): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;
        $receipt = $this->findCompanyReceiptOrFail($companyId, $purchaseOrderReceipt);
        $this->authorize('view', $receipt);

        $this->purchaseOrderReceiptPdfService->generateAndStore($receipt);

        return redirect()
            ->route('admin.purchase-order-receipts.show', $receipt->id)
            ->with('status', 'PDF da rececao gerado com sucesso.');
    }

    public function downloadPdf(Request $request, int $purchaseOrderReceipt): StreamedResponse
    {
        $companyId = (int) $request->user()->company_id;
        $receipt = $this->findCompanyReceiptOrFail($companyId, $purchaseOrderReceipt);
        $this->authorize('view', $receipt);

        if (! $receipt->pdf_path || ! Storage::disk('local')->exists($receipt->pdf_path)) {
            abort(404);
        }

        return Storage::disk('local')->download(
            $receipt->pdf_path,
            Str::slug($receipt->number).'.pdf'
        );
    }

    public function edit(Request $request, int $purchaseOrderReceipt): View|RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;
        $receipt = $this->findCompanyReceiptOrFail($companyId, $purchaseOrderReceipt);
        $this->authorize('update', $receipt);

        if (! $receipt->isEditable()) {
            return redirect()
                ->route('admin.purchase-order-receipts.show', $receipt->id)
                ->withErrors([
                    'receipt' => 'Apenas rececoes em rascunho podem ser editadas.',
                ]);
        }

        $receipt->load([
            'purchaseOrder:id,number,status,supplier_name_snapshot',
            'items' => fn ($query) => $query->orderBy('line_order')->orderBy('id'),
        ]);

        try {
            $baseLines = $this->purchaseOrderReceiptService->buildCreateLines($receipt->purchaseOrder);
        } catch (ValidationException $exception) {
            return redirect()
                ->route('admin.purchase-order-receipts.show', $receipt->id)
                ->withErrors($exception->errors());
        }

        $lineByItemId = $receipt->items->keyBy('purchase_order_item_id');
        $lines = collect($baseLines)
            ->map(function (array $line) use ($lineByItemId): array {
                $itemId = (int) ($line['purchase_order_item_id'] ?? 0);
                $receiptLine = $lineByItemId->get($itemId);

                if (! $receiptLine) {
                    return $line;
                }

                $line['received_quantity'] = (float) $receiptLine->received_quantity;
                $line['notes'] = $receiptLine->notes;

                return $line;
            })
            ->values()
            ->all();

        return view('admin.purchase-order-receipts.edit', [
            'receipt' => $receipt,
            'purchaseOrder' => $receipt->purchaseOrder,
            'lines' => $lines,
        ]);
    }

    public function update(UpdatePurchaseOrderReceiptRequest $request, int $purchaseOrderReceipt): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;
        $receipt = $this->findCompanyReceiptOrFail($companyId, $purchaseOrderReceipt);
        $this->authorize('update', $receipt);

        $updated = $this->purchaseOrderReceiptService->updateDraft($receipt, $request->validated());

        return redirect()
            ->route('admin.purchase-order-receipts.show', $updated->id)
            ->with('status', 'Rececao atualizada com sucesso.');
    }

    public function post(PostPurchaseOrderReceiptRequest $request, int $purchaseOrderReceipt): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;
        $receipt = $this->findCompanyReceiptOrFail($companyId, $purchaseOrderReceipt);
        $this->authorize('post', $receipt);

        $isFinal = $request->has('is_final')
            ? (bool) $request->boolean('is_final')
            : null;

        $posted = $this->purchaseOrderReceiptService->post($receipt, $isFinal);

        return redirect()
            ->route('admin.purchase-order-receipts.show', $posted->id)
            ->with('status', 'Rececao confirmada com sucesso.');
    }

    public function cancel(Request $request, int $purchaseOrderReceipt): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;
        $receipt = $this->findCompanyReceiptOrFail($companyId, $purchaseOrderReceipt);
        $this->authorize('delete', $receipt);

        $cancelled = $this->purchaseOrderReceiptService->cancelDraft($receipt);

        return redirect()
            ->route('admin.purchase-order-receipts.show', $cancelled->id)
            ->with('status', 'Rececao cancelada com sucesso.');
    }

    public function resolveLine(
        ResolvePurchaseOrderReceiptLineRequest $request,
        int $purchaseOrderReceipt,
        int $receiptItem
    ): RedirectResponse {
        $companyId = (int) $request->user()->company_id;
        $receipt = $this->findCompanyReceiptOrFail($companyId, $purchaseOrderReceipt);
        $this->authorize('update', $receipt);

        $line = $receipt->items()
            ->whereKey($receiptItem)
            ->firstOrFail();

        $action = (string) $request->validated('action');
        if ($action === ResolvePurchaseOrderReceiptLineRequest::ACTION_CREATE_NEW) {
            $this->authorize('create', Article::class);
        }

        $this->lineResolutionService->resolve(
            receipt: $receipt,
            receiptItem: $line,
            payload: $request->validated(),
            userId: (int) $request->user()->id
        );

        return redirect()
            ->route('admin.purchase-order-receipts.show', $receipt->id)
            ->with('status', 'Linha de rececao resolvida com sucesso.');
    }

    private function findCompanyPurchaseOrderOrFail(int $companyId, int $purchaseOrderId): PurchaseOrder
    {
        return PurchaseOrder::query()
            ->forCompany($companyId)
            ->whereKey($purchaseOrderId)
            ->firstOrFail();
    }

    private function findCompanyReceiptOrFail(int $companyId, int $receiptId): PurchaseOrderReceipt
    {
        return PurchaseOrderReceipt::query()
            ->forCompany($companyId)
            ->whereKey($receiptId)
            ->firstOrFail();
    }

    /**
     * @param Collection<int, PurchaseOrderReceiptItem> $items
     * @return Collection<int, PurchaseOrderReceiptItem>
     */
    private function unresolvedStockLines(Collection $items): Collection
    {
        return $items
            ->filter(fn (PurchaseOrderReceiptItem $item): bool => $item->requiresStockResolutionDecision())
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    private function articleResolutionOptions(int $companyId, bool $includeArticleCreateOptions): array
    {
        $existingArticles = Article::query()
            ->forCompany($companyId)
            ->where('is_active', true)
            ->orderBy('designation')
            ->limit(300)
            ->get(['id', 'code', 'designation', 'moves_stock']);

        if (! $includeArticleCreateOptions) {
            return [
                'existingArticles' => $existingArticles,
                'familyOptions' => collect(),
                'categoryOptions' => collect(),
                'unitOptions' => collect(),
                'vatRateOptions' => collect(),
                'defaults' => [
                    'category_id' => Article::defaultCategoryIdForCompany($companyId),
                    'unit_id' => Article::defaultUnitIdForCompany($companyId),
                ],
            ];
        }

        $vatRateOptions = VatRate::query()
            ->with([
                'companyOverrides' => fn ($query) => $query->where('company_id', $companyId),
            ])
            ->visibleToCompany($companyId)
            ->get()
            ->filter(fn (VatRate $vatRate): bool => $vatRate->isEnabledForCompany($companyId))
            ->sortBy([
                ['region', 'asc'],
                ['is_exempt', 'asc'],
                ['rate', 'desc'],
                ['name', 'asc'],
            ])
            ->values();

        return [
            'existingArticles' => $existingArticles,
            'familyOptions' => ProductFamily::query()
                ->visibleToCompany($companyId)
                ->whereNotNull('family_code')
                ->orderBy('name')
                ->get(['id', 'name', 'family_code']),
            'categoryOptions' => Category::query()
                ->visibleToCompany($companyId)
                ->orderBy('name')
                ->get(['id', 'name']),
            'unitOptions' => Unit::query()
                ->visibleToCompany($companyId)
                ->orderBy('name')
                ->get(['id', 'code', 'name']),
            'vatRateOptions' => $vatRateOptions,
            'defaults' => [
                'category_id' => Article::defaultCategoryIdForCompany($companyId),
                'unit_id' => Article::defaultUnitIdForCompany($companyId),
            ],
        ];
    }
}
