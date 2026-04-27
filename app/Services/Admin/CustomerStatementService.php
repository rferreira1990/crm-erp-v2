<?php

namespace App\Services\Admin;

use App\Models\Customer;
use App\Models\SalesDocument;
use App\Models\SalesDocumentReceipt;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class CustomerStatementService
{
    /**
     * @return array{
     *   customer: Customer,
     *   movements: Collection<int, array<string, mixed>>,
     *   total_debit: float,
     *   total_credit: float,
     *   balance: float,
     *   filters: array{date_from: ?string, date_to: ?string},
     *   period_label: string
     * }
     */
    public function buildStatement(int $companyId, int $customerId, array $filters = []): array
    {
        $normalizedFilters = $this->normalizeFilters($filters);
        $dateFrom = $normalizedFilters['date_from'];
        $dateTo = $normalizedFilters['date_to'];

        /** @var Customer $customer */
        $customer = Customer::query()
            ->forCompany($companyId)
            ->whereKey($customerId)
            ->firstOrFail();

        $documentMovements = SalesDocument::query()
            ->forCompany($companyId)
            ->where('customer_id', $customerId)
            ->where('status', SalesDocument::STATUS_ISSUED)
            ->when($dateFrom !== null, function ($query) use ($dateFrom): void {
                $query->whereDate('issue_date', '>=', $dateFrom);
            })
            ->when($dateTo !== null, function ($query) use ($dateTo): void {
                $query->whereDate('issue_date', '<=', $dateTo);
            })
            ->orderBy('issue_date')
            ->orderBy('id')
            ->get(['id', 'number', 'issue_date', 'grand_total', 'currency'])
            ->map(function (SalesDocument $document): array {
                $debit = round((float) $document->grand_total, 2);

                return [
                    'date' => $document->issue_date,
                    'sort_order' => 1,
                    'type' => 'sales_document',
                    'status' => SalesDocument::STATUS_ISSUED,
                    'number' => (string) $document->number,
                    'description' => 'Documento de Venda emitido',
                    'debit' => $debit,
                    'credit' => 0.0,
                    'reference_id' => (int) $document->id,
                    'route' => route('admin.sales-documents.show', $document->id),
                ];
            });

        $receiptMovements = SalesDocumentReceipt::query()
            ->forCompany($companyId)
            ->where('customer_id', $customerId)
            ->when($dateFrom !== null, function ($query) use ($dateFrom): void {
                $query->whereDate('receipt_date', '>=', $dateFrom);
            })
            ->when($dateTo !== null, function ($query) use ($dateTo): void {
                $query->whereDate('receipt_date', '<=', $dateTo);
            })
            ->orderBy('receipt_date')
            ->orderBy('id')
            ->get(['id', 'number', 'receipt_date', 'amount', 'status', 'sales_document_id'])
            ->map(function (SalesDocumentReceipt $receipt): array {
                $isIssued = $receipt->status === SalesDocumentReceipt::STATUS_ISSUED;

                return [
                    'date' => $receipt->receipt_date,
                    'sort_order' => $isIssued ? 2 : 3,
                    'type' => 'receipt',
                    'status' => $receipt->status,
                    'number' => (string) $receipt->number,
                    'description' => $isIssued
                        ? 'Recibo emitido'
                        : 'Recibo cancelado (sem impacto)',
                    'debit' => 0.0,
                    'credit' => $isIssued ? round((float) $receipt->amount, 2) : 0.0,
                    'reference_id' => (int) $receipt->id,
                    'sales_document_id' => (int) $receipt->sales_document_id,
                    'route' => route('admin.sales-document-receipts.show', $receipt->id),
                ];
            });

        $movements = $documentMovements
            ->merge($receiptMovements)
            ->sortBy([
                ['date', 'asc'],
                ['sort_order', 'asc'],
                ['reference_id', 'asc'],
            ])
            ->values();

        $runningBalance = 0.0;
        $movements = $movements->map(function (array $movement) use (&$runningBalance): array {
            $runningBalance = round($runningBalance + (float) $movement['debit'] - (float) $movement['credit'], 2);
            $movement['balance'] = $runningBalance;

            return $movement;
        });

        $totalDebit = round((float) $movements->sum('debit'), 2);
        $totalCredit = round((float) $movements->sum('credit'), 2);

        return [
            'customer' => $customer,
            'movements' => $movements,
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
            'balance' => round($totalDebit - $totalCredit, 2),
            'filters' => $normalizedFilters,
            'period_label' => $this->buildPeriodLabel($normalizedFilters),
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{date_from: ?string, date_to: ?string}
     */
    private function normalizeFilters(array $filters): array
    {
        return [
            'date_from' => $this->normalizeDateFilter($filters['date_from'] ?? null),
            'date_to' => $this->normalizeDateFilter($filters['date_to'] ?? null),
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
