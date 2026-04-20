<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\QuoteDashboardFilterRequest;
use App\Models\Customer;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class QuoteDashboardController extends Controller
{
    public function __invoke(QuoteDashboardFilterRequest $request): View
    {
        $this->authorize('viewAny', Quote::class);

        $companyId = (int) $request->user()->company_id;
        $filters = $this->buildFilters($request, $companyId);
        $statusLabels = Quote::statusLabels();
        $statusBadgeClasses = collect(Quote::statuses())
            ->mapWithKeys(fn (string $status): array => [$status => (new Quote(['status' => $status]))->statusBadgeClass()])
            ->all();
        $today = Carbon::today();

        $baseQuery = Quote::query()->forCompany($companyId);
        $this->applyFilters($baseQuery, $filters);

        $statusAggregation = (clone $baseQuery)
            ->selectRaw('status, COUNT(*) as quotes_count, COALESCE(SUM(grand_total), 0) as total_value')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $statusSummary = collect(Quote::statuses())
            ->map(function (string $status) use ($statusAggregation, $statusLabels): array {
                $aggregate = $statusAggregation->get($status);

                return [
                    'status' => $status,
                    'label' => $statusLabels[$status] ?? $status,
                    'count' => (int) ($aggregate?->quotes_count ?? 0),
                    'value' => (float) ($aggregate?->total_value ?? 0),
                ];
            })
            ->values();

        $statusMetrics = $statusSummary->keyBy('status');
        $openStatuses = Quote::openCommercialStatuses();
        $closedStatuses = Quote::closedCommercialStatuses();

        $approvedCount = (int) ($statusMetrics->get(Quote::STATUS_APPROVED)['count'] ?? 0);
        $approvedValue = (float) ($statusMetrics->get(Quote::STATUS_APPROVED)['value'] ?? 0);
        $rejectedCount = (int) ($statusMetrics->get(Quote::STATUS_REJECTED)['count'] ?? 0);
        $cancelledCount = (int) ($statusMetrics->get(Quote::STATUS_CANCELLED)['count'] ?? 0);
        $expiredCount = (int) ($statusMetrics->get(Quote::STATUS_EXPIRED)['count'] ?? 0);
        $approvalDenominator = $approvedCount + $rejectedCount + $cancelledCount + $expiredCount;

        $kpis = [
            'total_quotes' => (int) $statusSummary->sum('count'),
            'counts' => [
                Quote::STATUS_DRAFT => (int) ($statusMetrics->get(Quote::STATUS_DRAFT)['count'] ?? 0),
                Quote::STATUS_SENT => (int) ($statusMetrics->get(Quote::STATUS_SENT)['count'] ?? 0),
                Quote::STATUS_VIEWED => (int) ($statusMetrics->get(Quote::STATUS_VIEWED)['count'] ?? 0),
                Quote::STATUS_APPROVED => $approvedCount,
                Quote::STATUS_REJECTED => $rejectedCount,
                Quote::STATUS_EXPIRED => $expiredCount,
                Quote::STATUS_CANCELLED => $cancelledCount,
            ],
            'values' => [
                'draft' => (float) ($statusMetrics->get(Quote::STATUS_DRAFT)['value'] ?? 0),
                'open' => (float) $statusSummary
                    ->whereIn('status', $openStatuses)
                    ->sum('value'),
                'approved' => $approvedValue,
                'lost' => (float) $statusSummary
                    ->whereIn('status', [Quote::STATUS_REJECTED, Quote::STATUS_CANCELLED])
                    ->sum('value'),
                'approved_avg_ticket' => $approvedCount > 0
                    ? round($approvedValue / $approvedCount, 2)
                    : 0.0,
            ],
            'approval_rate' => $approvalDenominator > 0
                ? round(($approvedCount / $approvalDenominator) * 100, 2)
                : 0.0,
            'approval_rate_formula' => 'Aprovados / (Aprovados + Rejeitados + Cancelados + Expirados)',
        ];

        $recentQuotes = (clone $baseQuery)
            ->with([
                'customer:id,name',
                'assignedUser:id,name',
            ])
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->limit(12)
            ->get([
                'id',
                'number',
                'customer_id',
                'issue_date',
                'valid_until',
                'status',
                'grand_total',
                'currency',
                'assigned_user_id',
                'follow_up_date',
                'updated_at',
            ]);

        $followUpQuotes = (clone $baseQuery)
            ->with([
                'customer:id,name',
                'assignedUser:id,name',
            ])
            ->whereIn('status', $openStatuses)
            ->whereNotNull('follow_up_date')
            ->whereDate('follow_up_date', '<=', $today->toDateString())
            ->orderBy('follow_up_date')
            ->orderByDesc('id')
            ->limit(20)
            ->get([
                'id',
                'number',
                'customer_id',
                'follow_up_date',
                'status',
                'valid_until',
                'grand_total',
                'currency',
                'assigned_user_id',
            ]);

        $openQuotes = (clone $baseQuery)
            ->with([
                'customer:id,name',
                'assignedUser:id,name',
            ])
            ->where(function (Builder $query) use ($today): void {
                $query->whereIn('status', [Quote::STATUS_SENT, Quote::STATUS_VIEWED])
                    ->orWhere(function (Builder $draftQuery) use ($today): void {
                        $draftQuery->where('status', Quote::STATUS_DRAFT)
                            ->whereDate('issue_date', '>=', $today->copy()->subDays(14)->toDateString());
                    });
            })
            ->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->limit(20)
            ->get([
                'id',
                'number',
                'customer_id',
                'issue_date',
                'valid_until',
                'status',
                'grand_total',
                'currency',
                'assigned_user_id',
            ]);

        $temporalRaw = (clone $baseQuery)
            ->selectRaw('SUBSTR(issue_date, 1, 7) as period_month')
            ->selectRaw('COUNT(*) as created_count')
            ->selectRaw("SUM(CASE WHEN status = '".Quote::STATUS_APPROVED."' THEN 1 ELSE 0 END) as approved_count")
            ->selectRaw("COALESCE(SUM(CASE WHEN status = '".Quote::STATUS_APPROVED."' THEN grand_total ELSE 0 END), 0) as approved_value")
            ->groupBy('period_month')
            ->orderBy('period_month')
            ->get()
            ->keyBy('period_month');

        $periodStart = Carbon::parse($filters['date_from'])->startOfMonth();
        $periodEnd = Carbon::parse($filters['date_to'])->startOfMonth();
        $temporalPerformance = [];
        $cursor = $periodStart->copy();

        while ($cursor->lte($periodEnd)) {
            $monthKey = $cursor->format('Y-m');
            $aggregate = $temporalRaw->get($monthKey);

            $temporalPerformance[] = [
                'month' => $monthKey,
                'month_label' => $cursor->translatedFormat('M Y'),
                'created_count' => (int) ($aggregate?->created_count ?? 0),
                'approved_count' => (int) ($aggregate?->approved_count ?? 0),
                'approved_value' => (float) ($aggregate?->approved_value ?? 0),
            ];

            $cursor->addMonth();
        }

        $closedStatusesSql = implode("','", $closedStatuses);
        $responsibleRaw = (clone $baseQuery)
            ->whereNotNull('assigned_user_id')
            ->selectRaw('assigned_user_id, COUNT(*) as quotes_count, COALESCE(SUM(grand_total), 0) as total_value')
            ->selectRaw("SUM(CASE WHEN status = '".Quote::STATUS_APPROVED."' THEN 1 ELSE 0 END) as approved_count")
            ->selectRaw("SUM(CASE WHEN status IN ('{$closedStatusesSql}') THEN 1 ELSE 0 END) as closed_count")
            ->groupBy('assigned_user_id')
            ->orderByDesc('total_value')
            ->get();

        $assignedUsersById = User::query()
            ->where('is_super_admin', false)
            ->where('company_id', $companyId)
            ->whereIn('id', $responsibleRaw->pluck('assigned_user_id')->all())
            ->pluck('name', 'id');

        $responsiblePerformance = $responsibleRaw
            ->map(function ($aggregate) use ($assignedUsersById): array {
                $closedCount = (int) ($aggregate->closed_count ?? 0);

                return [
                    'assigned_user_id' => (int) $aggregate->assigned_user_id,
                    'name' => (string) ($assignedUsersById[(int) $aggregate->assigned_user_id] ?? 'Utilizador removido'),
                    'quotes_count' => (int) ($aggregate->quotes_count ?? 0),
                    'total_value' => (float) ($aggregate->total_value ?? 0),
                    'approved_count' => (int) ($aggregate->approved_count ?? 0),
                    'approval_rate' => $closedCount > 0
                        ? round((((int) ($aggregate->approved_count ?? 0)) / $closedCount) * 100, 2)
                        : 0.0,
                ];
            })
            ->values();

        $customerOptions = Customer::query()
            ->forCompany($companyId)
            ->orderBy('name')
            ->get(['id', 'name']);

        $assignedUserOptions = User::query()
            ->where('is_super_admin', false)
            ->where('company_id', $companyId)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('admin.quotes.dashboard', [
            'filters' => $filters,
            'periodOptions' => $this->periodOptions(),
            'statusLabels' => $statusLabels,
            'statusBadgeClasses' => $statusBadgeClasses,
            'customerOptions' => $customerOptions,
            'assignedUserOptions' => $assignedUserOptions,
            'kpis' => $kpis,
            'statusSummary' => $statusSummary,
            'recentQuotes' => $recentQuotes,
            'followUpQuotes' => $followUpQuotes,
            'openQuotes' => $openQuotes,
            'temporalPerformance' => $temporalPerformance,
            'responsiblePerformance' => $responsiblePerformance,
            'today' => $today,
            'openStatuses' => $openStatuses,
        ]);
    }

    /**
     * @return array{
     *   period: string,
     *   status: ?string,
     *   customer_id: ?int,
     *   assigned_user_id: ?int,
     *   date_from: string,
     *   date_to: string
     * }
     */
    private function buildFilters(QuoteDashboardFilterRequest $request, int $companyId): array
    {
        $validated = $request->validated();
        $period = (string) ($validated['period'] ?? 'this_year');
        $status = $validated['status'] ?? null;
        $customerId = isset($validated['customer_id']) ? (int) $validated['customer_id'] : null;
        $assignedUserId = isset($validated['assigned_user_id']) ? (int) $validated['assigned_user_id'] : null;

        if ($customerId !== null) {
            $customerExists = Customer::query()
                ->forCompany($companyId)
                ->whereKey($customerId)
                ->exists();

            if (! $customerExists) {
                throw new NotFoundHttpException();
            }
        }

        if ($assignedUserId !== null) {
            $assignedUserExists = User::query()
                ->where('is_super_admin', false)
                ->where('company_id', $companyId)
                ->whereKey($assignedUserId)
                ->exists();

            if (! $assignedUserExists) {
                throw new NotFoundHttpException();
            }
        }

        [$dateFrom, $dateTo] = $this->resolveDateRange(
            period: $period,
            customDateFrom: $validated['date_from'] ?? null,
            customDateTo: $validated['date_to'] ?? null
        );

        return [
            'period' => $period,
            'status' => $status,
            'customer_id' => $customerId,
            'assigned_user_id' => $assignedUserId,
            'date_from' => $dateFrom->toDateString(),
            'date_to' => $dateTo->toDateString(),
        ];
    }

    /**
     * @param Builder<Quote> $query
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        $query
            ->whereBetween('issue_date', [$filters['date_from'], $filters['date_to']])
            ->when($filters['status'] !== null, function (Builder $statusQuery) use ($filters): void {
                $statusQuery->where('status', $filters['status']);
            })
            ->when($filters['customer_id'] !== null, function (Builder $customerQuery) use ($filters): void {
                $customerQuery->where('customer_id', $filters['customer_id']);
            })
            ->when($filters['assigned_user_id'] !== null, function (Builder $assignedQuery) use ($filters): void {
                $assignedQuery->where('assigned_user_id', $filters['assigned_user_id']);
            });
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolveDateRange(string $period, ?string $customDateFrom, ?string $customDateTo): array
    {
        $today = Carbon::today();

        return match ($period) {
            'today' => [$today->copy(), $today->copy()],
            'this_week' => [$today->copy()->startOfWeek(), $today->copy()->endOfWeek()],
            'this_month' => [$today->copy()->startOfMonth(), $today->copy()->endOfMonth()],
            'this_quarter' => [$today->copy()->startOfQuarter(), $today->copy()->endOfQuarter()],
            'custom' => [Carbon::parse((string) $customDateFrom), Carbon::parse((string) $customDateTo)],
            default => [$today->copy()->startOfYear(), $today->copy()->endOfYear()],
        };
    }

    /**
     * @return array<string, string>
     */
    private function periodOptions(): array
    {
        return [
            'today' => 'Hoje',
            'this_week' => 'Esta semana',
            'this_month' => 'Este mes',
            'this_quarter' => 'Este trimestre',
            'this_year' => 'Este ano',
            'custom' => 'Personalizado',
        ];
    }
}
