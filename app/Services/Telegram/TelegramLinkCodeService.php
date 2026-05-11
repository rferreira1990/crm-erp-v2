<?php

namespace App\Services\Telegram;

use App\Exceptions\Telegram\TelegramLinkException;
use App\Models\TelegramLinkCode;
use App\Models\TelegramUserLink;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class TelegramLinkCodeService
{
    public function generateForUser(User $user): TelegramLinkCode
    {
        $companyId = (int) $user->company_id;
        if ($companyId <= 0) {
            throw new TelegramLinkException('Utilizador sem empresa associada.');
        }

        return DB::transaction(function () use ($user, $companyId): TelegramLinkCode {
            TelegramLinkCode::query()
                ->forCompany($companyId)
                ->where('user_id', (int) $user->id)
                ->whereNull('used_at')
                ->where('expires_at', '>', now())
                ->update([
                    'used_at' => now(),
                ]);

            return TelegramLinkCode::query()->create([
                'company_id' => $companyId,
                'user_id' => (int) $user->id,
                'code' => $this->generateUniqueCode(),
                'expires_at' => now()->addMinutes(10),
                'used_at' => null,
            ]);
        });
    }

    public function getActiveCodeForUser(User $user): ?TelegramLinkCode
    {
        $companyId = (int) $user->company_id;
        if ($companyId <= 0) {
            return null;
        }

        return TelegramLinkCode::query()
            ->forCompany($companyId)
            ->where('user_id', (int) $user->id)
            ->valid()
            ->latest('id')
            ->first();
    }

    public function deactivateActiveCodesForUser(User $user): void
    {
        $companyId = (int) $user->company_id;
        if ($companyId <= 0) {
            return;
        }

        TelegramLinkCode::query()
            ->forCompany($companyId)
            ->where('user_id', (int) $user->id)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->update([
                'used_at' => now(),
            ]);
    }

    public function linkByCode(string $code, int $telegramUserId, string|int|null $telegramChatId = null): TelegramUserLink
    {
        $normalizedCode = strtoupper(trim($code));
        if ($normalizedCode === '') {
            throw new TelegramLinkException('Codigo de ligacao em falta.');
        }

        return DB::transaction(function () use ($normalizedCode, $telegramUserId, $telegramChatId): TelegramUserLink {
            $linkCode = TelegramLinkCode::query()
                ->where('code', $normalizedCode)
                ->lockForUpdate()
                ->first();

            if (! $linkCode) {
                throw new TelegramLinkException('Codigo de ligacao invalido.');
            }

            if ($linkCode->used_at !== null) {
                throw new TelegramLinkException('Codigo de ligacao ja foi utilizado.');
            }

            if ($linkCode->expires_at === null || $linkCode->expires_at->isPast()) {
                throw new TelegramLinkException('Codigo de ligacao expirado.');
            }

            $companyId = (int) $linkCode->company_id;
            $user = User::query()
                ->whereKey((int) $linkCode->user_id)
                ->where('company_id', $companyId)
                ->first();

            if (! $user) {
                throw new TelegramLinkException('Nao foi possivel validar o utilizador do codigo.');
            }

            $existingByTelegram = TelegramUserLink::query()
                ->where('telegram_user_id', $telegramUserId)
                ->lockForUpdate()
                ->first();

            if (
                $existingByTelegram
                && $existingByTelegram->is_active
                && (
                    (int) $existingByTelegram->company_id !== $companyId
                    || (int) $existingByTelegram->user_id !== (int) $user->id
                )
            ) {
                throw new TelegramLinkException('Esta conta Telegram ja esta ligada a outro utilizador.');
            }

            TelegramUserLink::query()
                ->forCompany($companyId)
                ->where('user_id', (int) $user->id)
                ->where('is_active', true)
                ->when(
                    $existingByTelegram !== null,
                    fn ($query) => $query->where('id', '!=', (int) $existingByTelegram->id)
                )
                ->update([
                    'is_active' => false,
                ]);

            $chatId = $telegramChatId === null ? null : (string) $telegramChatId;

            if ($existingByTelegram) {
                $existingByTelegram->forceFill([
                    'company_id' => $companyId,
                    'user_id' => (int) $user->id,
                    'telegram_chat_id' => $chatId,
                    'is_active' => true,
                    'linked_at' => $existingByTelegram->linked_at ?? now(),
                    'last_seen_at' => now(),
                ])->save();

                $link = $existingByTelegram;
            } else {
                $link = TelegramUserLink::query()->create([
                    'company_id' => $companyId,
                    'user_id' => (int) $user->id,
                    'telegram_user_id' => $telegramUserId,
                    'telegram_chat_id' => $chatId,
                    'is_active' => true,
                    'linked_at' => now(),
                    'last_seen_at' => now(),
                ]);
            }

            $linkCode->forceFill([
                'used_at' => now(),
            ])->save();

            $link->loadMissing(['company:id,name', 'user:id,name,company_id']);

            return $link;
        });
    }

    private function generateUniqueCode(): string
    {
        for ($i = 0; $i < 10; $i++) {
            $code = $this->randomCode(6);

            $exists = TelegramLinkCode::query()
                ->where('code', $code)
                ->exists();

            if (! $exists) {
                return $code;
            }
        }

        throw new TelegramLinkException('Nao foi possivel gerar codigo de ligacao. Tente novamente.');
    }

    private function randomCode(int $length): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $maxIndex = strlen($alphabet) - 1;
        $value = '';

        for ($i = 0; $i < $length; $i++) {
            $value .= $alphabet[random_int(0, $maxIndex)];
        }

        return $value;
    }
}
