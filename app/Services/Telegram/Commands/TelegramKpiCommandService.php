<?php

namespace App\Services\Telegram\Commands;

use App\Models\PurchaseDocument;
use App\Models\SalesDocument;
use App\Models\SalesDocumentReceipt;
use App\Models\SupplierPayment;
use App\Models\TelegramUserLink;
use Illuminate\Support\Carbon;

class TelegramKpiCommandService
{
    public function execute(TelegramUserLink $link, string $period): string
    {
        $resolved = $this->resolvePeriod($period);
        if ($resolved === null) {
            return 'Use: /kpi hoje ou /kpi mes';
        }

        $companyId = (int) $link->company_id;
        $fromDate = $resolved['from']->toDateString();
        $toDate = $resolved['to']->toDateString();

        $sold = (float) SalesDocument::query()
            ->forCompany($companyId)
            ->where('status', SalesDocument::STATUS_ISSUED)
            ->whereBetween('issue_date', [$fromDate, $toDate])
            ->sum('grand_total');

        $received = (float) SalesDocumentReceipt::query()
            ->forCompany($companyId)
            ->where('status', SalesDocumentReceipt::STATUS_ISSUED)
            ->whereBetween('receipt_date', [$fromDate, $toDate])
            ->sum('amount');

        $receivableSummary = $this->buildReceivableSummary($companyId);
        $payableSummary = $this->buildPayableSummary($companyId);

        $lines = [
            'KPI de '.$resolved['label'].':',
            '',
            'Vendido: '.$this->formatMoney($sold),
            'Recebido: '.$this->formatMoney($received),
            'Em aberto clientes: '.$this->formatMoney($receivableSummary['open_total']),
            'Vencido clientes: '.$this->formatMoney($receivableSummary['overdue_total']).' ('.$receivableSummary['overdue_docs'].' docs)',
            'A pagar fornecedores: '.$this->formatMoney($payableSummary['open_total']),
            'Vencido fornecedores: '.$this->formatMoney($payableSummary['overdue_total']).' ('.$payableSummary['overdue_docs'].' docs)',
        ];

        return implode("\n", $lines);
    }

    /**
     * @return array{label:string,from:Carbon,to:Carbon}|null
     */
    private function resolvePeriod(string $raw): ?array
    {
        $normalized = strtolower(trim($raw));
        $normalized = str_replace(['ã', 'á', 'à'], 'a', $normalized);
        $normalized = str_replace(['ê', 'é'], 'e', $normalized);

        if ($normalized === 'hoje') {
            return [
                'label' => 'hoje',
                'from' => now()->startOfDay(),
                'to' => now()->endOfDay(),
            ];
        }

        if (in_array($normalized, ['mes', 'mês', 'este mes', 'este mês'], true)) {
            return [
                'label' => 'este mes',
                'from' => now()->startOfMonth()->startOfDay(),
                'to' => now()->endOfDay(),
            ];
        }

        return null;
    }

    /**
     * @return array{open_total:float,overdue_total:float,overdue_docs:int}
     */
    private function buildReceivableSummary(int $companyId): array
    {
        $documents = SalesDocument::query()
            ->forCompany($companyId)
            ->where('status', SalesDocument::STATUS_ISSUED)
            ->get(['id', 'grand_total', 'due_date']);

        if ($documents->isEmpty()) {
            return ['open_total' => 0.0, 'overdue_total' => 0.0, 'overdue_docs' => 0];
        }

        $receivedByDocument = SalesDocumentReceipt::query()
            ->forCompany($companyId)
            ->where('status', SalesDocumentReceipt::STATUS_ISSUED)
            ->whereIn('sales_document_id', $documents->pluck('id')->all())
            ->selectRaw('sales_document_id, SUM(amount) as received_total')
            ->groupBy('sales_document_id')
            ->pluck('received_total', 'sales_document_id');

        $openTotal = 0.0;
        $overdueTotal = 0.0;
        $overdueDocs = 0;
        $today = now()->startOfDay();

        foreach ($documents as $document) {
            $openAmount = round((float) $document->grand_total - (float) ($receivedByDocument[(int) $document->id] ?? 0), 2);
            if ($openAmount <= 0) {
                continue;
            }

            $openTotal += $openAmount;

            if ($document->due_date instanceof Carbon && $document->due_date->lt($today)) {
                $overdueTotal += $openAmount;
                $overdueDocs++;
            }
        }

        return [
            'open_total' => round($openTotal, 2),
            'overdue_total' => round($overdueTotal, 2),
            'overdue_docs' => $overdueDocs,
        ];
    }

    /**
     * @return array{open_total:float,overdue_total:float,overdue_docs:int}
     */
    private function buildPayableSummary(int $companyId): array
    {
        $documents = PurchaseDocument::query()
            ->forCompany($companyId)
            ->where('status', PurchaseDocument::STATUS_CONFIRMED)
            ->get(['id', 'grand_total', 'due_date']);

        if ($documents->isEmpty()) {
            return ['open_total' => 0.0, 'overdue_total' => 0.0, 'overdue_docs' => 0];
        }

        $paidByDocument = SupplierPayment::query()
            ->forCompany($companyId)
            ->where('status', SupplierPayment::STATUS_ISSUED)
            ->whereIn('purchase_document_id', $documents->pluck('id')->all())
            ->selectRaw('purchase_document_id, SUM(amount) as paid_total')
            ->groupBy('purchase_document_id')
            ->pluck('paid_total', 'purchase_document_id');

        $openTotal = 0.0;
        $overdueTotal = 0.0;
        $overdueDocs = 0;
        $today = now()->startOfDay();

        foreach ($documents as $document) {
            $openAmount = round((float) $document->grand_total - (float) ($paidByDocument[(int) $document->id] ?? 0), 2);
            if ($openAmount <= 0) {
                continue;
            }

            $openTotal += $openAmount;

            if ($document->due_date instanceof Carbon && $document->due_date->lt($today)) {
                $overdueTotal += $openAmount;
                $overdueDocs++;
            }
        }

        return [
            'open_total' => round($openTotal, 2),
            'overdue_total' => round($overdueTotal, 2),
            'overdue_docs' => $overdueDocs,
        ];
    }

    private function formatMoney(float $amount): string
    {
        return number_format($amount, 2, ',', '.').' €';
    }
}

