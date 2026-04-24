<?php

namespace App\Services\Admin;

use App\Models\Article;
use App\Models\SalesDocument;
use App\Models\StockMovement;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class SalesDocumentStockService
{
    private const EPSILON = 0.0005;

    public function moveStockForIssuedDocument(SalesDocument $document, int $performedBy): void
    {
        if (! $document->shouldMoveStock()) {
            return;
        }

        $existingMovements = StockMovement::query()
            ->forCompany((int) $document->company_id)
            ->where('reference_type', StockMovement::REFERENCE_SALES_DOCUMENT)
            ->where('reference_id', (int) $document->id)
            ->exists();

        if ($existingMovements) {
            throw ValidationException::withMessages([
                'document' => 'Este documento ja possui movimentos de stock associados.',
            ]);
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

            $quantity = round((float) $item->quantity, 3);
            if ($quantity <= self::EPSILON) {
                continue;
            }

            if (! $article->hasSufficientStockFor($quantity)) {
                throw ValidationException::withMessages([
                    'document' => 'Stock insuficiente para o artigo '.$article->code.' - '.$article->designation.'.',
                ]);
            }
        }

        foreach ($document->items as $item) {
            $articleId = (int) ($item->article_id ?? 0);
            if ($articleId <= 0) {
                continue;
            }

            $article = $articlesById->get($articleId);
            if (! $article || ! $article->canMoveStock()) {
                continue;
            }

            $quantity = round((float) $item->quantity, 3);
            if ($quantity <= self::EPSILON) {
                continue;
            }

            StockMovement::query()->create([
                'company_id' => (int) $document->company_id,
                'article_id' => (int) $article->id,
                'type' => StockMovement::TYPE_SALE,
                'direction' => StockMovement::DIRECTION_OUT,
                'reason_code' => StockMovement::REASON_OTHER,
                'quantity' => $quantity,
                'unit_cost' => $article->cost_price !== null ? round((float) $article->cost_price, 4) : null,
                'reference_type' => StockMovement::REFERENCE_SALES_DOCUMENT,
                'reference_id' => (int) $document->id,
                'reference_line_id' => (int) $item->id,
                'movement_date' => $document->issue_date?->format('Y-m-d') ?? now()->toDateString(),
                'notes' => 'Saida por Documento de Venda '.$document->number,
                'performed_by' => $performedBy,
            ]);

            $article->decreaseStock($quantity);
        }
    }
}

