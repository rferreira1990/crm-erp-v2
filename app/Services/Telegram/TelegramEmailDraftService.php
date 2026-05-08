<?php

namespace App\Services\Telegram;

use App\Models\TelegramEmailDraft;
use App\Models\TelegramUserLink;
use Illuminate\Support\Facades\DB;

class TelegramEmailDraftService
{
    public const DEFAULT_TTL_MINUTES = 30;
    public const MAX_ATTACHMENTS = 5;
    public const MAX_SUBJECT_LENGTH = 180;
    public const MAX_BODY_LENGTH = 5000;

    public function startDraft(TelegramUserLink $link, int|string $chatId, string $toEmail): TelegramEmailDraft
    {
        $normalizedEmail = $this->normalizeEmail($toEmail);

        return DB::transaction(function () use ($link, $chatId, $normalizedEmail): TelegramEmailDraft {
            $this->expireStaleDrafts($link, $chatId);
            $this->cancelActiveDraft($link, $chatId);

            return TelegramEmailDraft::query()->create([
                'company_id' => (int) $link->company_id,
                'user_id' => (int) $link->user_id,
                'telegram_user_id' => (int) $link->telegram_user_id,
                'telegram_chat_id' => (string) $chatId,
                'status' => TelegramEmailDraft::STATUS_COLLECTING_SUBJECT,
                'to_email' => $normalizedEmail,
                'attachments' => [],
                'expires_at' => now()->addMinutes($this->ttlMinutes()),
            ]);
        });
    }

    public function getActiveDraft(TelegramUserLink $link, int|string $chatId): ?TelegramEmailDraft
    {
        $this->expireStaleDrafts($link, $chatId);

        return TelegramEmailDraft::query()
            ->forCompany((int) $link->company_id)
            ->where('user_id', (int) $link->user_id)
            ->where('telegram_user_id', (int) $link->telegram_user_id)
            ->where('telegram_chat_id', (string) $chatId)
            ->active()
            ->orderByDesc('id')
            ->first();
    }

    public function getLatestDraft(TelegramUserLink $link, int|string $chatId): ?TelegramEmailDraft
    {
        return TelegramEmailDraft::query()
            ->forCompany((int) $link->company_id)
            ->where('user_id', (int) $link->user_id)
            ->where('telegram_user_id', (int) $link->telegram_user_id)
            ->where('telegram_chat_id', (string) $chatId)
            ->orderByDesc('id')
            ->first();
    }

    public function setSubject(TelegramEmailDraft $draft, string $subject): TelegramEmailDraft
    {
        $draft->forceFill([
            'subject' => $this->sanitizeSubject($subject),
            'status' => TelegramEmailDraft::STATUS_COLLECTING_BODY,
            'expires_at' => now()->addMinutes($this->ttlMinutes()),
        ])->save();

        return $draft->refresh();
    }

    public function setBody(TelegramEmailDraft $draft, string $body): TelegramEmailDraft
    {
        $cleanBody = $this->sanitizeBody($body);

        $draft->forceFill([
            'original_body' => $cleanBody,
            'selected_body' => $cleanBody,
            'status' => TelegramEmailDraft::STATUS_COLLECTING_ATTACHMENTS,
            'expires_at' => now()->addMinutes($this->ttlMinutes()),
        ])->save();

        return $draft->refresh();
    }

    /**
     * @param array{
     *  original_name:string,
     *  path:string,
     *  mime:string,
     *  size:int
     * } $attachment
     */
    public function addAttachment(TelegramEmailDraft $draft, array $attachment): TelegramEmailDraft
    {
        $attachments = $this->attachments($draft);
        if (count($attachments) >= $this->maxAttachments()) {
            return $draft;
        }

        $attachments[] = [
            'original_name' => (string) $attachment['original_name'],
            'path' => (string) $attachment['path'],
            'mime' => (string) $attachment['mime'],
            'size' => (int) $attachment['size'],
        ];

        $draft->forceFill([
            'attachments' => $attachments,
            'expires_at' => now()->addMinutes($this->ttlMinutes()),
        ])->save();

        return $draft->refresh();
    }

    public function moveToAiOffer(TelegramEmailDraft $draft): TelegramEmailDraft
    {
        $draft->forceFill([
            'status' => TelegramEmailDraft::STATUS_AI_IMPROVEMENT_OFFER,
            'expires_at' => now()->addMinutes($this->ttlMinutes()),
        ])->save();

        return $draft->refresh();
    }

    public function setImprovedBody(TelegramEmailDraft $draft, string $improvedBody): TelegramEmailDraft
    {
        $draft->forceFill([
            'improved_body' => $this->sanitizeBody($improvedBody),
            'status' => TelegramEmailDraft::STATUS_AI_IMPROVEMENT_PREVIEW,
            'expires_at' => now()->addMinutes($this->ttlMinutes()),
        ])->save();

        return $draft->refresh();
    }

    public function selectOriginalBody(TelegramEmailDraft $draft): TelegramEmailDraft
    {
        $draft->forceFill([
            'selected_body' => $this->sanitizeBody((string) ($draft->original_body ?? '')),
            'status' => TelegramEmailDraft::STATUS_AWAITING_FINAL_APPROVAL,
            'expires_at' => now()->addMinutes($this->ttlMinutes()),
        ])->save();

        return $draft->refresh();
    }

    public function selectImprovedBody(TelegramEmailDraft $draft): TelegramEmailDraft
    {
        $body = trim((string) $draft->improved_body);
        if ($body === '') {
            return $this->selectOriginalBody($draft);
        }

        $draft->forceFill([
            'selected_body' => $this->sanitizeBody($body),
            'status' => TelegramEmailDraft::STATUS_AWAITING_FINAL_APPROVAL,
            'expires_at' => now()->addMinutes($this->ttlMinutes()),
        ])->save();

        return $draft->refresh();
    }

