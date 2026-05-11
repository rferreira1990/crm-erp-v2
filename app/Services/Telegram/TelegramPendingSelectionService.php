<?php

namespace App\Services\Telegram;

use App\Models\TelegramPendingSelection;
use App\Models\TelegramUserLink;
use Illuminate\Support\Facades\DB;

class TelegramPendingSelectionService
{
    public const TYPE_QUOTE_INFO = 'quote_info';
    public const TYPE_CUSTOMER_BALANCE = 'customer_balance';
    public const TYPE_SUPPLIER_BALANCE = 'supplier_balance';
    public const TYPE_OVERDUE_CUSTOMER_FOLLOWUP = 'overdue_customer_followup';
    public const TYPE_CONSTRUCTION_SITE_DAILY_LOG_CREATE = 'construction_site_daily_log_create';
    public const TYPE_CALENDAR_EVENT_CREATE = 'calendar_event_create';
    public const TYPE_DAILY_LOG_ATTACH_PHOTOS = 'daily_log_attach_photos';

    /**
     * @param array<string, mixed> $payload
     */
    public function createSelection(
        TelegramUserLink $link,
        int|string $chatId,
        string $type,
        array $payload,
        int $ttlMinutes = 10
    ): TelegramPendingSelection {
        $companyId = (int) $link->company_id;
        $userId = (int) $link->user_id;
        $telegramUserId = (int) $link->telegram_user_id;
        $chat = (string) $chatId;
        $expiresAt = now()->addMinutes(max(1, $ttlMinutes));

        return DB::transaction(function () use (
            $companyId,
            $userId,
            $telegramUserId,
            $chat,
            $type,
            $payload,
            $expiresAt
        ): TelegramPendingSelection {
            TelegramPendingSelection::query()
                ->forCompany($companyId)
                ->where('user_id', $userId)
                ->where('telegram_user_id', $telegramUserId)
                ->where('telegram_chat_id', $chat)
                ->where('type', $type)
                ->whereNull('consumed_at')
                ->update([
                    'consumed_at' => now(),
                ]);

            return TelegramPendingSelection::query()->create([
                'company_id' => $companyId,
                'user_id' => $userId,
                'telegram_user_id' => $telegramUserId,
                'telegram_chat_id' => $chat,
                'type' => $type,
                'payload' => $payload,
                'expires_at' => $expiresAt,
            ]);
        });
    }

    /**
     * @return array{
     *   handled: bool,
     *   status?: 'resolved'|'expired'|'invalid',
     *   message?: string,
     *   type?: string,
     *   selected_id?: int,
     *   selection_id?: int,
     *   payload?: array<string,mixed>
     * }|null
     */
    public function consumeNumericReply(TelegramUserLink $link, int|string $chatId, string $text): ?array
    {
        $trimmed = trim($text);
        if (preg_match('/^\d+$/', $trimmed) !== 1) {
            return null;
        }

        $companyId = (int) $link->company_id;
        $userId = (int) $link->user_id;
        $telegramUserId = (int) $link->telegram_user_id;
        $chat = (string) $chatId;
        $choice = (int) $trimmed;

        return DB::transaction(function () use ($companyId, $userId, $telegramUserId, $chat, $choice): ?array {
            /** @var TelegramPendingSelection|null $selection */
            $selection = TelegramPendingSelection::query()
                ->forCompany($companyId)
                ->where('user_id', $userId)
                ->where('telegram_user_id', $telegramUserId)
                ->where('telegram_chat_id', $chat)
                ->whereNull('consumed_at')
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            if (! $selection) {
                return null;
            }

            if ($selection->expires_at->isPast()) {
                $selection->forceFill(['consumed_at' => now()])->save();

                return [
                    'handled' => true,
                    'status' => 'expired',
                    'message' => 'Selecao expirada. Faca o pedido novamente.',
                    'selection_id' => (int) $selection->id,
                ];
            }

            $ids = $this->extractAllowedIds($selection->payload);
            $index = $choice - 1;

            if ($choice <= 0 || ! isset($ids[$index])) {
                return [
                    'handled' => true,
                    'status' => 'invalid',
                    'message' => 'Selecao invalida. Responda com um numero da lista.',
                    'selection_id' => (int) $selection->id,
                ];
            }

            $selectedId = (int) $ids[$index];
            $selection->forceFill(['consumed_at' => now()])->save();

            return [
                'handled' => true,
                'status' => 'resolved',
                'type' => (string) $selection->type,
                'selected_id' => $selectedId,
                'selection_id' => (int) $selection->id,
                'payload' => is_array($selection->payload) ? $selection->payload : [],
            ];
        });
    }

    public function getActiveSelectionByType(
        TelegramUserLink $link,
        int|string $chatId,
        string $type
    ): ?TelegramPendingSelection {
        return TelegramPendingSelection::query()
            ->forCompany((int) $link->company_id)
            ->where('user_id', (int) $link->user_id)
            ->where('telegram_user_id', (int) $link->telegram_user_id)
            ->where('telegram_chat_id', (string) $chatId)
            ->where('type', $type)
            ->active()
            ->orderByDesc('id')
            ->first();
    }

    public function consumeSelection(TelegramPendingSelection $selection): void
    {
        if ($selection->consumed_at !== null) {
            return;
        }

        $selection->forceFill(['consumed_at' => now()])->save();
    }

    /**
     * @param array<string, mixed>|null $payload
     * @return list<int>
     */
    private function extractAllowedIds(?array $payload): array
    {
        if (! is_array($payload)) {
            return [];
        }

        $raw = $payload['ids'] ?? [];
        if (! is_array($raw)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (mixed $id): int => (int) $id,
            $raw
        ), static fn (int $id): bool => $id > 0));
    }
}
