<?php

namespace App\Services\Telegram\Commands;

use App\Models\Customer;
use App\Models\SalesDocument;
use App\Models\SalesDocumentReceipt;
use App\Models\TelegramUserLink;
use App\Services\Telegram\TelegramPendingSelectionService;
use Illuminate\Support\Carbon;

class TelegramCustomerBalanceCommandService
{
    public function __construct(
        private readonly TelegramPendingSelectionService $pendingSelectionService
    ) {
    }

    /**
     * @return array{message:string}
     */
    public function execute(TelegramUserLink $link, int|string $chatId, string $term): array
    {
        $searchTerm = trim($term);
        if ($searchTerm === '') {
            return ['message' => 'Use: /cliente-saldo TERMO'];
        }

        $companyId = (int) $link->company_id;
        $customers = $this->searchCustomers($companyId, $searchTerm);

        if ($customers->isEmpty()) {
            return ['message' => sprintf('Nao encontrei clientes para: %s', $searchTerm)];
        }

        if ($customers->count() > 1) {
            $this->pendingSelectionService->createSelection(
                link: $link,
                chatId: $chatId,
                type: TelegramPendingSelectionService::TYPE_CUSTOMER_BALANCE,
                payload: ['ids' => $customers->pluck('id')->take(5)->values()->all()]
            );

            return ['message' => $this->multipleCustomersMessage($customers)];
        }

        return ['message' => $this->singleCustomerBalanceMessage($customers->firstOrFail())];
    }

    /**
     * @return array{message:string}
     */
    public function executeByCustomerId(TelegramUserLink $link, int $customerId): array
    {
        $companyId = (int) $link->company_id;
        $customer = Customer::query()
            ->forCompany($companyId)
            ->whereKey($customerId)
            ->first();

        if (! $customer) {
            return ['message' => 'Cliente nao encontrado para a selecao indicada.'];
        }

        return ['message' => $this->singleCustomerBalanceMessage($customer)];
    }

    /**
     * @return \Illuminate\Support\Collection<int, Customer>
     */
    private function searchCustomers(int $companyId, string $term): \Illuminate\Support\Collection
    {
        return Customer::query()
            ->forCompany($companyId)
            ->where(function ($query) use ($term): void {
                $like = '%'.$term.'%';
                $query->where('name', 'like', $like)
                    ->orWhere('nif', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('phone', 'like', $like)
                    ->orWhere('mobile', 'like', $like);
            })
            ->orderBy('name')
            ->limit(5)
            ->get(['id', 'company_id', 'name', 'nif']);
    }

    /**
     * @param \Illuminate\Support\Collection<int, Customer> $customers
     */
    private function multipleCustomersMessage(\Illuminate\Support\Collection $customers): string
    {
        $lines = ['Encontrei varios clientes:', ''];

        foreach ($customers->take(5) as $index => $customer) {
            $lines[] = sprintf(
                '%d) %s%s',
                $index + 1,
                (string) $customer->name,
                $customer->nif ? ' | NIF '.$customer->nif : ''
            );
        }

        $lines[] = '';
        $lines[] = 'Responda com o numero do cliente.';

        return implode("\n", $lines);
    }

    private function singleCustomerBalanceMessage(Customer $customer): string
    {
        $summary = $this->buildCustomerSummary((int) $customer->company_id, (int) $customer->id);

        $lines = [
            'Saldo cliente:',
            'Cliente: '.$customer->name,
            'NIF: '.($customer->nif ?: '-'),
            'Saldo em aberto: '.$this->formatMoney($summary['open_balance']),
            'Valor vencido: '.$this->formatMoney($summary['overdue_amount']),
            'Docs vencidos: '.$summary['overdue_count'],
            'Documento vencido mais antigo: '.($summary['oldest_overdue_days'] !== null ? $summary['oldest_overdue_days'].' dias' : '-'),
        ];

        return implode("\n", $lines);
    }

    /**
     * @return array{
     *   open_balance:float,
     *   overdue_amount:float,
     *   overdue_count:int,
     *   oldest_overdue_days:?int
     * }
     */
    private function buildCustomerSummary(int $companyId, int $customerId): array
    {
        $documents = SalesDocument::query()
            ->forCompany($companyId)
            ->where('customer_id', $customerId)
            ->where('status', SalesDocument::STATUS_ISSUED)
            ->get(['id', 'due_date', 'grand_total']);

        $receivedTotals = SalesDocumentReceipt::query()
            ->forCompany($companyId)
            ->where('status', SalesDocumentReceipt::STATUS_ISSUED)
            ->whereIn('sales_document_id', $documents->pluck('id')->all())
            ->selectRaw('sales_document_id, SUM(amount) as received_total')
            ->groupBy('sales_document_id')
            ->pluck('received_total', 'sales_document_id');

        $openTotal = 0.0;
        $overdueTotal = 0.0;
        $overdueCount = 0;
        $oldestDueDate = null;
        $today = now()->startOfDay();

        foreach ($documents as $document) {
            $grandTotal = (float) $document->grand_total;
            $receivedTotal = (float) ($receivedTotals->get((int) $document->id, 0));
            $openAmount = round(max($grandTotal - $receivedTotal, 0), 2);

            if ($openAmount <= 0) {
                continue;
            }

            $openTotal += $openAmount;

            if ($document->due_date instanceof Carbon && $document->due_date->lt($today)) {
                $overdueTotal += $openAmount;
                $overdueCount++;

                if ($oldestDueDate === null || $document->due_date->lt($oldestDueDate)) {
                    $oldestDueDate = $document->due_date->copy();
                }
            }
        }

        $oldestOverdueDays = $oldestDueDate ? $today->diffInDays($oldestDueDate) : null;

        return [
            'open_balance' => round($openTotal, 2),
            'overdue_amount' => round($overdueTotal, 2),
            'overdue_count' => $overdueCount,
            'oldest_overdue_days' => $oldestOverdueDays,
        ];
    }

    private function formatMoney(float $amount): string
    {
        return number_format($amount, 2, ',', '.').' €';
    }
}
