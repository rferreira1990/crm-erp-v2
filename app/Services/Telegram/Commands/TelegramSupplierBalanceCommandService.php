<?php

namespace App\Services\Telegram\Commands;

use App\Models\PurchaseDocument;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\TelegramUserLink;
use App\Services\Telegram\TelegramPendingSelectionService;
use Illuminate\Support\Carbon;

class TelegramSupplierBalanceCommandService
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
            return ['message' => 'Use: /fornecedor-saldo TERMO'];
        }

        $companyId = (int) $link->company_id;
        $suppliers = $this->searchSuppliers($companyId, $searchTerm);

        if ($suppliers->isEmpty()) {
            return ['message' => sprintf('Nao encontrei fornecedores para: %s', $searchTerm)];
        }

        if ($suppliers->count() > 1) {
            $this->pendingSelectionService->createSelection(
                link: $link,
                chatId: $chatId,
                type: TelegramPendingSelectionService::TYPE_SUPPLIER_BALANCE,
                payload: ['ids' => $suppliers->pluck('id')->take(5)->values()->all()]
            );

            return ['message' => $this->multipleSuppliersMessage($suppliers)];
        }

        return ['message' => $this->singleSupplierBalanceMessage($suppliers->firstOrFail())];
    }

    /**
     * @return array{message:string}
     */
    public function executeBySupplierId(TelegramUserLink $link, int $supplierId): array
    {
        $companyId = (int) $link->company_id;
        $supplier = Supplier::query()
            ->forCompany($companyId)
            ->whereKey($supplierId)
            ->first();

        if (! $supplier) {
            return ['message' => 'Fornecedor nao encontrado para a selecao indicada.'];
        }

        return ['message' => $this->singleSupplierBalanceMessage($supplier)];
    }

    /**
     * @return \Illuminate\Support\Collection<int, Supplier>
     */
    private function searchSuppliers(int $companyId, string $term): \Illuminate\Support\Collection
    {
        return Supplier::query()
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
     * @param \Illuminate\Support\Collection<int, Supplier> $suppliers
     */
    private function multipleSuppliersMessage(\Illuminate\Support\Collection $suppliers): string
    {
        $lines = ['Encontrei varios fornecedores:', ''];

        foreach ($suppliers->take(5) as $index => $supplier) {
            $lines[] = sprintf(
                '%d) %s%s',
                $index + 1,
                (string) $supplier->name,
                $supplier->nif ? ' | NIF '.$supplier->nif : ''
            );
        }

        $lines[] = '';
        $lines[] = 'Responda com o numero do fornecedor.';

        return implode("\n", $lines);
    }

    private function singleSupplierBalanceMessage(Supplier $supplier): string
    {
        $summary = $this->buildSupplierSummary((int) $supplier->company_id, (int) $supplier->id);

        $lines = [
            'Saldo fornecedor:',
            'Fornecedor: '.$supplier->name,
            'NIF: '.($supplier->nif ?: '-'),
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
    private function buildSupplierSummary(int $companyId, int $supplierId): array
    {
        $documents = PurchaseDocument::query()
            ->forCompany($companyId)
            ->where('supplier_id', $supplierId)
            ->where('status', PurchaseDocument::STATUS_CONFIRMED)
            ->get(['id', 'due_date', 'grand_total']);

        $paidTotals = SupplierPayment::query()
            ->forCompany($companyId)
            ->where('status', SupplierPayment::STATUS_ISSUED)
            ->whereIn('purchase_document_id', $documents->pluck('id')->all())
            ->selectRaw('purchase_document_id, SUM(amount) as paid_total')
            ->groupBy('purchase_document_id')
            ->pluck('paid_total', 'purchase_document_id');

        $openTotal = 0.0;
        $overdueTotal = 0.0;
        $overdueCount = 0;
        $oldestDueDate = null;
        $today = now()->startOfDay();

        foreach ($documents as $document) {
            $grandTotal = (float) $document->grand_total;
            $paidTotal = (float) ($paidTotals->get((int) $document->id, 0));
            $openAmount = round(max($grandTotal - $paidTotal, 0), 2);

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
