<?php

namespace App\Services\Telegram;

use App\Models\TelegramUserLink;
use RuntimeException;

class TelegramUserResolverService
{
    public function resolveByTelegramUserId(int $telegramUserId): ?TelegramUserLink
    {
        return TelegramUserLink::query()
            ->active()
            ->where('telegram_user_id', $telegramUserId)
            ->with(['company:id,name', 'user:id,name,company_id'])
            ->first();
    }

    public function ensureLinked(int $telegramUserId): TelegramUserLink
    {
        $link = $this->resolveByTelegramUserId($telegramUserId);

        if (! $link) {
            throw new RuntimeException('Conta Telegram nao ligada ao ERP.');
        }

        return $link;
    }
}
