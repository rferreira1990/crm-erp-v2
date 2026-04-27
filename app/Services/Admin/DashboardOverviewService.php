<?php

namespace App\Services\Admin;

use App\Models\Article;
use App\Models\ConstructionSite;
use App\Models\ConstructionSiteMaterialUsage;
use App\Models\ConstructionSiteMaterialUsageItem;
use App\Models\ConstructionSiteTimeEntry;
use App\Models\Customer;
use App\Models\PurchaseOrder;
use App\Models\Quote;
use App\Models\SalesDocument;
use App\Models\SalesDocumentReceipt;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class DashboardOverviewService
{
    private const FILTER_PERIOD_THIS_MONTH = 'this_month';
    private const FILTER_PERIOD_LAST_30_DAYS = 'last_30_days';
    private const FILTER_PERIOD_THIS_YEAR = 'this_year';
    private const FILTER_PERIOD_CUSTOM = 'custom';

    private const QUOTE_NO_RESPONSE_DAYS = 7;

    /**
     * @param array<string, mixed> $filters
     * @return array{
     *   filters: array<string, mixed>,
     *   options: array<string, mixed>,
     *   kpis: array<string, mixed>,
     *   charts: array<string, mixed>,
     *   recent: array<string, mixed>,
     *   alerts: array<string, mixed>
     * }
     */
    public function build(int $companyId, array $filters, bool $canViewMargins): array
    {
        $resolvedFilters = $this->resolveFilters($filters);
        $dateFrom = $resolvedFilters['effective_date_from'];
        $dateTo = $resolvedFilters['effective_date_to'];
        $referenceDate = $dateTo ? Carbon::parse($dateTo) : now();

        $activeConstructionSiteStatuses = [
            ConstructionSite::STATUS_PLANNED,
            ConstructionSite::STATUS_IN_PROGRESS,
            ConstructionSite::STATUS_ON_HOLD,
        ];

        $monthStart = $referenceDate->copy()->startOfMonth();
        $monthEnd = $referenceDate->copy()->endOfMonth();
        $yearStart = $referenceDate->copy()->startOfYear();
        $yearEnd = $referenceDate->copy()->endOfYear();

        $issuedDocumentsQuery = SalesDocument::query()
            ->forCompany($companyId)
            ->where('status', SalesDocument::STATUS_ISSUED);

        $this->applyCommonFilters(
            query: $issuedDocumentsQuery,
            filters: $resolvedFilters,
            dateColumn: 'issue_date',
            customerColumn: 'customer_id',
            responsibleColumn: 'created_by'
        );

        $soldThisMonth = $this->sumIssuedSalesWithinWindow(
            companyId: $companyId,
            filters: $resolvedFilters,
            windowFrom: $monthStart,
            windowTo: $monthEnd
        );

        $soldThisYear = $this->sumIssuedSalesWithinWindow(
            companyId: $companyId,
            filters: $resolvedFilters,
            windowFrom: $yearStart,
            windowTo: $yearEnd
        );

        $quotesOpenCountQuery = Quote::query()
            ->forCompany($companyId)
            ->whereIn('status', [
                Quote::STATUS_DRAFT,
                Quote::STATUS_SENT,
                Quote::STATUS_VIEWED,
            ]);

        $this->applyCommonFilters(
            query: $quotesOpenCountQuery,
            filters: $resolvedFilters,
            dateColumn: 'issue_date',
            customerColumn: 'customer_id',
            responsibleColumn: 'assigned_user_id'
        );

        $quotesApprovedQuery = Quote::query()
            ->forCompany($companyId)
            ->where('status', Quote::STATUS_APPROVED);

        $this->applyCommonFilters(
            query: $quotesApprovedQuery,
            filters: $resolvedFilters,
            dateColumn: 'issue_date',
            customerColumn: 'customer_id',
            responsibleColumn: 'assigned_user_id'
        );

        $quotesDecidedQuery = Quote::query()
            ->forCompany($companyId)
            ->whereIn('status', [
                Quote::STATUS_APPROVED,
                Quote::STATUS_REJECTED,
                Quote::STATUS_EXPIRED,
                Quote::STATUS_CANCELLED,
            ]);

        $this->applyCommonFilters(
            query: $quotesDecidedQuery,
            filters: $resolvedFilters,
            dateColumn: 'issue_date',
            customerColumn: 'customer_id',
            responsibleColumn: 'assigned_user_id'
        );

        $quotesOpenCount = (int) $quotesOpenCountQuery->count();
        $quotesApprovedCount = (int) $quotesApprovedQuery->count();
        $quotesDecidedCount = (int) $quotesDecidedQuery->count();

        $quoteConversionRate = $quotesDecidedCount > 0
            ? round(($quotesApprovedCount / $quotesDecidedCount) * 100, 2)
            : 0.0;

        $activeSitesQuery = ConstructionSite::query()
            ->forCompany($companyId)
            ->whereIn('status', $activeConstructionSiteStatuses);

        $this->applyCommonFilters(
            query: $activeSitesQuery,
            filters: $resolvedFilters,
            dateColumn: 'created_at',
            customerColumn: 'customer_id',
            responsibleColumn: 'assigned_user_id'
        );

        $activeSitesCount = (int) $activeSitesQuery->count();

        $openValue = $this->resolveOpenValue(
            companyId: $companyId,
            filters: $resolvedFilters
        );

        $totalReceivedThisMonth = $this->sumIssuedReceiptsWithinWindow(
            companyId: $companyId,
            filters: $resolvedFilters,
            windowFrom: $monthStart,
            windowTo: $monthEnd
        );

        $documentsPendingPaymentQuery = SalesDocument::query()
            ->forCompany($companyId)
            ->where('status', SalesDocument::STATUS_ISSUED)
            ->where(function (Builder $query): void {
                $query->whereNull('payment_status')
                    ->orWhere('payment_status', '!=', SalesDocument::PAYMENT_STATUS_PAID);
            });

        $this->applyCommonFilters(
            query: $documentsPendingPaymentQuery,
            filters: $resolvedFilters,
            dateColumn: 'issue_date',
            customerColumn: 'customer_id',
            responsibleColumn: 'created_by'
        );

        $documentsPendingPayment = (int) $documentsPendingPaymentQuery->count();

        $marginEstimatedWorks = null;
        if ($canViewMargins) {
            $marginEstimatedWorks = $this->resolveEstimatedWorksMargin(
                companyId: $companyId,
                filters: $resolvedFilters,
                activeStatuses: $activeConstructionSiteStatuses
            );
        }

        $recentSalesDocuments = SalesDocument::query()
            ->forCompany($companyId)
            ->with(['customer:id,name'])
            ->when($resolvedFilters['customer_id'] !== null, fn (Builder $query) => $query->where('customer_id', $resolvedFilters['customer_id']))
            ->when($resolvedFilters['responsible_id'] !== null, fn (Builder $query) => $query->where('created_by', $resolvedFilters['responsible_id']))
            ->when($dateFrom !== null, fn (Builder $query) => $query->whereDate('issue_date', '>=', $dateFrom))
            ->when($dateTo !== null, fn (Builder $query) => $query->whereDate('issue_date', '<=', $dateTo))
            ->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->limit(5)
            ->get([
                'id',
                'company_id',
                'number',
                'customer_id',
                'status',
                'payment_status',
                'issue_date',
                'grand_total',
                'currency',
            ]);

        $recentQuotes = Quote::query()
            ->forCompany($companyId)
            ->with(['customer:id,name'])
            ->when($resolvedFilters['customer_id'] !== null, fn (Builder $query) => $query->where('customer_id', $resolvedFilters['customer_id']))
            ->when($resolvedFilters['responsible_id'] !== null, fn (Builder $query) => $query->where('assigned_user_id', $resolvedFilters['responsible_id']))
            ->when($dateFrom !== null, fn (Builder $query) => $query->whereDate('issue_date', '>=', $dateFrom))
            ->when($dateTo !== null, fn (Builder $query) => $query->whereDate('issue_date', '<=', $dateTo))
            ->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->limit(5)
            ->get([
                'id',
                'company_id',
                'number',
                'customer_id',
                'status',
                'issue_date',
                'grand_total',
                'currency',
            ]);

        $recentConstructionSites = ConstructionSite::query()
            ->forCompany($companyId)
            ->with([
                'customer:id,name',
                'assignedUser:id,name',
            ])
            ->when($resolvedFilters['customer_id'] !== null, fn (Builder $query) => $query->where('customer_id', $resolvedFilters['customer_id']))
            ->when($resolvedFilters['responsible_id'] !== null, fn (Builder $query) => $query->where('assigned_user_id', $resolvedFilters['responsible_id']))
            ->when($dateFrom !== null, fn (Builder $query) => $query->whereDate('created_at', '>=', $dateFrom))
            ->when($dateTo !== null, fn (Builder $query) => $query->whereDate('created_at', '<=', $dateTo))
            ->orderByDesc('id')
            ->limit(5)
            ->get([
                'id',
                'company_id',
                'code',
                'name',
                'customer_id',
                'assigned_user_id',
                'quote_id',
                'status',
                'created_at',
            ]);

        $recentSitesEstimatedCosts = [];
        if ($canViewMargins && $recentConstructionSites->isNotEmpty()) {
            $recentSiteIds = $recentConstructionSites->pluck('id')->map(fn ($id): int => (int) $id)->all();
            $recentSitesEstimatedCosts = $this->resolveEstimatedCostsBySite($companyId, $recentSiteIds);
        }

        $recentReceipts = SalesDocumentReceipt::query()
            ->forCompany($companyId)
            ->with([
                'customer:id,name',
                'salesDocument:id,number',
            ])
            ->when($resolvedFilters['customer_id'] !== null, fn (Builder $query) => $query->where('customer_id', $resolvedFilters['customer_id']))
            ->when($resolvedFilters['responsible_id'] !== null, fn (Builder $query) => $query->where('created_by', $resolvedFilters['responsible_id']))
            ->when($dateFrom !== null, fn (Builder $query) => $query->whereDate('receipt_date', '>=', $dateFrom))
            ->when($dateTo !== null, fn (Builder $query) => $query->whereDate('receipt_date', '<=', $dateTo))
            ->orderByDesc('receipt_date')
            ->orderByDesc('id')
            ->limit(5)
            ->get([
                'id',
                'company_id',
                'number',
                'sales_document_id',
                'customer_id',
                'status',
                'receipt_date',
                'amount',
            ]);

        $salesByMonth = $this->buildMonthlySalesSeries($companyId, $resolvedFilters);
        $receiptsByMonth = $this->buildMonthlyReceiptsSeries($companyId, $resolvedFilters);

        $quotesByStatus = Quote::query()
            ->forCompany($companyId)
            ->when($resolvedFilters['customer_id'] !== null, fn (Builder $query) => $query->where('customer_id', $resolvedFilters['customer_id']))
            ->when($resolvedFilters['responsible_id'] !== null, fn (Builder $query) => $query->where('assigned_user_id', $resolvedFilters['responsible_id']))
            ->when($dateFrom !== null, fn (Builder $query) => $query->whereDate('issue_date', '>=', $dateFrom))
            ->when($dateTo !== null, fn (Builder $query) => $query->whereDate('issue_date', '<=', $dateTo))
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $worksByStatus = ConstructionSite::query()
            ->forCompany($companyId)
            ->when($resolvedFilters['customer_id'] !== null, fn (Builder $query) => $query->where('customer_id', $resolvedFilters['customer_id']))
            ->when($resolvedFilters['responsible_id'] !== null, fn (Builder $query) => $query->where('assigned_user_id', $resolvedFilters['responsible_id']))
            ->when($dateFrom !== null, fn (Builder $query) => $query->whereDate('created_at', '>=', $dateFrom))
            ->when($dateTo !== null, fn (Builder $query) => $query->whereDate('created_at', '<=', $dateTo))
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $today = now()->toDateString();

        $overdueDocumentsCount = SalesDocument::query()
            ->forCompany($companyId)
            ->where('status', SalesDocument::STATUS_ISSUED)
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', $today)
            ->where(function (Builder $query): void {
                $query->whereNull('payment_status')
                    ->orWhere('payment_status', '!=', SalesDocument::PAYMENT_STATUS_PAID);
            })
            ->when($resolvedFilters['customer_id'] !== null, fn (Builder $query) => $query->where('customer_id', $resolvedFilters['customer_id']))
            ->when($resolvedFilters['responsible_id'] !== null, fn (Builder $query) => $query->where('created_by', $resolvedFilters['responsible_id']))
            ->count();

        $quotesNoResponseCount = Quote::query()
            ->forCompany($companyId)
            ->whereIn('status', [
                Quote::STATUS_SENT,
                Quote::STATUS_VIEWED,
            ])
            ->whereDate('issue_date', '<=', now()->subDays(self::QUOTE_NO_RESPONSE_DAYS)->toDateString())
            ->when($resolvedFilters['customer_id'] !== null, fn (Builder $query) => $query->where('customer_id', $resolvedFilters['customer_id']))
            ->when($resolvedFilters['responsible_id'] !== null, fn (Builder $query) => $query->where('assigned_user_id', $resolvedFilters['responsible_id']))
            ->count();

        $lowStockCount = Article::query()
            ->forCompany($companyId)
            ->where('is_active', true)
            ->where('moves_stock', true)
            ->where('stock_alert_enabled', true)
            ->whereNotNull('minimum_stock')
            ->whereColumn('stock_quantity', '<=', 'minimum_stock')
            ->count();

        $pendingPoReceiptsCount = PurchaseOrder::query()
            ->forCompany($companyId)
            ->whereIn('status', PurchaseOrder::receivableStatuses())
            ->count();

        $worksOverBudgetCount = null;
        if ($canViewMargins) {
            $worksOverBudgetCount = $this->resolveWorksOverBudgetCount($companyId, $activeConstructionSiteStatuses);
        }

        return [
            'filters' => $resolvedFilters,
            'options' => [
                'customers' => Customer::query()
                    ->forCompany($companyId)
                    ->orderBy('name')
                    ->get(['id', 'name']),
                'responsibles' => User::query()
                    ->where('company_id', $companyId)
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get(['id', 'name']),
                'period_options' => [
                    self::FILTER_PERIOD_THIS_MONTH => 'Este mes',
                    self::FILTER_PERIOD_LAST_30_DAYS => 'Ultimos 30 dias',
                    self::FILTER_PERIOD_THIS_YEAR => 'Este ano',
                    self::FILTER_PERIOD_CUSTOM => 'Personalizado',
                ],
            ],
            'kpis' => [
                'sold_month' => $soldThisMonth,
                'sold_year' => $soldThisYear,
                'quotes_open' => $quotesOpenCount,
                'quote_conversion_rate' => $quoteConversionRate,
                'active_construction_sites' => $activeSitesCount,
                'open_customer_value' => $openValue,
                'received_month' => $totalReceivedThisMonth,
                'documents_pending_payment' => $documentsPendingPayment,
                'estimated_works_margin' => $marginEstimatedWorks,
            ],
            'charts' => [
                'sales_by_month' => $salesByMonth,
                'quotes_by_status' => [
                    'labels' => array_values(Quote::statusLabels()),
                    'values' => collect(Quote::statusLabels())
                        ->map(fn (string $label, string $status): int => (int) ($quotesByStatus[$status] ?? 0))
                        ->values()
                        ->all(),
                ],
                'works_by_status' => [
                    'labels' => array_values(ConstructionSite::statusLabels()),
                    'values' => collect(ConstructionSite::statusLabels())
                        ->map(fn (string $label, string $status): int => (int) ($worksByStatus[$status] ?? 0))
                        ->values()
                        ->all(),
                ],
                'receipts_by_month' => $receiptsByMonth,
            ],
            'recent' => [
                'sales_documents' => $recentSalesDocuments,
                'quotes' => $recentQuotes,
                'construction_sites' => $recentConstructionSites,
                'construction_sites_estimated_costs' => $recentSitesEstimatedCosts,
                'receipts' => $recentReceipts,
            ],
            'alerts' => [
                'overdue_documents' => (int) $overdueDocumentsCount,
                'quotes_without_response' => (int) $quotesNoResponseCount,
                'works_over_budget' => $worksOverBudgetCount,
                'low_stock_articles' => (int) $lowStockCount,
                'pending_purchase_order_receipts' => (int) $pendingPoReceiptsCount,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{
     *   period:string,
     *   customer_id:?int,
     *   responsible_id:?int,
     *   date_from:?string,
     *   date_to:?string,
     *   effective_date_from:?string,
     *   effective_date_to:?string,
     *   period_label:string
     * }
     */
    private function resolveFilters(array $filters): array
    {
        $period = strtolower(trim((string) ($filters['period'] ?? self::FILTER_PERIOD_THIS_MONTH)));
        if (! in_array($period, [
            self::FILTER_PERIOD_THIS_MONTH,
            self::FILTER_PERIOD_LAST_30_DAYS,
            self::FILTER_PERIOD_THIS_YEAR,
            self::FILTER_PERIOD_CUSTOM,
        ], true)) {
            $period = self::FILTER_PERIOD_THIS_MONTH;
        }

        $dateFrom = $this->normalizeDate($filters['date_from'] ?? null);
        $dateTo = $this->normalizeDate($filters['date_to'] ?? null);

        $effectiveFrom = null;
        $effectiveTo = null;

        if ($period === self::FILTER_PERIOD_THIS_MONTH) {
            $effectiveFrom = now()->startOfMonth()->toDateString();
            $effectiveTo = now()->toDateString();
        }

        if ($period === self::FILTER_PERIOD_LAST_30_DAYS) {
            $effectiveFrom = now()->subDays(29)->toDateString();
            $effectiveTo = now()->toDateString();
        }

        if ($period === self::FILTER_PERIOD_THIS_YEAR) {
            $effectiveFrom = now()->startOfYear()->toDateString();
            $effectiveTo = now()->toDateString();
        }

        if ($period === self::FILTER_PERIOD_CUSTOM) {
            $effectiveFrom = $dateFrom;
            $effectiveTo = $dateTo;
        }

        if ($effectiveFrom !== null && $effectiveTo !== null && $effectiveFrom > $effectiveTo) {
            [$effectiveFrom, $effectiveTo] = [$effectiveTo, $effectiveFrom];
        }

        $customerId = $this->normalizeInteger($filters['customer_id'] ?? null);
        $responsibleId = $this->normalizeInteger($filters['responsible_id'] ?? null);

        $periodLabel = match ($period) {
            self::FILTER_PERIOD_THIS_MONTH => 'Este mes',
            self::FILTER_PERIOD_LAST_30_DAYS => 'Ultimos 30 dias',
            self::FILTER_PERIOD_THIS_YEAR => 'Este ano',
            default => $this->customPeriodLabel($effectiveFrom, $effectiveTo),
        };

        return [
            'period' => $period,
            'customer_id' => $customerId,
            'responsible_id' => $responsibleId,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'effective_date_from' => $effectiveFrom,
            'effective_date_to' => $effectiveTo,
            'period_label' => $periodLabel,
        ];
    }

    private function normalizeDate(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = trim($value);
        if ($normalized === '') {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $normalized)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function normalizeInteger(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $normalized = (int) $value;

        return $normalized > 0 ? $normalized : null;
    }

    private function customPeriodLabel(?string $dateFrom, ?string $dateTo): string
    {
        if ($dateFrom !== null && $dateTo !== null) {
            return 'Personalizado: '.$dateFrom.' a '.$dateTo;
        }

        if ($dateFrom !== null) {
            return 'Personalizado: desde '.$dateFrom;
        }

        if ($dateTo !== null) {
            return 'Personalizado: ate '.$dateTo;
        }

        return 'Personalizado';
    }

    /**
     * @param Builder $query
     * @param array<string, mixed> $filters
     */
    private function applyCommonFilters(
        Builder $query,
        array $filters,
        string $dateColumn,
        ?string $customerColumn = null,
        ?string $responsibleColumn = null
    ): void {
        if ($filters['effective_date_from'] !== null) {
            $query->whereDate($dateColumn, '>=', $filters['effective_date_from']);
        }

        if ($filters['effective_date_to'] !== null) {
            $query->whereDate($dateColumn, '<=', $filters['effective_date_to']);
        }

        if ($customerColumn !== null && $filters['customer_id'] !== null) {
            $query->where($customerColumn, $filters['customer_id']);
        }

        if ($responsibleColumn !== null && $filters['responsible_id'] !== null) {
            $query->where($responsibleColumn, $filters['responsible_id']);
        }
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function sumIssuedSalesWithinWindow(int $companyId, array $filters, Carbon $windowFrom, Carbon $windowTo): float
    {
        $query = SalesDocument::query()
            ->forCompany($companyId)
            ->where('status', SalesDocument::STATUS_ISSUED)
            ->whereBetween('issue_date', [$windowFrom->toDateString(), $windowTo->toDateString()]);

        $this->applyCommonFilters(
            query: $query,
            filters: $filters,
            dateColumn: 'issue_date',
            customerColumn: 'customer_id',
            responsibleColumn: 'created_by'
        );

        return round((float) $query->sum('grand_total'), 2);
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function sumIssuedReceiptsWithinWindow(int $companyId, array $filters, Carbon $windowFrom, Carbon $windowTo): float
    {
        $query = SalesDocumentReceipt::query()
            ->forCompany($companyId)
            ->where('status', SalesDocumentReceipt::STATUS_ISSUED)
            ->whereBetween('receipt_date', [$windowFrom->toDateString(), $windowTo->toDateString()]);

        $this->applyCommonFilters(
            query: $query,
            filters: $filters,
            dateColumn: 'receipt_date',
            customerColumn: 'customer_id',
            responsibleColumn: 'created_by'
        );

        return round((float) $query->sum('amount'), 2);
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function resolveOpenValue(int $companyId, array $filters): float
    {
        $receiptTotalsQuery = SalesDocumentReceipt::query()
            ->from('sales_document_receipts as sdr')
            ->where('sdr.company_id', $companyId)
            ->where('sdr.status', SalesDocumentReceipt::STATUS_ISSUED)
            ->selectRaw('sdr.sales_document_id, SUM(sdr.amount) as received_total')
            ->groupBy('sdr.sales_document_id');

        if ($filters['effective_date_from'] !== null) {
            $receiptTotalsQuery->whereDate('sdr.receipt_date', '>=', $filters['effective_date_from']);
        }

        if ($filters['effective_date_to'] !== null) {
            $receiptTotalsQuery->whereDate('sdr.receipt_date', '<=', $filters['effective_date_to']);
        }

        if ($filters['customer_id'] !== null) {
            $receiptTotalsQuery->where('sdr.customer_id', $filters['customer_id']);
        }

        if ($filters['responsible_id'] !== null) {
            $receiptTotalsQuery->where('sdr.created_by', $filters['responsible_id']);
        }

        $documentQuery = SalesDocument::query()
            ->from('sales_documents')
            ->where('sales_documents.company_id', $companyId)
            ->where('sales_documents.status', SalesDocument::STATUS_ISSUED)
            ->leftJoinSub($receiptTotalsQuery, 'receipt_totals', function ($join): void {
                $join->on('receipt_totals.sales_document_id', '=', 'sales_documents.id');
            });

        if ($filters['effective_date_from'] !== null) {
            $documentQuery->whereDate('sales_documents.issue_date', '>=', $filters['effective_date_from']);
        }

        if ($filters['effective_date_to'] !== null) {
            $documentQuery->whereDate('sales_documents.issue_date', '<=', $filters['effective_date_to']);
        }

        if ($filters['customer_id'] !== null) {
            $documentQuery->where('sales_documents.customer_id', $filters['customer_id']);
        }

        if ($filters['responsible_id'] !== null) {
            $documentQuery->where('sales_documents.created_by', $filters['responsible_id']);
        }

        $openTotal = $documentQuery
            ->selectRaw('COALESCE(SUM(CASE WHEN (sales_documents.grand_total - COALESCE(receipt_totals.received_total, 0)) > 0 THEN (sales_documents.grand_total - COALESCE(receipt_totals.received_total, 0)) ELSE 0 END), 0) as open_total')
            ->value('open_total');

        return round((float) ($openTotal ?? 0), 2);
    }

    /**
     * @param array<string, mixed> $filters
     * @param array<int, string> $activeStatuses
     */
    private function resolveEstimatedWorksMargin(int $companyId, array $filters, array $activeStatuses): float
    {
        $siteQuery = ConstructionSite::query()
            ->from('construction_sites')
            ->where('construction_sites.company_id', $companyId)
            ->whereIn('construction_sites.status', $activeStatuses);

        if ($filters['effective_date_from'] !== null) {
            $siteQuery->whereDate('construction_sites.created_at', '>=', $filters['effective_date_from']);
        }

        if ($filters['effective_date_to'] !== null) {
            $siteQuery->whereDate('construction_sites.created_at', '<=', $filters['effective_date_to']);
        }

        if ($filters['customer_id'] !== null) {
            $siteQuery->where('construction_sites.customer_id', $filters['customer_id']);
        }

        if ($filters['responsible_id'] !== null) {
            $siteQuery->where('construction_sites.assigned_user_id', $filters['responsible_id']);
        }

        $siteIds = $siteQuery->pluck('construction_sites.id')->map(fn ($id): int => (int) $id)->all();
        if ($siteIds === []) {
            return 0.0;
        }

        $quoteTotal = (float) ConstructionSite::query()
            ->from('construction_sites')
            ->leftJoin('quotes', function ($join) use ($companyId): void {
                $join->on('quotes.id', '=', 'construction_sites.quote_id')
                    ->where('quotes.company_id', '=', $companyId);
            })
            ->where('construction_sites.company_id', $companyId)
            ->whereIn('construction_sites.id', $siteIds)
            ->sum('quotes.grand_total');

        $materialCost = (float) ConstructionSiteMaterialUsageItem::query()
            ->from('construction_site_material_usage_items as items')
            ->join('construction_site_material_usages as usages', function ($join) use ($companyId): void {
                $join->on('usages.id', '=', 'items.construction_site_material_usage_id')
                    ->where('usages.company_id', '=', $companyId)
                    ->where('usages.status', '=', ConstructionSiteMaterialUsage::STATUS_POSTED);
            })
            ->where('items.company_id', $companyId)
            ->whereIn('usages.construction_site_id', $siteIds)
            ->selectRaw('COALESCE(SUM(items.quantity * COALESCE(items.unit_cost, 0)), 0) as total')
            ->value('total');

        $laborCost = (float) ConstructionSiteTimeEntry::query()
            ->forCompany($companyId)
            ->whereIn('construction_site_id', $siteIds)
            ->sum('total_cost');

        return round($quoteTotal - $materialCost - $laborCost, 2);
    }

    /**
     * @param array<int, string> $activeStatuses
     */
    private function resolveWorksOverBudgetCount(int $companyId, array $activeStatuses): int
    {
        $materialCostsSubquery = ConstructionSiteMaterialUsageItem::query()
            ->from('construction_site_material_usage_items as items')
            ->join('construction_site_material_usages as usages', function ($join) use ($companyId): void {
                $join->on('usages.id', '=', 'items.construction_site_material_usage_id')
                    ->where('usages.company_id', '=', $companyId)
                    ->where('usages.status', '=', ConstructionSiteMaterialUsage::STATUS_POSTED);
            })
            ->where('items.company_id', $companyId)
            ->selectRaw('usages.construction_site_id, COALESCE(SUM(items.quantity * COALESCE(items.unit_cost, 0)), 0) as material_cost')
            ->groupBy('usages.construction_site_id');

        $laborCostsSubquery = ConstructionSiteTimeEntry::query()
            ->forCompany($companyId)
            ->selectRaw('construction_site_id, COALESCE(SUM(total_cost), 0) as labor_cost')
            ->groupBy('construction_site_id');

        return (int) ConstructionSite::query()
            ->from('construction_sites')
            ->join('quotes', function ($join) use ($companyId): void {
                $join->on('quotes.id', '=', 'construction_sites.quote_id')
                    ->where('quotes.company_id', '=', $companyId);
            })
            ->leftJoinSub($materialCostsSubquery, 'site_material_costs', function ($join): void {
                $join->on('site_material_costs.construction_site_id', '=', 'construction_sites.id');
            })
            ->leftJoinSub($laborCostsSubquery, 'site_labor_costs', function ($join): void {
                $join->on('site_labor_costs.construction_site_id', '=', 'construction_sites.id');
            })
            ->where('construction_sites.company_id', $companyId)
            ->whereIn('construction_sites.status', $activeStatuses)
            ->whereRaw('(COALESCE(site_material_costs.material_cost, 0) + COALESCE(site_labor_costs.labor_cost, 0)) > quotes.grand_total')
            ->count();
    }

    /**
     * @param array<int, int> $siteIds
     * @return array<int, float>
     */
    private function resolveEstimatedCostsBySite(int $companyId, array $siteIds): array
    {
        if ($siteIds === []) {
            return [];
        }

        $materialCosts = ConstructionSiteMaterialUsageItem::query()
            ->from('construction_site_material_usage_items as items')
            ->join('construction_site_material_usages as usages', function ($join) use ($companyId): void {
                $join->on('usages.id', '=', 'items.construction_site_material_usage_id')
                    ->where('usages.company_id', '=', $companyId)
                    ->where('usages.status', '=', ConstructionSiteMaterialUsage::STATUS_POSTED);
            })
            ->where('items.company_id', $companyId)
            ->whereIn('usages.construction_site_id', $siteIds)
            ->selectRaw('usages.construction_site_id as site_id, COALESCE(SUM(items.quantity * COALESCE(items.unit_cost, 0)), 0) as total_cost')
            ->groupBy('site_id')
            ->pluck('total_cost', 'site_id');

        $laborCosts = ConstructionSiteTimeEntry::query()
            ->forCompany($companyId)
            ->whereIn('construction_site_id', $siteIds)
            ->selectRaw('construction_site_id as site_id, COALESCE(SUM(total_cost), 0) as total_cost')
            ->groupBy('site_id')
            ->pluck('total_cost', 'site_id');

        $totals = [];
        foreach ($siteIds as $siteId) {
            $totals[$siteId] = round(
                (float) ($materialCosts[$siteId] ?? 0) + (float) ($laborCosts[$siteId] ?? 0),
                2
            );
        }

        return $totals;
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{labels: array<int, string>, values: array<int, float>}
     */
    private function buildMonthlySalesSeries(int $companyId, array $filters): array
    {
        $labels = [];
        $values = [];
        $startMonth = now()->startOfMonth()->subMonths(11);

        for ($i = 0; $i < 12; $i++) {
            $monthStart = $startMonth->copy()->addMonths($i)->startOfMonth();
            $monthEnd = $monthStart->copy()->endOfMonth();

            $query = SalesDocument::query()
                ->forCompany($companyId)
                ->where('status', SalesDocument::STATUS_ISSUED)
                ->whereBetween('issue_date', [$monthStart->toDateString(), $monthEnd->toDateString()]);

            $this->applyCommonFilters(
                query: $query,
                filters: $filters,
                dateColumn: 'issue_date',
                customerColumn: 'customer_id',
                responsibleColumn: 'created_by'
            );

            $labels[] = $monthStart->format('M Y');
            $values[] = round((float) $query->sum('grand_total'), 2);
        }

        return [
            'labels' => $labels,
            'values' => $values,
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{labels: array<int, string>, values: array<int, float>}
     */
    private function buildMonthlyReceiptsSeries(int $companyId, array $filters): array
    {
        $labels = [];
        $values = [];
        $startMonth = now()->startOfMonth()->subMonths(11);

        for ($i = 0; $i < 12; $i++) {
            $monthStart = $startMonth->copy()->addMonths($i)->startOfMonth();
            $monthEnd = $monthStart->copy()->endOfMonth();

            $query = SalesDocumentReceipt::query()
                ->forCompany($companyId)
                ->where('status', SalesDocumentReceipt::STATUS_ISSUED)
                ->whereBetween('receipt_date', [$monthStart->toDateString(), $monthEnd->toDateString()]);

            $this->applyCommonFilters(
                query: $query,
                filters: $filters,
                dateColumn: 'receipt_date',
                customerColumn: 'customer_id',
                responsibleColumn: 'created_by'
            );

            $labels[] = $monthStart->format('M Y');
            $values[] = round((float) $query->sum('amount'), 2);
        }

        return [
            'labels' => $labels,
            'values' => $values,
        ];
    }
}
