<?php

namespace App\Services\Admin;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseOrderReceipt;
use App\Models\PurchaseOrderReceiptItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseOrderReceiptService
{
    private const EPSILON = 0.0005;

    public function __construct(
        private readonly PurchaseOrderReceiptStockService $purchaseOrderReceiptStockService
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function buildCreateLines(PurchaseOrder $purchaseOrder): array
    {
        $this->assertPurchaseOrderCanReceive($purchaseOrder);

        $purchaseOrder->loadMissing([
            'items' => fn ($query) => $query->orderBy('line_order')->orderBy('id'),
        ]);

        $postedMap = $this->postedReceivedMap((int) $purchaseOrder->id);

        return $purchaseOrder->items
            ->map(function (PurchaseOrderItem $item) use ($postedMap): array {
                $ordered = round((float) ($item->quantity ?? 0), 3);
                $previous = round((float) ($postedMap[(int) $item->id] ?? 0), 3);
                $remaining = round(max(0.0, $ordered - $previous), 3);

                return [
                    'purchase_order_item_id' => (int) $item->id,
                    'line_order' => (int) ($item->line_order ?? 1),
                    'article_id' => $item->article_id,
                    'article_code' => $item->article_code,
                    'description' => (string) ($item->description ?: '-'),
                    'unit_name' => $item->unit_name,
                    'ordered_quantity' => $ordered,
                    'previously_received_quantity' => $previous,
                    'remaining_quantity' => $remaining,
                    'received_quantity' => 0.0,
                    'notes' => null,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function createDraft(PurchaseOrder $purchaseOrder, int $receivedBy, array $payload): PurchaseOrderReceipt
    {
        return DB::transaction(function () use ($purchaseOrder, $receivedBy, $payload): PurchaseOrderReceipt {
            /** @var PurchaseOrder $lockedPurchaseOrder */
            $lockedPurchaseOrder = PurchaseOrder::query()
                ->whereKey((int) $purchaseOrder->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertPurchaseOrderCanReceive($lockedPurchaseOrder);

            /** @var Collection<int, PurchaseOrderItem> $poItems */
            $poItems = $lockedPurchaseOrder->items()
                ->orderBy('line_order')
                ->orderBy('id')
                ->get();

            $postedMap = $this->postedReceivedMap((int) $lockedPurchaseOrder->id);
            $inputMap = $this->normalizeItemsInput((array) ($payload['items'] ?? []));

            $itemsPayload = $this->buildReceiptItemsPayload($poItems, $inputMap, $postedMap);

            /** @var PurchaseOrderReceipt $receipt */
            $receipt = PurchaseOrderReceipt::createWithGeneratedNumber((int) $lockedPurchaseOrder->company_id, [
                'purchase_order_id' => (int) $lockedPurchaseOrder->id,
                'status' => PurchaseOrderReceipt::STATUS_DRAFT,
                'receipt_date' => (string) $payload['receipt_date'],
                'supplier_document_number' => $this->normalizeNullableString($payload['supplier_document_number'] ?? null),
                'supplier_document_date' => $payload['supplier_document_date'] ?? null,
                'notes' => $this->normalizeNullableString($payload['notes'] ?? null),
                'internal_notes' => $this->normalizeNullableString($payload['internal_notes'] ?? null),
                'received_by' => $receivedBy,
                'pdf_path' => null,
                'is_final' => false,
            ]);

            $receipt->items()->createMany($itemsPayload);

            return $receipt->load([
                'purchaseOrder',
                'items' => fn ($query) => $query->orderBy('line_order')->orderBy('id'),
            ]);
        });
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function updateDraft(PurchaseOrderReceipt $receipt, array $payload): PurchaseOrderReceipt
    {
        return DB::transaction(function () use ($receipt, $payload): PurchaseOrderReceipt {
            /** @var PurchaseOrderReceipt $lockedReceipt */
            $lockedReceipt = PurchaseOrderReceipt::query()
                ->whereKey((int) $receipt->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedReceipt->isEditable()) {
                throw ValidationException::withMessages([
                    'receipt' => 'Apenas rececoes em rascunho podem ser editadas.',
                ]);
            }

            /** @var PurchaseOrder $lockedPurchaseOrder */
            $lockedPurchaseOrder = PurchaseOrder::query()
                ->whereKey((int) $lockedReceipt->purchase_order_id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertPurchaseOrderCanReceive($lockedPurchaseOrder);

            /** @var Collection<int, PurchaseOrderItem> $poItems */
            $poItems = $lockedPurchaseOrder->items()
                ->orderBy('line_order')
                ->orderBy('id')
                ->get();

            $postedMap = $this->postedReceivedMap((int) $lockedPurchaseOrder->id, (int) $lockedReceipt->id);
            $inputMap = $this->normalizeItemsInput((array) ($payload['items'] ?? []));

            $itemsPayload = $this->buildReceiptItemsPayload($poItems, $inputMap, $postedMap);

            $lockedReceipt->forceFill([
                'receipt_date' => (string) $payload['receipt_date'],
                'supplier_document_number' => $this->normalizeNullableString($payload['supplier_document_number'] ?? null),
                'supplier_document_date' => $payload['supplier_document_date'] ?? null,
                'notes' => $this->normalizeNullableString($payload['notes'] ?? null),
                'internal_notes' => $this->normalizeNullableString($payload['internal_notes'] ?? null),
            ])->save();

            $lockedReceipt->items()->delete();
            $lockedReceipt->items()->createMany($itemsPayload);

            return $lockedReceipt->load([
                'purchaseOrder',
                'items' => fn ($query) => $query->orderBy('line_order')->orderBy('id'),
            ]);
        });
    }

    public function post(PurchaseOrderReceipt $receipt, ?bool $isFinal = null): PurchaseOrderReceipt
    {
        return DB::transaction(function () use ($receipt, $isFinal): PurchaseOrderReceipt {
            /** @var PurchaseOrderReceipt $lockedReceipt */
            $lockedReceipt = PurchaseOrderReceipt::query()
                ->whereKey((int) $receipt->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedReceipt->canPost()) {
                throw ValidationException::withMessages([
                    'receipt' => 'Apenas rececoes em rascunho podem ser confirmadas.',
                ]);
            }

            /** @var PurchaseOrder $lockedPurchaseOrder */
            $lockedPurchaseOrder = PurchaseOrder::query()
                ->whereKey((int) $lockedReceipt->purchase_order_id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertPurchaseOrderCanReceive($lockedPurchaseOrder);

            /** @var Collection<int, PurchaseOrderItem> $poItems */
            $poItems = $lockedPurchaseOrder->items()
                ->orderBy('line_order')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $poItemsById = $poItems->keyBy('id');
            $postedMap = $this->postedReceivedMap((int) $lockedPurchaseOrder->id, (int) $lockedReceipt->id);

            $lockedReceipt->load([
                'items' => fn ($query) => $query->orderBy('line_order')->orderBy('id')->lockForUpdate(),
            ]);

            if ($lockedReceipt->items->isEmpty()) {
                throw ValidationException::withMessages([
                    'receipt' => 'A rececao nao contem linhas.',
                ]);
            }

            $hasPositiveQuantity = false;
            foreach ($lockedReceipt->items as $receiptItem) {
                $purchaseOrderItem = $poItemsById->get((int) $receiptItem->purchase_order_item_id);
                if (! $purchaseOrderItem) {
                    throw ValidationException::withMessages([
                        'receipt' => 'A rececao contem linhas invalidas para esta encomenda.',
                    ]);
                }

                $ordered = round((float) ($purchaseOrderItem->quantity ?? 0), 3);
                $previouslyReceived = round((float) ($postedMap[(int) $purchaseOrderItem->id] ?? 0), 3);
                $maximumReceivableNow = round(max(0.0, $ordered - $previouslyReceived), 3);
                $receivedNow = round((float) ($receiptItem->received_quantity ?? 0), 3);

                if ($receivedNow < -self::EPSILON) {
                    throw ValidationException::withMessages([
                        'receipt' => 'Existem quantidades negativas na rececao.',
                    ]);
                }

                if ($receivedNow > ($maximumReceivableNow + self::EPSILON)) {
                    throw ValidationException::withMessages([
                        'receipt' => 'A quantidade recebida excede a quantidade em falta na encomenda.',
                    ]);
                }

                if ($receivedNow > self::EPSILON) {
                    $hasPositiveQuantity = true;
                }

                $receiptItem->forceFill([
                    'line_order' => (int) ($purchaseOrderItem->line_order ?? $receiptItem->line_order),
                    'source_line_type' => $this->resolveSourceLineType($purchaseOrderItem),
                    'stock_resolution_status' => $this->resolveReceiptItemStockResolutionStatus(
                        purchaseOrderItem: $purchaseOrderItem,
                        receiptItem: $receiptItem
                    ),
                    'article_id' => $purchaseOrderItem->article_id,
                    'article_code' => $purchaseOrderItem->article_code,
                    'description' => (string) ($purchaseOrderItem->description ?: '-'),
                    'unit_name' => $purchaseOrderItem->unit_name,
                    'ordered_quantity' => $ordered,
                    'previously_received_quantity' => $previouslyReceived,
                    'received_quantity' => max(0.0, $receivedNow),
                ])->save();
            }

            if (! $hasPositiveQuantity) {
                throw ValidationException::withMessages([
                    'receipt' => 'A rececao deve ter pelo menos uma linha com quantidade recebida superior a zero.',
                ]);
            }

            $this->assertReceiptStockLinesResolved($lockedReceipt->items);

            $totalsAfterPost = $this->calculateTotalsAfterPosting($poItems, $postedMap, $lockedReceipt->items);
            $allComplete = $this->allItemsComplete($poItems, $totalsAfterPost);

            $lockedReceipt->forceFill([
                'status' => PurchaseOrderReceipt::STATUS_POSTED,
                'is_final' => $isFinal ?? $allComplete,
            ])->save();

            $lockedPurchaseOrder->forceFill([
                'status' => $allComplete
                    ? PurchaseOrder::STATUS_RECEIVED
                    : PurchaseOrder::STATUS_PARTIALLY_RECEIVED,
                'is_locked' => true,
            ])->save();

            $this->purchaseOrderReceiptStockService->integrateForPostedReceipt($lockedReceipt);

            return $lockedReceipt->fresh([
                'purchaseOrder',
                'receiver:id,name',
                'items' => fn ($query) => $query->orderBy('line_order')->orderBy('id'),
            ]);
        });
    }

    public function cancelDraft(PurchaseOrderReceipt $receipt): PurchaseOrderReceipt
    {
        return DB::transaction(function () use ($receipt): PurchaseOrderReceipt {
            /** @var PurchaseOrderReceipt $lockedReceipt */
            $lockedReceipt = PurchaseOrderReceipt::query()
                ->whereKey((int) $receipt->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedReceipt->canCancel()) {
                throw ValidationException::withMessages([
                    'receipt' => 'Apenas rececoes em rascunho podem ser canceladas.',
                ]);
            }

            $lockedReceipt->forceFill([
                'status' => PurchaseOrderReceipt::STATUS_CANCELLED,
            ])->save();

            return $lockedReceipt;
        });
    }

    public function assertPurchaseOrderCanReceive(PurchaseOrder $purchaseOrder): void
    {
        if (! $purchaseOrder->canReceiveMaterial()) {
            throw ValidationException::withMessages([
                'purchase_order' => 'A encomenda selecionada nao permite registo de rececao no estado atual.',
            ]);
        }
    }

    /**
     * @param Collection<int, PurchaseOrderItem> $purchaseOrderItems
     * @param array<int, float> $postedMap
     * @param Collection<int, PurchaseOrderReceiptItem> $receiptItems
     * @return array<int, float>
     */
    private function calculateTotalsAfterPosting(Collection $purchaseOrderItems, array $postedMap, Collection $receiptItems): array
    {
        $currentReceiptMap = $receiptItems
            ->groupBy('purchase_order_item_id')
            ->map(fn (Collection $items): float => round((float) $items->sum(fn (PurchaseOrderReceiptItem $item): float => (float) $item->received_quantity), 3));

        $totals = [];
        foreach ($purchaseOrderItems as $item) {
            $itemId = (int) $item->id;
            $totals[$itemId] = round((float) ($postedMap[$itemId] ?? 0) + (float) ($currentReceiptMap[$itemId] ?? 0), 3);
        }

        return $totals;
    }

    /**
     * @param Collection<int, PurchaseOrderItem> $purchaseOrderItems
     * @param array<int, float> $totalsByPurchaseOrderItem
     */
    private function allItemsComplete(Collection $purchaseOrderItems, array $totalsByPurchaseOrderItem): bool
    {
        foreach ($purchaseOrderItems as $item) {
            $ordered = round((float) ($item->quantity ?? 0), 3);
            $received = round((float) ($totalsByPurchaseOrderItem[(int) $item->id] ?? 0), 3);

            if ($ordered > ($received + self::EPSILON)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param Collection<int, PurchaseOrderItem> $purchaseOrderItems
     * @param array<int, array{received_quantity: float, notes: ?string}> $inputMap
     * @param array<int, float> $postedMap
     * @return array<int, array<string, mixed>>
     */
    private function buildReceiptItemsPayload(Collection $purchaseOrderItems, array $inputMap, array $postedMap): array
    {
        $payload = [];

        foreach ($purchaseOrderItems as $item) {
            $itemId = (int) $item->id;
            $ordered = round((float) ($item->quantity ?? 0), 3);
            $previouslyReceived = round((float) ($postedMap[$itemId] ?? 0), 3);
            $maximumReceivableNow = round(max(0.0, $ordered - $previouslyReceived), 3);
            $receivedNow = round((float) ($inputMap[$itemId]['received_quantity'] ?? 0), 3);

            if ($receivedNow < -self::EPSILON) {
                throw ValidationException::withMessages([
                    "items.$itemId.received_quantity" => 'A quantidade recebida nao pode ser negativa.',
                ]);
            }

            if ($receivedNow > ($maximumReceivableNow + self::EPSILON)) {
                throw ValidationException::withMessages([
                    "items.$itemId.received_quantity" => 'A quantidade recebida excede a quantidade em falta para esta linha.',
                ]);
            }

            $payload[] = [
                'company_id' => (int) $item->company_id,
                'purchase_order_item_id' => $itemId,
                'line_order' => (int) ($item->line_order ?? 1),
                'source_line_type' => $this->resolveSourceLineType($item),
                'stock_resolution_status' => $this->defaultReceiptStockResolutionStatus($item),
                'article_id' => $item->article_id,
                'article_code' => $item->article_code,
                'description' => (string) ($item->description ?: '-'),
                'unit_name' => $item->unit_name,
                'ordered_quantity' => $ordered,
                'previously_received_quantity' => $previouslyReceived,
                'received_quantity' => max(0.0, $receivedNow),
                'notes' => $inputMap[$itemId]['notes'] ?? null,
            ];
        }

        return $payload;
    }

    /**
     * @param array<int|string, mixed> $input
     * @return array<int, array{received_quantity: float, notes: ?string}>
     */
    private function normalizeItemsInput(array $input): array
    {
        $normalized = [];

        foreach ($input as $key => $line) {
            if (! is_array($line)) {
                continue;
            }

            $itemId = (int) $key;
            $explicitId = isset($line['purchase_order_item_id']) ? (int) $line['purchase_order_item_id'] : 0;
            if ($explicitId > 0) {
                $itemId = $explicitId;
            }

            if ($itemId <= 0) {
                continue;
            }

            $normalized[$itemId] = [
                'received_quantity' => round((float) ($line['received_quantity'] ?? 0), 3),
                'notes' => $this->normalizeNullableString($line['notes'] ?? null),
            ];
        }

        return $normalized;
    }

    /**
     * @return array<int, float>
     */
    private function postedReceivedMap(int $purchaseOrderId, ?int $excludeReceiptId = null): array
    {
        $query = PurchaseOrderReceiptItem::query()
            ->selectRaw('purchase_order_receipt_items.purchase_order_item_id, SUM(purchase_order_receipt_items.received_quantity) as total_received')
            ->join('purchase_order_receipts', 'purchase_order_receipts.id', '=', 'purchase_order_receipt_items.purchase_order_receipt_id')
            ->where('purchase_order_receipts.purchase_order_id', $purchaseOrderId)
            ->where('purchase_order_receipts.status', PurchaseOrderReceipt::STATUS_POSTED)
            ->groupBy('purchase_order_receipt_items.purchase_order_item_id');

        if ($excludeReceiptId !== null) {
            $query->where('purchase_order_receipts.id', '!=', $excludeReceiptId);
        }

        return $query
            ->pluck('total_received', 'purchase_order_receipt_items.purchase_order_item_id')
            ->map(fn ($value): float => round((float) $value, 3))
            ->all();
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }

    private function resolveSourceLineType(PurchaseOrderItem $item): string
    {
        $lineType = trim((string) ($item->line_type ?? ''));

        if ($lineType !== '') {
            return $lineType;
        }

        return $item->article_id !== null
            ? PurchaseOrderItem::LINE_TYPE_ARTICLE
            : PurchaseOrderItem::LINE_TYPE_TEXT;
    }

    private function defaultReceiptStockResolutionStatus(PurchaseOrderItem $item): string
    {
        $lineType = $this->resolveSourceLineType($item);

        if ($lineType === PurchaseOrderItem::LINE_TYPE_SECTION || $lineType === PurchaseOrderItem::LINE_TYPE_NOTE) {
            return PurchaseOrderReceiptItem::STOCK_RESOLUTION_NON_STOCKABLE;
        }

        if ($item->article_id !== null) {
            return PurchaseOrderReceiptItem::STOCK_RESOLUTION_RESOLVED_ARTICLE;
        }

        $currentStatus = trim((string) ($item->stock_resolution_status ?? ''));
        if (in_array($currentStatus, [
            PurchaseOrderItem::STOCK_RESOLUTION_PENDING,
            PurchaseOrderItem::STOCK_RESOLUTION_RESOLVED_ARTICLE,
            PurchaseOrderItem::STOCK_RESOLUTION_NON_STOCKABLE,
        ], true)) {
            return $currentStatus;
        }

        return PurchaseOrderReceiptItem::STOCK_RESOLUTION_PENDING;
    }

    private function resolveReceiptItemStockResolutionStatus(
        PurchaseOrderItem $purchaseOrderItem,
        PurchaseOrderReceiptItem $receiptItem
    ): string {
        if ($purchaseOrderItem->article_id !== null) {
            return PurchaseOrderReceiptItem::STOCK_RESOLUTION_RESOLVED_ARTICLE;
        }

        $lineType = $this->resolveSourceLineType($purchaseOrderItem);
        if ($lineType === PurchaseOrderItem::LINE_TYPE_SECTION || $lineType === PurchaseOrderItem::LINE_TYPE_NOTE) {
            return PurchaseOrderReceiptItem::STOCK_RESOLUTION_NON_STOCKABLE;
        }

        $currentStatus = trim((string) ($receiptItem->stock_resolution_status ?? ''));
        if (in_array($currentStatus, [
            PurchaseOrderReceiptItem::STOCK_RESOLUTION_PENDING,
            PurchaseOrderReceiptItem::STOCK_RESOLUTION_NON_STOCKABLE,
            PurchaseOrderReceiptItem::STOCK_RESOLUTION_RESOLVED_ARTICLE,
        ], true)) {
            return $currentStatus;
        }

        return $this->defaultReceiptStockResolutionStatus($purchaseOrderItem);
    }

    /**
     * @param Collection<int, PurchaseOrderReceiptItem> $items
     */
    private function assertReceiptStockLinesResolved(Collection $items): void
    {
        $pendingLines = $items
            ->filter(fn (PurchaseOrderReceiptItem $item): bool => $item->requiresStockResolutionDecision(self::EPSILON))
            ->map(fn (PurchaseOrderReceiptItem $item): string => '#'.(string) ($item->line_order ?? $item->id))
            ->values()
            ->all();

        if ($pendingLines === []) {
            return;
        }

        throw ValidationException::withMessages([
            'stock_resolution' => 'Existem linhas de texto sem resolucao de stock: '.implode(', ', $pendingLines).'. Resolva antes de confirmar a rececao.',
        ]);
    }
}
