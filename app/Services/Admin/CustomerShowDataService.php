<?php

namespace App\Services\Admin;

use App\Models\Customer;
use App\Models\Quote;
use App\Models\SalesDocument;
use App\Models\SalesDocumentReceipt;
use Illuminate\Support\Collection;

class CustomerShowDataService
{
    /**
     * @return array{
     *   customer: Customer,
     *   contacts: Collection<int, \App\Models\CustomerContact>,
     *   recentQuotes: Collection<int, Quote>,
     *   recentSalesDocuments: Collection<int, SalesDocument>,
     *   recentReceipts: Collection<int, SalesDocumentReceipt>,
     *   openSalesDocuments: Collection<int, SalesDocument>,
     *   kpis: array<string, float|int|string|null>,
     *   statementSummary: array{
     *     total_debit: float,
     *     total_credit: float,
     *     balance: float,
     *     open_amount: float
     *   },
     *   paymentStatusCounts: array<string, int>,
     *   activity: array<string, string|null>
     * }
     */
    public function build(int $companyId, Customer $customer): array
    {
        $customer->loadMissing([
            'country:id,name,iso_code',
            'priceTier:id,name,percentage_adjustment',
            'paymentTerm:id,name',
            'defaultVatRate:id,name,rate',
            'defaultVatExemptionReason:id,code,name',
            'contacts' => fn ($query) => $query
                ->orderByDesc('is_primary')
                ->orderBy('name')
                ->orderBy('id'),
        ]);

        $customerId = (int) $customer->id;

        $quotesBaseQuery = Quote::query()
            ->forCompany($companyId)
            ->where('customer_id', $customerId);

        $recentQuotes = (clone $quotesBaseQuery)
            ->with(['assignedUser:id,name'])
            ->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->limit(5)
            ->get([
                'id',
                'company_id',
                'number',
                'status',
                'issue_date',
                'valid_until',
                'grand_total',
                'currency',
                'assigned_user_id',
            ]);

        $documentsBaseQuery = SalesDocument::query()
            ->forCompany($companyId)
            ->where('customer_id', $customerId);

        $issuedDocumentsBaseQuery = (clone $documentsBaseQuery)
            ->where('status', SalesDocument::STATUS_ISSUED);

        $recentSalesDocuments = (clone $documentsBaseQuery)
            ->withCount('items')
            ->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->limit(5)
            ->get([
                'id',
                'company_id',
                'number',
                'status',
                'source_type',
                'payment_status',
                'issue_date',
                'grand_total',
                'currency',
            ]);

        $openSalesDocuments = (clone $issuedDocumentsBaseQuery)
            ->whereIn('payment_status', [
                SalesDocument::PAYMENT_STATUS_UNPAID,
                SalesDocument::PAYMENT_STATUS_PARTIAL,
            ])
            ->withSum([
                'receipts as issued_receipts_total' => fn ($query) => $query
                    ->where('status', SalesDocumentReceipt::STATUS_ISSUED),
            ], 'amount')
            ->orderByRaw('CASE WHEN due_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('due_date')
            ->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->limit(8)
            ->get([
                'id',
                'company_id',
                'number',
                'status',
                'payment_status',
                'issue_date',
                'due_date',
                'grand_total',
                'currency',
            ])
            ->map(function (SalesDocument $document): SalesDocument {
                $issuedReceiptsTotal = round((float) ($document->issued_receipts_total ?? 0), 2);
                $openAmount = round(max(0, (float) $document->grand_total - $issuedReceiptsTotal), 2);
                $isOverdue = $document->due_date !== null && $document->due_date->lt(now()->startOfDay());

                $document->setAttribute('issued_receipts_total', $issuedReceiptsTotal);
                $document->setAttribute('open_amount', $openAmount);
                $document->setAttribute('is_overdue', $isOverdue);

                return $document;
            })
            ->filter(fn (SalesDocument $document): bool => (float) $document->open_amount > 0)
            ->values();

        $receiptsBaseQuery = SalesDocumentReceipt::query()
            ->forCompany($companyId)
            ->where('customer_id', $customerId);

        $recentReceipts = (clone $receiptsBaseQuery)
            ->with([
                'salesDocument:id,number',
                'paymentMethod:id,name',
            ])
            ->orderByDesc('receipt_date')
            ->orderByDesc('id')
            ->limit(5)
            ->get([
                'id',
                'company_id',
                'sales_document_id',
                'payment_method_id',
                'number',
                'status',
                'receipt_date',
                'amount',
            ]);

        $totalQuotes = (int) (clone $quotesBaseQuery)->count();
        $totalApprovedQuotes = (int) (clone $quotesBaseQuery)
            ->where('status', Quote::STATUS_APPROVED)
            ->count();
        $totalApprovedQuoteValue = round((float) (clone $quotesBaseQuery)
            ->where('status', Quote::STATUS_APPROVED)
            ->sum('grand_total'), 2);
        $totalIssuedDocuments = (int) (clone $issuedDocumentsBaseQuery)->count();
        $totalIssuedSales = round((float) (clone $issuedDocumentsBaseQuery)->sum('grand_total'), 2);
        $totalIssuedFromQuotes = (int) (clone $issuedDocumentsBaseQuery)
            ->where('source_type', SalesDocument::SOURCE_QUOTE)
            ->count();
        $totalReceived = round((float) (clone $receiptsBaseQuery)
            ->where('status', SalesDocumentReceipt::STATUS_ISSUED)
            ->sum('amount'), 2);

        $balance = round($totalIssuedSales - $totalReceived, 2);
        $openAmount = $balance > 0 ? $balance : 0.0;
        $overdueOpenAmount = round((float) $openSalesDocuments
            ->where('is_overdue', true)
            ->sum('open_amount'), 2);
        $quoteApprovalRate = $totalQuotes > 0
            ? round(($totalApprovedQuotes / $totalQuotes) * 100, 1)
            : 0.0;
        $quoteToIssuedRate = $totalApprovedQuotes > 0
            ? round(($totalIssuedFromQuotes / $totalApprovedQuotes) * 100, 1)
            : 0.0;

        $paymentStatusCounts = [
            SalesDocument::PAYMENT_STATUS_UNPAID => 0,
            SalesDocument::PAYMENT_STATUS_PARTIAL => 0,
            SalesDocument::PAYMENT_STATUS_PAID => 0,
        ];

        $rawPaymentStatusCounts = (clone $issuedDocumentsBaseQuery)
            ->selectRaw('COALESCE(payment_status, ?) as payment_status_normalized, COUNT(*) as aggregate', [SalesDocument::PAYMENT_STATUS_UNPAID])
            ->groupBy('payment_status_normalized')
            ->pluck('aggregate', 'payment_status_normalized');

        foreach ($rawPaymentStatusCounts as $status => $aggregate) {
            if (! array_key_exists((string) $status, $paymentStatusCounts)) {
                continue;
            }

            $paymentStatusCounts[(string) $status] = (int) $aggregate;
        }

        $lastQuoteDate = (clone $quotesBaseQuery)->max('issue_date');
        $lastSaleDate = (clone $issuedDocumentsBaseQuery)->max('issue_date');
        $lastReceiptDate = (clone $receiptsBaseQuery)
            ->where('status', SalesDocumentReceipt::STATUS_ISSUED)
            ->max('receipt_date');

        return [
            'customer' => $customer,
            'contacts' => $customer->contacts,
            'recentQuotes' => $recentQuotes,
            'recentSalesDocuments' => $recentSalesDocuments,
            'recentReceipts' => $recentReceipts,
            'openSalesDocuments' => $openSalesDocuments,
            'kpis' => [
                'total_quotes' => $totalQuotes,
                'total_approved_quotes' => $totalApprovedQuotes,
                'total_approved_quote_value' => $totalApprovedQuoteValue,
                'total_issued_documents' => $totalIssuedDocuments,
                'total_issued_from_quotes' => $totalIssuedFromQuotes,
                'total_issued_sales' => $totalIssuedSales,
                'open_amount' => $openAmount,
                'overdue_open_amount' => $overdueOpenAmount,
                'total_received' => $totalReceived,
                'contacts_count' => (int) $customer->contacts->count(),
                'last_sale_date' => $lastSaleDate,
                'quote_approval_rate' => $quoteApprovalRate,
                'quote_to_issued_rate' => $quoteToIssuedRate,
            ],
            'statementSummary' => [
                'total_debit' => $totalIssuedSales,
                'total_credit' => $totalReceived,
                'balance' => $balance,
                'open_amount' => $openAmount,
            ],
            'paymentStatusCounts' => $paymentStatusCounts,
            'activity' => [
                'last_quote_date' => $lastQuoteDate,
                'last_sale_date' => $lastSaleDate,
                'last_receipt_date' => $lastReceiptDate,
                'created_at' => $customer->created_at?->toDateTimeString(),
                'updated_at' => $customer->updated_at?->toDateTimeString(),
            ],
        ];
    }
}
