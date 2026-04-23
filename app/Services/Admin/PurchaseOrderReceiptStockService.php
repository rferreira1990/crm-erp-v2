<?php

namespace App\Services\Admin;

use App\Models\Article;
use App\Models\PurchaseOrderReceipt;
use App\Models\PurchaseOrderReceiptItem;
use App\Models\StockMovement;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class PurchaseOrderReceiptStockService
{
    private const EPSILON = 0.0005;

    public function integrateForPostedReceipt(PurchaseOrderReceipt $receipt): void
    {
        /** @var PurchaseOrderReceipt $lockedReceipt */
        $lockedReceipt = PurchaseOrderReceipt::query()
            ->whereKey((int) $receipt->id)
            ->lockForUpdate()
            ->firstOrFail();

        if ($lockedReceipt->status !== PurchaseOrderReceipt::STATUS_POSTED) {
            throw ValidationException::withMessages([
                'receipt' => 'A rececao tem de estar confirmada para integrar stock.',
            ]);
        }

        if ($lockedReceipt->stock_posted_at !== null) {
            return;
        }

        $existingStockMovements = StockMovement::query()
            ->forCompany((int) $lockedReceipt->company_id)
            ->where('reference_type', StockMovement::REFERENCE_PURCHASE_ORDER_RECEIPT)
            ->where('reference_id', (int) $lockedReceipt->id)
            ->exists();

        if ($existingStockMovements) {
            throw ValidationException::withMessages([
                'receipt' => 'A rececao ja possui movimentos de stock registados.',
            ]);
        }

        $lockedReceipt->load([
            'purchaseOrder:id,number',
            'items' => fn ($query) => $query
                ->orderBy('line_order')
                ->orderBy('id')
                ->with('purchaseOrderItem:id,unit_price'),
        ]);

        $articleIds = $lockedReceipt->items
            ->pluck('article_id')
            ->filter(fn ($id): bool => (int) $id > 0)
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        /** @var Collection<int, Article> $articlesById */
        $articlesById = Article::query()
            ->forCompany((int) $lockedReceipt->company_id)
            ->whereIn('id', $articleIds)
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        $stockDeltaByArticleId = [];
        foreach ($lockedReceipt->items as $item) {
            $this->createMovementForReceiptItem(
                receipt: $lockedReceipt,
                receiptItem: $item,
                articlesById: $articlesById,
                stockDeltaByArticleId: $stockDeltaByArticleId
            );
        }

        foreach ($stockDeltaByArticleId as $articleId => $delta) {
            $article = $articlesById->get((int) $articleId);
            if (! $article) {
                continue;
            }

            $article->increaseStock((float) $delta);
        }

        $lockedReceipt->forceFill([
            'stock_posted_at' => now(),
        ])->save();
    }

    /**
     * @param Collection<int, Article> $articlesById
     * @param array<int, float> $stockDeltaByArticleId
     */
    private function createMovementForReceiptItem(
        PurchaseOrderReceipt $receipt,
        PurchaseOrderReceiptItem $receiptItem,
        Collection $articlesById,
        array &$stockDeltaByArticleId
    ): void {
        $receivedQuantity = round((float) ($receiptItem->received_quantity ?? 0), 3);
        if ($receivedQuantity <= self::EPSILON) {
            return;
        }

        $articleId = (int) ($receiptItem->article_id ?? 0);
        if ($articleId <= 0) {
            return;
        }

        $article = $articlesById->get($articleId);
        if (! $article || ! $article->moves_stock) {
            return;
        }

        $unitCost = null;
        if ($receiptItem->purchaseOrderItem?->unit_price !== null) {
            $unitCost = round((float) $receiptItem->purchaseOrderItem->unit_price, 4);
        }

        $poNumber = trim((string) $receipt->purchaseOrder?->number);
        $notes = 'Entrada por rececao '.$receipt->number;
        if ($poNumber !== '') {
            $notes .= ' (PO '.$poNumber.')';
        }

        StockMovement::query()->create([
            'company_id' => (int) $receipt->company_id,
            'article_id' => $articleId,
            'type' => StockMovement::TYPE_PURCHASE_RECEIPT,
            'direction' => StockMovement::DIRECTION_IN,
            'quantity' => $receivedQuantity,
            'unit_cost' => $unitCost,
            'reference_type' => StockMovement::REFERENCE_PURCHASE_ORDER_RECEIPT,
            'reference_id' => (int) $receipt->id,
            'reference_line_id' => (int) $receiptItem->id,
            'movement_date' => $receipt->receipt_date?->format('Y-m-d') ?? now()->toDateString(),
            'notes' => $notes,
            'performed_by' => $receipt->received_by,
        ]);

        $stockDeltaByArticleId[$articleId] = round(
            (float) ($stockDeltaByArticleId[$articleId] ?? 0) + $receivedQuantity,
            3
        );
    }
}
