<?php

namespace App\Services\Admin;

use App\Models\Article;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\VatRate;
use Illuminate\Support\Collection;

class QuoteItemsSyncService
{
    /**
     * @param array<int, array<string, mixed>> $items
     */
    public function sync(Quote $quote, array $items, int $companyId): void
    {
        $quote->items()->delete();
        $quote->loadMissing('priceTier');

        $articleIds = collect($items)
            ->pluck('article_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $vatIds = collect($items)
            ->pluck('vat_rate_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        /** @var Collection<int, Article> $articles */
        $articles = Article::query()
            ->forCompany($companyId)
            ->whereIn('id', $articleIds)
            ->get()
            ->keyBy('id');

        /** @var Collection<int, VatRate> $vatRates */
        $vatRates = VatRate::query()
            ->with([
                'companyOverrides' => fn ($query) => $query->where('company_id', $companyId),
            ])
            ->visibleToCompany($companyId)
            ->whereIn('id', $vatIds)
            ->get()
            ->keyBy('id');

        foreach ($items as $index => $item) {
            $lineType = (string) ($item['line_type'] ?? QuoteItem::TYPE_ARTICLE);
            $articleId = isset($item['article_id']) ? (int) $item['article_id'] : null;
            $article = $articleId !== null ? $articles->get($articleId) : null;

            $description = trim((string) ($item['description'] ?? ''));
            if ($description === '' && $article) {
                $description = (string) $article->designation;
            }

            $quantity = isset($item['quantity']) ? (float) $item['quantity'] : 1.0;
            $unitPriceInput = $item['unit_price'] ?? null;
            $unitPrice = $unitPriceInput !== null ? (float) $unitPriceInput : 0.0;

            if ($article && $unitPriceInput === null && $article->sale_price !== null) {
                $unitPrice = (float) $article->sale_price;

                if ($quote->priceTier) {
                    $unitPrice = $quote->priceTier->applyToAmount($unitPrice);
                }
            }

            $discountPercent = isset($item['discount_percent']) ? (float) $item['discount_percent'] : 0.0;
            if ($discountPercent < 0) {
                $discountPercent = 0.0;
            }

            $vatRateId = isset($item['vat_rate_id']) ? (int) $item['vat_rate_id'] : null;
            if ($vatRateId === null && $article?->vat_rate_id !== null) {
                $vatRateId = (int) $article->vat_rate_id;
            }
            if ($vatRateId === null && $quote->default_vat_rate_id !== null) {
                $vatRateId = (int) $quote->default_vat_rate_id;
            }

            $vatRate = $vatRateId !== null ? $vatRates->get($vatRateId) : null;
            $vatPercent = $vatRate ? (float) $vatRate->rate : 0.0;
            $isExempt = $vatRate ? (bool) $vatRate->is_exempt : false;

            $reasonId = isset($item['vat_exemption_reason_id']) ? (int) $item['vat_exemption_reason_id'] : null;
            if (! $isExempt) {
                $reasonId = null;
            }

            $unitId = isset($item['unit_id']) ? (int) $item['unit_id'] : null;
            if ($unitId === null && $article?->unit_id !== null) {
                $unitId = (int) $article->unit_id;
            }

            if (in_array($lineType, [QuoteItem::TYPE_SECTION, QuoteItem::TYPE_NOTE], true)) {
                $quantity = 1;
                $unitPrice = 0;
                $discountPercent = 0;
                $vatRateId = null;
                $reasonId = null;
                $vatPercent = 0;
                $isExempt = false;
                $unitId = null;
            }

            $amounts = QuoteItem::calculateAmounts($quantity, $unitPrice, $discountPercent, $vatPercent, $isExempt);

            $quote->items()->create([
                'company_id' => $companyId,
                'sort_order' => (int) ($item['sort_order'] ?? ($index + 1)),
                'line_type' => $lineType,
                'article_id' => $article?->id,
                'description' => $description !== '' ? $description : '-',
                'internal_description' => $item['internal_description'] ?? null,
                'quantity' => $quantity,
                'unit_id' => $unitId,
                'unit_price' => $unitPrice,
                'discount_percent' => $discountPercent,
                'vat_rate_id' => $vatRateId,
                'vat_exemption_reason_id' => $reasonId,
                'subtotal' => $amounts['subtotal'],
                'discount_amount' => $amounts['discount_amount'],
                'tax_amount' => $amounts['tax_amount'],
                'total' => $amounts['total'],
                'metadata' => is_array($item['metadata'] ?? null) ? $item['metadata'] : null,
            ]);
        }
    }
}

