<?php

namespace App\Services\Admin;

use App\Models\Customer;
use App\Models\SalesDocument;
use App\Models\SalesDocumentReceipt;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CustomerStatementService
{
    /**
     * @return array{
     *   customer: Customer,
     *   movements: Collection<int, array<string, mixed>>|LengthAwarePaginator,
     *   total_debit: float,
     *   total_credit: float,
     *   balance: float,
     *   filters: array{date_from: ?string, date_to: ?string},
     *   period_label: string
     * }
     */
    public function buildStatement(
        int $companyId,
        int $customerId,
        array $filters = [],
        bool $paginate = false,
        int $perPage = 50
    ): array {
        $normalizedFilters = $this->normalizeFilters($filters);
        $dateFrom = $normalizedFilters['date_from'];
        $dateTo = $normalizedFilters['date_to'];

        /** @var Customer $customer */
        $customer = Customer::query()
            ->forCompany($companyId)
            ->whereKey($customerId)
            ->firstOrFail();

        $baseQuery = $this->buildMovementsBaseQuery($companyId, $customerId, $dateFrom, $dateTo);

        $totals = DB::query()
            ->fromSub(clone $baseQuery, 'movements_totals')
            ->selectRaw('COALESCE(SUM(debit), 0) as total_debit')
            ->selectRaw('COALESCE(SUM(credit), 0) as total_credit')
            ->first();

        $totalDebit = round((float) ($totals->total_debit ?? 0), 2);
        $totalCredit = round((float) ($totals->total_credit ?? 0), 2);
        $balance = round($totalDebit - $totalCredit, 2);

        $orderedQuery = (clone $baseQuery)
            ->orderBy('movement_date')
            ->orderBy('sort_order')
            ->orderBy('reference_id');

        if ($paginate) {
            $page = max(1, (int) ($filters['page'] ?? request()->integer('page', 1)));
            $safePerPage = max(1, min(200, $perPage));

            $paginator = $orderedQuery
                ->paginate($safePerPage, ['*'], 'page', $page)
                ->withQueryString();

            $openingBalance = $this->calculateOpeningBalance(clone $orderedQuery, ($page - 1) * $safePerPage);
            $movements = $this->mapMovements(collect($paginator->items()), $openingBalance);
            $paginator->setCollection($movements);

            $movementPayload = $paginator;
        } else {
            $movementPayload = $this->mapMovements($orderedQuery->get(), 0.0);
        }

        return [
            'customer' => $customer,
            'movements' => $movementPayload,
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
            'balance' => $balance,
            'filters' => $normalizedFilters,
            'period_label' => $this->buildPeriodLabel($normalizedFilters),
        ];
    }

    /**
     * @return \Illuminate\Database\Query\Builder
     */
    private function buildMovementsBaseQuery(int $companyId, int $customerId, ?string $dateFrom, ?string $dateTo): \Illuminate\Database\Query\Builder
    {
        $documentMovements = SalesDocument::query()
            ->forCompany($companyId)
            ->where('customer_id', $customerId)
            ->where('status', SalesDocument::STATUS_ISSUED)
            ->when($dateFrom !== null, fn ($query) => $query->whereDate('issue_date', '>=', $dateFrom))
            ->when($dateTo !== null, fn ($query) => $query->whereDate('issue_date', '<=', $dateTo))
            ->selectRaw('issue_date as movement_date')
            ->selectRaw('1 as sort_order')
            ->selectRaw("'sales_document' as movement_type")
            ->selectRaw("'".SalesDocument::STATUS_ISSUED."' as movement_status")
            ->selectRaw('number as movement_number')
            ->selectRaw("'Documento de Venda emitido' as movement_description")
            ->selectRaw('COALESCE(grand_total, 0) as debit')
            ->selectRaw('0 as credit')
            ->selectRaw('id as reference_id');

        $receiptMovements = SalesDocumentReceipt::query()
            ->forCompany($companyId)
            ->where('customer_id', $customerId)
            ->when($dateFrom !== null, fn ($query) => $query->whereDate('receipt_date', '>=', $dateFrom))
            ->when($dateTo !== null, fn ($query) => $query->whereDate('receipt_date', '<=', $dateTo))
            ->selectRaw('receipt_date as movement_date')
            ->selectRaw('CASE WHEN status = ? THEN 2 ELSE 3 END as sort_order', [SalesDocumentReceipt::STATUS_ISSUED])
            ->selectRaw("'receipt' as movement_type")
            ->selectRaw('status as movement_status')
            ->selectRaw('number as movement_number')
            ->selectRaw("CASE WHEN status = ? THEN 'Recibo emitido' ELSE 'Recibo cancelado (sem impacto)' END as movement_description", [SalesDocumentReceipt::STATUS_ISSUED])
            ->selectRaw('0 as debit')
            ->selectRaw('CASE WHEN status = ? THEN COALESCE(amount, 0) ELSE 0 END as credit', [SalesDocumentReceipt::STATUS_ISSUED])
            ->selectRaw('id as reference_id');

        return DB::query()->fromSub($documentMovements->unionAll($receiptMovements), 'customer_movements');
    }

    private function calculateOpeningBalance(\Illuminate\Database\Query\Builder $orderedQuery, int $offset): float
    {
        if ($offset <= 0) {
            return 0.0;
        }

        $summary = DB::query()
            ->fromSub($orderedQuery->limit($offset), 'opening_slice')
            ->selectRaw('COALESCE(SUM(debit), 0) as opening_debit')
            ->selectRaw('COALESCE(SUM(credit), 0) as opening_credit')
            ->first();

        return round((float) ($summary->opening_debit ?? 0) - (float) ($summary->opening_credit ?? 0), 2);
    }

    /**
     * @param Collection<int, object> $rows
     * @return Collection<int, array<string, mixed>>
     */
    private function mapMovements(Collection $rows, float $startingBalance): Collection
    {
        $runningBalance = $startingBalance;

        return $rows->map(function (object $row) use (&$runningBalance): array {
            $debit = round((float) ($row->debit ?? 0), 2);
            $credit = round((float) ($row->credit ?? 0), 2);
            $runningBalance = round($runningBalance + $debit - $credit, 2);

            $type = (string) ($row->movement_type ?? '');
            $referenceId = (int) ($row->reference_id ?? 0);

            return [
                'date' => isset($row->movement_date) ? Carbon::parse((string) $row->movement_date) : null,
                'sort_order' => (int) ($row->sort_order ?? 0),
                'type' => $type,
                'status' => (string) ($row->movement_status ?? ''),
                'number' => (string) ($row->movement_number ?? ''),
                'description' => (string) ($row->movement_description ?? ''),
                'debit' => $debit,
                'credit' => $credit,
                'reference_id' => $referenceId,
                'route' => $type === 'sales_document'
                    ? route('admin.sales-documents.show', $referenceId)
                    : route('admin.sales-document-receipts.show', $referenceId),
                'balance' => $runningBalance,
            ];
        });
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{date_from: ?string, date_to: ?string}
     */
    private function normalizeFilters(array $filters): array
    {
        $dateFrom = $this->normalizeDateFilter($filters['date_from'] ?? null);
        $dateTo = $this->normalizeDateFilter($filters['date_to'] ?? null);

        if ($dateFrom === null && $dateTo === null) {
            $dateFrom = now()->subDays(90)->toDateString();
            $dateTo = now()->toDateString();
        }

        return [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ];
    }

    private function normalizeDateFilter(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);
        if ($normalized === '') {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $normalized)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param array{date_from: ?string, date_to: ?string} $filters
     */
    private function buildPeriodLabel(array $filters): string
    {
        $dateFrom = $filters['date_from'];
        $dateTo = $filters['date_to'];

        if ($dateFrom !== null && $dateTo !== null) {
            return 'Periodo: '.$dateFrom.' a '.$dateTo;
        }

        if ($dateFrom !== null) {
            return 'Periodo: desde '.$dateFrom;
        }

        if ($dateTo !== null) {
            return 'Periodo: ate '.$dateTo;
        }

        return 'Periodo: completo';
    }
}
