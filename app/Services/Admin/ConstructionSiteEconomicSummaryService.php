<?php

namespace App\Services\Admin;

use App\Models\ConstructionSite;
use App\Models\ConstructionSiteMaterialUsage;
use App\Models\ConstructionSiteMaterialUsageItem;
use App\Models\ConstructionSiteTimeEntry;
use App\Models\Quote;

class ConstructionSiteEconomicSummaryService
{
    private const BUDGET_EPSILON = 0.00005;

    public const BUDGET_STATUS_NO_QUOTE = 'no_quote';
    public const BUDGET_STATUS_UNDER_BUDGET = 'under_budget';
    public const BUDGET_STATUS_ON_BUDGET = 'on_budget';
    public const BUDGET_STATUS_OVER_BUDGET = 'over_budget';

    /**
     * @return array{
     *   has_quote:bool,
     *   quote_value:?float,
     *   quote_status_label:?string,
     *   material_cost:float,
     *   labor_cost:float,
     *   total_cost:float,
     *   budget_consumption_percent:?float,
     *   budget_status:string,
     *   budget_status_label:string,
     *   budget_status_badge_class:string,
     *   estimated_margin:?float,
     *   deviation_amount:?float,
     *   deviation_percent:?float
     * }
     */
    public function build(int $companyId, ConstructionSite $constructionSite): array
    {
        $quoteValue = $this->resolveQuoteValue($companyId, $constructionSite->quote_id);
        $materialCost = $this->resolvePostedMaterialCost($companyId, (int) $constructionSite->id);
        $laborCost = $this->resolveLaborCost($companyId, (int) $constructionSite->id);
        $totalCost = round($materialCost + $laborCost, 4);
        $budgetStatus = $this->resolveBudgetStatus($quoteValue, $totalCost);
        $budgetConsumptionPercent = $this->resolveBudgetConsumptionPercent($quoteValue, $totalCost);

        $estimatedMargin = $quoteValue !== null
            ? round($quoteValue - $totalCost, 4)
            : null;

        $deviationAmount = $quoteValue !== null
            ? round($totalCost - $quoteValue, 4)
            : null;

        $deviationPercent = null;
        if ($deviationAmount !== null && $quoteValue !== null && abs($quoteValue) > self::BUDGET_EPSILON) {
            $deviationPercent = round(($deviationAmount / $quoteValue) * 100, 4);
        }

        return [
            'has_quote' => $quoteValue !== null,
            'quote_value' => $quoteValue,
            'quote_status_label' => $this->resolveQuoteStatusLabel($companyId, $constructionSite->quote_id),
            'material_cost' => $materialCost,
            'labor_cost' => $laborCost,
            'total_cost' => $totalCost,
            'budget_consumption_percent' => $budgetConsumptionPercent,
            'budget_status' => $budgetStatus,
            'budget_status_label' => $this->budgetStatusLabel($budgetStatus),
            'budget_status_badge_class' => $this->budgetStatusBadgeClass($budgetStatus),
            'estimated_margin' => $estimatedMargin,
            'deviation_amount' => $deviationAmount,
            'deviation_percent' => $deviationPercent,
        ];
    }

    private function resolveBudgetConsumptionPercent(?float $quoteValue, float $totalCost): ?float
    {
        if ($quoteValue === null || $quoteValue <= self::BUDGET_EPSILON) {
            return null;
        }

        return round(($totalCost / $quoteValue) * 100, 4);
    }

    private function resolveBudgetStatus(?float $quoteValue, float $totalCost): string
    {
        if ($quoteValue === null) {
            return self::BUDGET_STATUS_NO_QUOTE;
        }

        $delta = round($totalCost - $quoteValue, 4);

        if (abs($delta) <= self::BUDGET_EPSILON) {
            return self::BUDGET_STATUS_ON_BUDGET;
        }

        if ($delta > self::BUDGET_EPSILON) {
            return self::BUDGET_STATUS_OVER_BUDGET;
        }

        return self::BUDGET_STATUS_UNDER_BUDGET;
    }

    private function budgetStatusLabel(string $status): string
    {
        return match ($status) {
            self::BUDGET_STATUS_NO_QUOTE => 'Sem orcamento',
            self::BUDGET_STATUS_UNDER_BUDGET => 'Abaixo do orcamento',
            self::BUDGET_STATUS_ON_BUDGET => 'No limite do orcamento',
            self::BUDGET_STATUS_OVER_BUDGET => 'Acima do orcamento',
            default => $status,
        };
    }

    private function budgetStatusBadgeClass(string $status): string
    {
        return match ($status) {
            self::BUDGET_STATUS_NO_QUOTE => 'badge-phoenix-secondary',
            self::BUDGET_STATUS_UNDER_BUDGET => 'badge-phoenix-success',
            self::BUDGET_STATUS_ON_BUDGET => 'badge-phoenix-warning',
            self::BUDGET_STATUS_OVER_BUDGET => 'badge-phoenix-danger',
            default => 'badge-phoenix-secondary',
        };
    }

    private function resolveQuoteValue(int $companyId, ?int $quoteId): ?float
    {
        if ($quoteId === null) {
            return null;
        }

        $raw = Quote::query()
            ->forCompany($companyId)
            ->whereKey($quoteId)
            ->value('grand_total');

        return $raw !== null ? round((float) $raw, 4) : null;
    }

    private function resolveQuoteStatusLabel(int $companyId, ?int $quoteId): ?string
    {
        if ($quoteId === null) {
            return null;
        }

        $status = Quote::query()
            ->forCompany($companyId)
            ->whereKey($quoteId)
            ->value('status');

        if (! is_string($status) || $status === '') {
            return null;
        }

        return Quote::statusLabels()[$status] ?? $status;
    }

    private function resolvePostedMaterialCost(int $companyId, int $constructionSiteId): float
    {
        $raw = ConstructionSiteMaterialUsageItem::query()
            ->selectRaw('COALESCE(SUM(construction_site_material_usage_items.quantity * COALESCE(construction_site_material_usage_items.unit_cost, 0)), 0) as total')
            ->join(
                'construction_site_material_usages',
                'construction_site_material_usages.id',
                '=',
                'construction_site_material_usage_items.construction_site_material_usage_id'
            )
            ->where('construction_site_material_usage_items.company_id', $companyId)
            ->where('construction_site_material_usages.company_id', $companyId)
            ->where('construction_site_material_usages.construction_site_id', $constructionSiteId)
            ->where('construction_site_material_usages.status', ConstructionSiteMaterialUsage::STATUS_POSTED)
            ->value('total');

        return round((float) ($raw ?? 0), 4);
    }

    private function resolveLaborCost(int $companyId, int $constructionSiteId): float
    {
        return round((float) (
            ConstructionSiteTimeEntry::query()
                ->forCompany($companyId)
                ->where('construction_site_id', $constructionSiteId)
                ->sum('total_cost')
        ), 4);
    }
}
