<?php

namespace App\Services\Telegram\Commands;

use App\Models\Customer;
use App\Models\SalesDocument;
use App\Models\SalesDocumentReceipt;
use App\Models\TelegramUserLink;
use App\Services\Telegram\TelegramPendingSelectionService;
use Illuminate\Support\Carbon;

class TelegramOverdueCustomersCommandService
{
    public function __construct(
        private readonly TelegramPendingSelectionService $pendingSelectionService
    ) {
    }

    public function execute(TelegramUserLink $link, int|string $chatId): string
    {
        $companyId = (int) $link->company_id;
        $today = now()->startOfDay();

        $documents = SalesDocument::query()
            ->forCompany($companyId)
            ->where('status', SalesDocument::STATUS_ISSUED)
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', $today->toDateString())
            ->whereNotNull('customer_id')
            ->get(['id', 'customer_id', 'grand_total', 'due_date']);

        if ($documents->isEmpty()) {
            return 'Nao existem clientes com documentos vencidos.';
        }

        $receivedByDocument = SalesDocumentReceipt::query()
            ->forCompany($companyId)
            ->where('status', SalesDocumentReceipt::STATUS_ISSUED)
            ->whereIn('sales_document_id', $documents->pluck('id')->all())
            ->selectRaw('sales_document_id, SUM(amount) as received_total')
            ->groupBy('sales_document_id')
            ->pluck('received_total', 'sales_document_id');

        $aggregated = [];

        foreach ($documents as $document) {
            $customerId = (int) $document->customer_id;
            if ($customerId <= 0) {
                continue;
            }

            $openAmount = round((float) $document->grand_total - (float) ($receivedByDocument[(int) $document->id] ?? 0), 2);
            if ($openAmount <= 0) {
                continue;
            }

            if (! isset($aggregated[$customerId])) {
                $aggregated[$customerId] = [
                    'amount' => 0.0,
                    'docs' => 0,
                    'oldest_due' => null,
                ];
            }

            $aggregated[$customerId]['amount'] += $openAmount;
            $aggregated[$customerId]['docs']++;

            if ($document->due_date instanceof Carbon) {
                $oldest = $aggregated[$customerId]['oldest_due'];
                if (! $oldest instanceof Carbon || $document->due_date->lt($oldest)) {
                    $aggregated[$customerId]['oldest_due'] = $document->due_date->copy();
                }
            }
        }

        if ($aggregated === []) {
            return 'Nao existem clientes com documentos vencidos.';
        }

        $customerNames = Customer::query()
            ->forCompany($companyId)
            ->whereIn('id', array_keys($aggregated))
            ->pluck('name', 'id');

        uasort($aggregated, fn (array $a, array $b): int => $b['amount'] <=> $a['amount']);
        $top = array_slice($aggregated, 0, 5, true);

        $lines = ['Top clientes com vencidos:', ''];

        $index = 1;
        foreach ($top as $customerId => $row) {
            $oldestDays = null;
            if ($row['oldest_due'] instanceof Carbon) {
                $oldestDays = $today->diffInDays($row['oldest_due']);
            }

            $lines[] = sprintf(
                '%d) %s | %s | %d docs%s',
                $index++,
                (string) ($customerNames[$customerId] ?? 'Cliente #'.$customerId),
                $this->formatMoney((float) $row['amount']),
                (int) $row['docs'],
                $oldestDays !== null ? ' | mais antigo: '.$oldestDays.' dias' : ''
            );
        }

        if (count($aggregated) > 5) {
            $lines[] = '';
            $lines[] = 'Mostrei os 5 clientes com maior valor vencido.';
        }

        $this->pendingSelectionService->createSelection(
            link: $link,
            chatId: $chatId,
            type: TelegramPendingSelectionService::TYPE_OVERDUE_CUSTOMER_FOLLOWUP,
            payload: [
                'ids' => array_map(static fn (int|string $id): int => (int) $id, array_keys($top)),
            ],
            ttlMinutes: 10
        );

        $lines[] = '';
        $lines[] = 'Para criar tarefa de follow-up: CRIAR FOLLOW-UP N';

        return implode("\n", $lines);
    }

    private function formatMoney(float $amount): string
    {
        return number_format($amount, 2, ',', '.').' €';
    }
}
