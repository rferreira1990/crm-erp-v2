<?php

namespace App\Services\Admin;

use App\Models\Article;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\Unit;
use App\Models\VatExemptionReason;
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
        $unitIds = collect($items)
            ->pluck('unit_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
        $reasonIds = collect($items)
            ->pluck('vat_exemption_reason_id')
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
        /** @var Collection<int, Unit> $units */
        $units = Unit::query()
            ->visibleToCompany($companyId)
            ->whereIn('id', $unitIds)
            ->get()
            ->keyBy('id');
        /** @var Collection<int, VatExemptionReason> $reasons */
        $reasons = VatExemptionReason::query()
            ->visibleToCompany($companyId)
            ->whereIn('id', $reasonIds)
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
            $unit = $unitId !== null ? $units->get($unitId) : null;
            $reason = $reasonId !== null ? $reasons->get($reasonId) : null;

            $quote->items()->create([
                'company_id' => $companyId,
                'sort_order' => (int) ($item['sort_order'] ?? ($index + 1)),
                'line_type' => $lineType,
                'article_id' => $article?->id,
                'article_code' => $article?->code,
                'article_designation' => $article?->designation,
                'description' => $description !== '' ? $description : '-',
                'internal_description' => $item['internal_description'] ?? null,
                'quantity' => $quantity,
                'unit_id' => $unitId,
                'unit_code' => $unit?->code,
                'unit_name' => $unit?->name,
                'unit_price' => $unitPrice,
                'discount_percent' => $discountPercent,
                'vat_rate_id' => $vatRateId,
                'vat_rate_name' => $vatRate?->name,
                'vat_rate_percentage' => $vatRate?->rate,
                'vat_exemption_reason_id' => $reasonId,
                'vat_exemption_reason_code' => $reason?->code,
                'vat_exemption_reason_name' => $reason?->name,
                'subtotal' => $amounts['subtotal'],
                'discount_amount' => $amounts['discount_amount'],
                'tax_amount' => $amounts['tax_amount'],
                'total' => $amounts['total'],
                'metadata' => is_array($item['metadata'] ?? null) ? $item['metadata'] : null,
            ]);
        }
    }

    public function refreshSnapshots(Quote $quote, int $companyId, bool $force = false): void
    {
        if (! $force && $quote->status !== Quote::STATUS_DRAFT) {
            return;
        }

        $quote->load([
            'items' => fn ($query) => $query
                ->orderBy('sort_order')
                ->orderBy('id'),
        ]);

        $articleIds = $quote->items->pluck('article_id')->filter()->map(fn ($id) => (int) $id)->unique()->values()->all();
        $unitIds = $quote->items->pluck('unit_id')->filter()->map(fn ($id) => (int) $id)->unique()->values()->all();
        $vatIds = $quote->items->pluck('vat_rate_id')->filter()->map(fn ($id) => (int) $id)->unique()->values()->all();
        $reasonIds = $quote->items->pluck('vat_exemption_reason_id')->filter()->map(fn ($id) => (int) $id)->unique()->values()->all();

        $articles = Article::query()->forCompany($companyId)->whereIn('id', $articleIds)->get()->keyBy('id');
        $units = Unit::query()->visibleToCompany($companyId)->whereIn('id', $unitIds)->get()->keyBy('id');
        $vatRates = VatRate::query()->visibleToCompany($companyId)->whereIn('id', $vatIds)->get()->keyBy('id');
        $reasons = VatExemptionReason::query()->visibleToCompany($companyId)->whereIn('id', $reasonIds)->get()->keyBy('id');

        foreach ($quote->items as $item) {
            $article = $item->article_id ? $articles->get((int) $item->article_id) : null;
            $unit = $item->unit_id ? $units->get((int) $item->unit_id) : null;
            $vatRate = $item->vat_rate_id ? $vatRates->get((int) $item->vat_rate_id) : null;
            $reason = $item->vat_exemption_reason_id ? $reasons->get((int) $item->vat_exemption_reason_id) : null;

            $item->forceFill([
                'article_code' => $article?->code ?? $item->article_code,
                'article_designation' => $article?->designation ?? $item->article_designation,
                'unit_code' => $unit?->code ?? $item->unit_code,
                'unit_name' => $unit?->name ?? $item->unit_name,
                'vat_rate_name' => $vatRate?->name ?? $item->vat_rate_name,
                'vat_rate_percentage' => $vatRate?->rate ?? $item->vat_rate_percentage,
                'vat_exemption_reason_code' => $reason?->code ?? $item->vat_exemption_reason_code,
                'vat_exemption_reason_name' => $reason?->name ?? $item->vat_exemption_reason_name,
            ])->save();
        }
    }

    public function syncSnapshots(Quote $quote, int $companyId, bool $force = false): void
    {
        $this->refreshSnapshots($quote, $companyId, $force);
        $quote->syncHeaderSnapshot($force);
    }
}
