<?php

namespace App\Services\Admin;

use App\Models\PurchaseDocument;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SupplierStatementService
{
    public const VIEW_ALL = 'all';
    public const VIEW_OPEN = 'open';
    public const VIEW_OVERDUE = 'overdue';
    public const VIEW_SETTLED = 'settled';

    /**
     * @return array{
     *   supplier: Supplier,
     *   movements: Collection<int, array<string, mixed>>|LengthAwarePaginator,
     *   total_debit: float,
     *   total_credit: float,
     *   balance: float,
     *   filters: array{date_from: ?string, date_to: ?string, statement_view: string},
     *   period_label: string,
     *   statementViewLabels: array<string, string>
     * }
     */
    public function buildStatement(
        int $companyId,
        int $supplierId,
        array $filters = [],
        bool $paginate = false,
        int $perPage = 50
    ): array {
        $normalizedFilters = $this->normalizeFilters($filters);
        $dateFrom = $normalizedFilters['date_from'];
        $dateTo = $normalizedFilters['date_to'];
        $statementView = $normalizedFilters['statement_view'];

        /** @var Supplier $supplier */
        $supplier = Supplier::query()
            ->forCompany($companyId)
            ->whereKey($supplierId)
            ->firstOrFail();

        $baseQuery = $this->buildMovementsBaseQuery(
            companyId: $companyId,
            supplierId: $supplierId,
            dateFrom: $dateFrom,
            dateTo: $dateTo,
            statementView: $statementView
        );

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
            'supplier' => $supplier,
            'movements' => $movementPayload,
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
            'balance' => $balance,
            'filters' => $normalizedFilters,
            'period_label' => $this->buildPeriodLabel($normalizedFilters),
            'statementViewLabels' => self::statementViewLabels(),
        ];
    }

    /**
     * @return \Illuminate\Database\Query\Builder
     */
    private function buildMovementsBaseQuery(
        int $companyId,
        int $supplierId,
        ?string $dateFrom,
        ?string $dateTo,
        string $statementView
    ): \Illuminate\Database\Query\Builder
    {
        $baseDocumentScope = PurchaseDocument::query()
            ->forCompany($companyId)
            ->where('supplier_id', $supplierId)
            ->where('status', PurchaseDocument::STATUS_CONFIRMED);

        $this->applyStatementViewToDocumentQuery($baseDocumentScope, $statementView);

        $documentMovements = (clone $baseDocumentScope)
            ->when($dateFrom !== null, fn ($query) => $query->whereDate('issue_date', '>=', $dateFrom))
            ->when($dateTo !== null, fn ($query) => $query->whereDate('issue_date', '<=', $dateTo))
            ->selectRaw('issue_date as movement_date')
            ->selectRaw('1 as sort_order')
            ->selectRaw("'purchase_document' as movement_type")
            ->selectRaw("'".PurchaseDocument::STATUS_CONFIRMED."' as movement_status")
            ->selectRaw('number as movement_number')
            ->selectRaw("'Documento de Compra confirmado' as movement_description")
            ->selectRaw('COALESCE(grand_total, 0) as debit')
            ->selectRaw('0 as credit')
            ->selectRaw('id as reference_id');

        $paymentDocumentIdsQuery = (clone $baseDocumentScope)->select('id');

        $paymentMovements = SupplierPayment::query()
            ->forCompany($companyId)
            ->where('supplier_id', $supplierId)
            ->whereIn('purchase_document_id', $paymentDocumentIdsQuery)
            ->when($dateFrom !== null, fn ($query) => $query->whereDate('payment_date', '>=', $dateFrom))
            ->when($dateTo !== null, fn ($query) => $query->whereDate('payment_date', '<=', $dateTo))
            ->selectRaw('payment_date as movement_date')
            ->selectRaw('CASE WHEN status = ? THEN 2 ELSE 3 END as sort_order', [SupplierPayment::STATUS_ISSUED])
            ->selectRaw("'supplier_payment' as movement_type")
            ->selectRaw('status as movement_status')
            ->selectRaw('number as movement_number')
            ->selectRaw("CASE WHEN status = ? THEN 'Pagamento a Fornecedor emitido' ELSE 'Pagamento cancelado (sem impacto)' END as movement_description", [SupplierPayment::STATUS_ISSUED])
            ->selectRaw('0 as debit')
            ->selectRaw('CASE WHEN status = ? THEN COALESCE(amount, 0) ELSE 0 END as credit', [SupplierPayment::STATUS_ISSUED])
            ->selectRaw('id as reference_id');

        return DB::query()->fromSub($documentMovements->unionAll($paymentMovements), 'supplier_movements');
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
                'route' => $type === 'purchase_document'
                    ? route('admin.purchase-documents.show', $referenceId)
                    : route('admin.supplier-payments.show', $referenceId),
                'balance' => $runningBalance,
            ];
        });
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{date_from: ?string, date_to: ?string, statement_view: string}
     */
    private function normalizeFilters(array $filters): array
    {
        $dateFrom = $this->normalizeDateFilter($filters['date_from'] ?? null);
        $dateTo = $this->normalizeDateFilter($filters['date_to'] ?? null);
        $statementView = $this->normalizeStatementView($filters['statement_view'] ?? null);

        if ($dateFrom === null && $dateTo === null) {
            $dateFrom = now()->subDays(90)->toDateString();
            $dateTo = now()->toDateString();
        }

        return [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'statement_view' => $statementView,
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
     * @param array{date_from: ?string, date_to: ?string, statement_view: string} $filters
     */
    private function buildPeriodLabel(array $filters): string
    {
        $dateFrom = $filters['date_from'];
        $dateTo = $filters['date_to'];
        $viewLabel = self::statementViewLabels()[$filters['statement_view']] ?? self::statementViewLabels()[self::VIEW_ALL];

        if ($dateFrom !== null && $dateTo !== null) {
            return $viewLabel.' | Periodo: '.$dateFrom.' a '.$dateTo;
        }

        if ($dateFrom !== null) {
            return $viewLabel.' | Periodo: desde '.$dateFrom;
        }

        if ($dateTo !== null) {
            return $viewLabel.' | Periodo: ate '.$dateTo;
        }

        return $viewLabel.' | Periodo: completo';
    }

    public static function statementViewLabels(): array
    {
        return [
            self::VIEW_ALL => 'Extrato completo',
            self::VIEW_OPEN => 'Em aberto',
            self::VIEW_OVERDUE => 'Vencidas',
            self::VIEW_SETTLED => 'Liquidadas',
        ];
    }

    private function normalizeStatementView(mixed $value): string
    {
        $normalized = strtolower(trim((string) $value));

        return in_array($normalized, [
            self::VIEW_ALL,
            self::VIEW_OPEN,
            self::VIEW_OVERDUE,
            self::VIEW_SETTLED,
        ], true)
            ? $normalized
            : self::VIEW_ALL;
    }

    private function applyStatementViewToDocumentQuery(\Illuminate\Database\Eloquent\Builder $query, string $statementView): void
    {
        if ($statementView === self::VIEW_OPEN) {
            $query->whereIn('payment_status', [
                PurchaseDocument::PAYMENT_STATUS_UNPAID,
                PurchaseDocument::PAYMENT_STATUS_PARTIAL,
            ]);

            return;
        }

        if ($statementView === self::VIEW_SETTLED) {
            $query->where('payment_status', PurchaseDocument::PAYMENT_STATUS_PAID);

            return;
        }

        if ($statementView === self::VIEW_OVERDUE) {
            $query
                ->whereIn('payment_status', [
                    PurchaseDocument::PAYMENT_STATUS_UNPAID,
                    PurchaseDocument::PAYMENT_STATUS_PARTIAL,
                ])
                ->whereNotNull('due_date')
                ->whereDate('due_date', '<', now()->toDateString());
        }
    }
}
