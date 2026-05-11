<?php

namespace App\Services\Telegram\Commands;

use App\Models\CalendarEvent;
use App\Models\Customer;
use App\Models\SalesDocument;
use App\Models\SalesDocumentReceipt;
use App\Models\TelegramPendingSelection;
use App\Models\TelegramUserLink;
use App\Models\User;
use App\Services\Telegram\TelegramPendingSelectionService;

class TelegramOverdueCustomerFollowupDraftService
{
    public function __construct(
        private readonly TelegramPendingSelectionService $pendingSelectionService
    ) {
    }

    /**
     * @return array{status:string,message:string}
     */
    public function prepareFromChoice(TelegramUserLink $link, int|string $chatId, int $choice): array
    {
        if ($choice <= 0) {
            return [
                'status' => 'invalid',
                'message' => 'Numero invalido. Use: CRIAR FOLLOW-UP N',
            ];
        }

        $actorUser = $this->resolveActorUser($link);
        if (! $actorUser || ! $actorUser->can('company.calendar.create')) {
            return [
                'status' => 'forbidden',
                'message' => 'Nao tem permissao para criar eventos na agenda.',
            ];
        }

        $selection = $this->pendingSelectionService->getActiveSelectionByType(
            $link,
            $chatId,
            TelegramPendingSelectionService::TYPE_OVERDUE_CUSTOMER_FOLLOWUP
        );

        if (! $selection instanceof TelegramPendingSelection) {
            return [
                'status' => 'missing_selection',
                'message' => 'Selecao expirada ou inexistente. Execute /clientes-vencidos novamente.',
            ];
        }

        $payload = is_array($selection->payload) ? $selection->payload : [];
        $ids = $this->extractIds($payload);
        $index = $choice - 1;

        if (! isset($ids[$index])) {
            return [
                'status' => 'invalid_choice',
                'message' => 'Opcao invalida. Use o numero da lista atual de clientes vencidos.',
            ];
        }

        $companyId = (int) $link->company_id;
        $customerId = (int) $ids[$index];
        $customer = Customer::query()
            ->forCompany($companyId)
            ->whereKey($customerId)
            ->first();

        if (! $customer) {
            return [
                'status' => 'customer_not_found',
                'message' => 'Cliente nao encontrado. Execute /clientes-vencidos novamente.',
            ];
        }

        $summary = $this->buildCustomerOverdueSummary($companyId, $customerId);

        $startsAt = now()->addDay()->setTime(9, 0, 0);
        $endsAt = now()->addDay()->setTime(9, 30, 0);

        $calendarPayload = [
            'title' => 'Follow-up cobranca - '.$customer->name,
            'description' => sprintf(
                "Follow-up de cobranca via Telegram.\nCliente: %s\nValor vencido: %s\nDocumentos vencidos: %d",
                $customer->name,
                $this->formatMoney($summary['overdue_amount']),
                $summary['overdue_count']
            ),
            'type' => CalendarEvent::TYPE_REMINDER,
            'status' => CalendarEvent::STATUS_PENDING,
            'priority' => CalendarEvent::PRIORITY_HIGH,
            'starts_at' => $startsAt->toDateTimeString(),
            'ends_at' => $endsAt->toDateTimeString(),
            'all_day' => false,
            'user_id' => (int) $actorUser->id,
            'customer_id' => (int) $customer->id,
            'supplier_id' => null,
            'construction_site_id' => null,
            'quote_id' => null,
        ];

        $this->pendingSelectionService->createSelection(
            link: $link,
            chatId: $chatId,
            type: TelegramPendingSelectionService::TYPE_CALENDAR_EVENT_CREATE,
            payload: ['calendar_event' => $calendarPayload],
            ttlMinutes: 10
        );

        return [
            'status' => 'pending_confirmation',
            'message' => implode("\n", [
                'Vou criar este evento:',
                '',
                'Titulo: '.$calendarPayload['title'],
                'Tipo: lembrete',
                'Data/hora: '.$startsAt->format('d/m/Y H:i'),
                'Cliente: '.$customer->name,
                'Prioridade: alta',
                '',
                'Responder:',
                'OK CRIAR',
                'CANCELAR',
            ]),
        ];
    }

    private function resolveActorUser(TelegramUserLink $link): ?User
    {
        return User::query()
            ->where('is_super_admin', false)
            ->where('is_active', true)
            ->where('company_id', (int) $link->company_id)
            ->whereKey((int) $link->user_id)
            ->first();
    }

    /**
     * @param array<string,mixed> $payload
     * @return list<int>
     */
    private function extractIds(array $payload): array
    {
        $rawIds = $payload['ids'] ?? null;
        if (! is_array($rawIds)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (mixed $id): int => (int) $id,
            $rawIds
        ), static fn (int $id): bool => $id > 0));
    }

    /**
     * @return array{overdue_amount:float,overdue_count:int}
     */
    private function buildCustomerOverdueSummary(int $companyId, int $customerId): array
    {
        $today = now()->startOfDay();

        $documents = SalesDocument::query()
            ->forCompany($companyId)
            ->where('customer_id', $customerId)
            ->where('status', SalesDocument::STATUS_ISSUED)
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', $today->toDateString())
            ->get(['id', 'grand_total']);

        if ($documents->isEmpty()) {
            return ['overdue_amount' => 0.0, 'overdue_count' => 0];
        }

        $receivedByDocument = SalesDocumentReceipt::query()
            ->forCompany($companyId)
            ->where('status', SalesDocumentReceipt::STATUS_ISSUED)
            ->whereIn('sales_document_id', $documents->pluck('id')->all())
            ->selectRaw('sales_document_id, SUM(amount) as received_total')
            ->groupBy('sales_document_id')
            ->pluck('received_total', 'sales_document_id');

        $overdueAmount = 0.0;
        $overdueCount = 0;

        foreach ($documents as $document) {
            $openAmount = round((float) $document->grand_total - (float) ($receivedByDocument[(int) $document->id] ?? 0), 2);
            if ($openAmount <= 0) {
                continue;
            }

            $overdueAmount += $openAmount;
            $overdueCount++;
        }

        return [
            'overdue_amount' => round($overdueAmount, 2),
            'overdue_count' => $overdueCount,
        ];
    }

    private function formatMoney(float $amount): string
    {
        return number_format($amount, 2, ',', '.').' €';
    }
}
