<?php

namespace App\Services\Admin;

use App\Models\Article;
use App\Models\PurchaseDocument;
use App\Models\PurchaseOrderReceipt;
use App\Models\StockMovement;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class PurchaseDocumentStockService
{
    private const EPSILON = 0.0005;

    public function moveStockForConfirmedDocument(PurchaseDocument $document, int $performedBy): void
    {
        if (! $document->isConfirmed()) {
            throw ValidationException::withMessages([
                'purchase_document' => 'O documento tem de estar confirmado para integrar stock.',
            ]);
        }

        $existingMovements = StockMovement::query()
            ->forCompany((int) $document->company_id)
            ->where('reference_type', StockMovement::REFERENCE_PURCHASE_DOCUMENT)
            ->where('reference_id', (int) $document->id)
            ->exists();

        if ($existingMovements) {
            throw ValidationException::withMessages([
                'purchase_document' => 'O documento ja possui movimentos de stock associados.',
            ]);
        }

        if ($document->purchase_order_id !== null) {
            $hasPostedReceipts = PurchaseOrderReceipt::query()
                ->forCompany((int) $document->company_id)
                ->where('purchase_order_id', (int) $document->purchase_order_id)
                ->where('status', PurchaseOrderReceipt::STATUS_POSTED)
                ->exists();

            if ($hasPostedReceipts) {
                throw ValidationException::withMessages([
                    'purchase_document' => 'A encomenda associada ja tem rececoes confirmadas. Nao e possivel integrar stock pelo Documento de Compra.',
                ]);
            }
        }

        $document->loadMissing([
            'items' => fn ($query) => $query
                ->orderBy('line_order')
                ->orderBy('id'),
        ]);

        $articleIds = $document->items
            ->pluck('article_id')
            ->filter(fn ($id): bool => (int) $id > 0)
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        /** @var Collection<int, Article> $articlesById */
        $articlesById = Article::query()
            ->forCompany((int) $document->company_id)
            ->whereIn('id', $articleIds)
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        $stockDeltaByArticleId = [];
        $latestUnitCostByArticleId = [];

        foreach ($document->items as $item) {
            $articleId = (int) ($item->article_id ?? 0);
            if ($articleId <= 0) {
                continue;
            }

            $article = $articlesById->get($articleId);
            if (! $article) {
                abort(404);
            }

            if (! $article->canMoveStock()) {
                continue;
            }

            $quantity = round((float) ($item->quantity ?? 0), 3);
            if ($quantity <= self::EPSILON) {
                continue;
            }

            $unitCost = round((float) ($item->unit_price ?? 0), 4);

            StockMovement::query()->create([
                'company_id' => (int) $document->company_id,
                'article_id' => (int) $article->id,
                'type' => StockMovement::TYPE_PURCHASE_RECEIPT,
                'direction' => StockMovement::DIRECTION_IN,
                'reason_code' => StockMovement::REASON_OTHER,
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
                'reference_type' => StockMovement::REFERENCE_PURCHASE_DOCUMENT,
                'reference_id' => (int) $document->id,
                'reference_line_id' => (int) $item->id,
                'movement_date' => $document->issue_date?->format('Y-m-d') ?? now()->toDateString(),
                'notes' => 'Entrada por Documento de Compra '.$document->number,
                'performed_by' => $performedBy,
            ]);

            $stockDeltaByArticleId[$articleId] = round(
                (float) ($stockDeltaByArticleId[$articleId] ?? 0) + $quantity,
                3
            );

            $latestUnitCostByArticleId[$articleId] = $unitCost;
        }

        foreach ($stockDeltaByArticleId as $articleId => $delta) {
            $article = $articlesById->get((int) $articleId);
            if (! $article) {
                continue;
            }

            $latestUnitCost = $latestUnitCostByArticleId[(int) $articleId] ?? null;
            if ($latestUnitCost !== null) {
                $article->forceFill([
                    'cost_price' => round((float) $latestUnitCost, 4),
                ])->save();
            }

            $article->increaseStock((float) $delta);
        }
    }
}