    public function markSent(TelegramEmailDraft $draft): TelegramEmailDraft
    {
        $draft->forceFill([
            'status' => TelegramEmailDraft::STATUS_SENT,
            'sent_at' => now(),
            'expires_at' => now(),
        ])->save();

        return $draft->refresh();
    }

    public function cancelDraft(TelegramEmailDraft $draft): TelegramEmailDraft
    {
        $draft->forceFill([
            'status' => TelegramEmailDraft::STATUS_CANCELLED,
            'cancelled_at' => now(),
            'expires_at' => now(),
        ])->save();

        return $draft->refresh();
    }

    public function cancelActiveDraft(TelegramUserLink $link, int|string $chatId): bool
    {
        $draft = TelegramEmailDraft::query()
            ->forCompany((int) $link->company_id)
            ->where('user_id', (int) $link->user_id)
            ->where('telegram_user_id', (int) $link->telegram_user_id)
            ->where('telegram_chat_id', (string) $chatId)
            ->active()
            ->orderByDesc('id')
            ->first();

        if (! $draft) {
            return false;
        }

        $this->cancelDraft($draft);

        return true;
    }

    public function expireStaleDrafts(TelegramUserLink $link, int|string $chatId): int
    {
        return TelegramEmailDraft::query()
            ->forCompany((int) $link->company_id)
            ->where('user_id', (int) $link->user_id)
            ->where('telegram_user_id', (int) $link->telegram_user_id)
            ->where('telegram_chat_id', (string) $chatId)
            ->whereNotIn('status', [
                TelegramEmailDraft::STATUS_SENT,
                TelegramEmailDraft::STATUS_CANCELLED,
                TelegramEmailDraft::STATUS_EXPIRED,
            ])
            ->where('expires_at', '<=', now())
            ->update([
                'status' => TelegramEmailDraft::STATUS_EXPIRED,
                'expires_at' => now(),
            ]);
    }

    public function isExpired(TelegramEmailDraft $draft): bool
    {
        return $draft->status === TelegramEmailDraft::STATUS_EXPIRED || $draft->expires_at?->isPast() === true;
    }

    public function buildPreview(TelegramEmailDraft $draft): string
    {
        $attachments = $this->attachments($draft);
        $lines = [
            'Preview do email:',
            '',
            'Para: '.$draft->to_email,
            'Assunto: '.((string) ($draft->subject ?? '-')),
            '',
            'Texto:',
            (string) ($draft->selected_body ?? $draft->original_body ?? '-'),
            '',
            'Anexos:',
        ];

        if ($attachments === []) {
            $lines[] = '- (sem anexos)';
        } else {
            foreach ($attachments as $attachment) {
                $name = trim((string) ($attachment['original_name'] ?? 'anexo'));
                $lines[] = '- '.($name !== '' ? $name : 'anexo');
            }
        }

        $lines[] = '';
        $lines[] = 'Responder:';
        $lines[] = 'OK ENVIAR';
        $lines[] = 'CANCELAR';

        return implode("\n", $lines);
    }

    /**
     * @return list<array{original_name:string,path:string,mime:string,size:int}>
     */
    public function attachments(TelegramEmailDraft $draft): array
    {
        if (! is_array($draft->attachments)) {
            return [];
        }

        $normalized = [];

        foreach ($draft->attachments as $attachment) {
            if (! is_array($attachment)) {
                continue;
            }

            $path = trim((string) ($attachment['path'] ?? ''));
            if ($path === '') {
                continue;
            }

            $normalized[] = [
                'original_name' => trim((string) ($attachment['original_name'] ?? 'anexo')),
                'path' => $path,
                'mime' => trim((string) ($attachment['mime'] ?? 'application/octet-stream')),
                'size' => max(0, (int) ($attachment['size'] ?? 0)),
            ];
        }

        return $normalized;
    }

    private function normalizeEmail(string $email): string
    {
        $normalized = strtolower(trim($email));
        if ($normalized === '' || ! filter_var($normalized, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Email invalido.');
        }

        return $normalized;
    }

    private function sanitizeSubject(string $subject): string
    {
        $value = trim($subject);
        $value = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value) ?? $value;
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
        $value = trim($value);

        if ($value === '') {
            throw new \InvalidArgumentException('Assunto obrigatorio.');
        }

        if (mb_strlen($value) > self::MAX_SUBJECT_LENGTH) {
            $value = mb_substr($value, 0, self::MAX_SUBJECT_LENGTH);
        }

        return $value;
    }

    private function sanitizeBody(string $body): string
    {
        $value = trim($body);
        $value = strip_tags($value);
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/u', ' ', $value) ?? $value;
        $value = preg_replace("/\r\n|\r/u", "\n", $value) ?? $value;
        $value = preg_replace("/\n{3,}/u", "\n\n", $value) ?? $value;
        $value = trim($value);

        if ($value === '') {
            throw new \InvalidArgumentException('Texto do email obrigatorio.');
        }

        if (mb_strlen($value) > self::MAX_BODY_LENGTH) {
            $value = mb_substr($value, 0, self::MAX_BODY_LENGTH);
            $value = rtrim($value);
        }

        return $value;
    }

    private function ttlMinutes(): int
    {
        return max(5, (int) config('telegram.email.draft_ttl_minutes', self::DEFAULT_TTL_MINUTES));
    }

    private function maxAttachments(): int
    {
        return max(1, (int) config('telegram.email.max_attachments', self::MAX_ATTACHMENTS));
    }
}
