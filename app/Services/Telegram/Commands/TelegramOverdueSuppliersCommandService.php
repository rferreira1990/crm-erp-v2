<?php

namespace App\Services\Telegram\Commands;

use App\Models\PurchaseDocument;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\TelegramUserLink;
use Illuminate\Support\Carbon;

class TelegramOverdueSuppliersCommandService
{
    public function execute(TelegramUserLink $link): string
    {
        $companyId = (int) $link->company_id;
        $today = now()->startOfDay();

        $documents = PurchaseDocument::query()
            ->forCompany($companyId)
            ->where('status', PurchaseDocument::STATUS_CONFIRMED)
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', $today->toDateString())
            ->whereNotNull('supplier_id')
            ->get(['id', 'supplier_id', 'grand_total', 'due_date']);

        if ($documents->isEmpty()) {
            return 'Nao existem fornecedores com documentos vencidos.';
        }

        $paidByDocument = SupplierPayment::query()
            ->forCompany($companyId)
            ->where('status', SupplierPayment::STATUS_ISSUED)
            ->whereIn('purchase_document_id', $documents->pluck('id')->all())
            ->selectRaw('purchase_document_id, SUM(amount) as paid_total')
            ->groupBy('purchase_document_id')
            ->pluck('paid_total', 'purchase_document_id');

        $aggregated = [];

        foreach ($documents as $document) {
            $supplierId = (int) $document->supplier_id;
            if ($supplierId <= 0) {
                continue;
            }

            $openAmount = round((float) $document->grand_total - (float) ($paidByDocument[(int) $document->id] ?? 0), 2);
            if ($openAmount <= 0) {
                continue;
            }

            if (! isset($aggregated[$supplierId])) {
                $aggregated[$supplierId] = [
                    'amount' => 0.0,
                    'docs' => 0,
                    'oldest_due' => null,
                ];
            }

            $aggregated[$supplierId]['amount'] += $openAmount;
            $aggregated[$supplierId]['docs']++;

            if ($document->due_date instanceof Carbon) {
                $oldest = $aggregated[$supplierId]['oldest_due'];
                if (! $oldest instanceof Carbon || $document->due_date->lt($oldest)) {
                    $aggregated[$supplierId]['oldest_due'] = $document->due_date->copy();
                }
            }
        }

        if ($aggregated === []) {
            return 'Nao existem fornecedores com documentos vencidos.';
        }

        $supplierNames = Supplier::query()
            ->forCompany($companyId)
            ->whereIn('id', array_keys($aggregated))
            ->pluck('name', 'id');

        uasort($aggregated, fn (array $a, array $b): int => $b['amount'] <=> $a['amount']);
        $top = array_slice($aggregated, 0, 5, true);

        $lines = ['Top fornecedores com vencidos:', ''];

        $index = 1;
        foreach ($top as $supplierId => $row) {
            $oldestDays = null;
            if ($row['oldest_due'] instanceof Carbon) {
                $oldestDays = $today->diffInDays($row['oldest_due']);
            }

            $lines[] = sprintf(
                '%d) %s | %s | %d docs%s',
                $index++,
                (string) ($supplierNames[$supplierId] ?? 'Fornecedor #'.$supplierId),
                $this->formatMoney((float) $row['amount']),
                (int) $row['docs'],
                $oldestDays !== null ? ' | mais antigo: '.$oldestDays.' dias' : ''
            );
        }

        if (count($aggregated) > 5) {
            $lines[] = '';
            $lines[] = 'Mostrei os 5 fornecedores com maior valor vencido.';
        }

        return implode("\n", $lines);
    }

    private function formatMoney(float $amount): string
    {
        return number_format($amount, 2, ',', '.').' €';
    }
}

