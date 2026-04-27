<?php

namespace App\Services\Admin;

use App\Models\EmailAccount;
use App\Models\EmailMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class EmailInboxSyncService
{
    private const MAX_ATTACHMENT_BYTES = 10485760;

    public function __construct(
        private readonly EmailAccountConnectionService $connectionService,
        private readonly EmailMessageSanitizerService $sanitizerService
    ) {
    }

    /**
     * @return array{processed:int,created:int,updated:int}
     */
    public function syncLatestInbox(EmailAccount $account, int $limit = 100): array
    {
        if (! $account->is_active) {
            throw new \RuntimeException('A conta de email esta inativa.');
        }

        $companyId = (int) $account->company_id;
        $limit = max(1, min(500, $limit));

        try {
            $remoteMessages = $this->connectionService->fetchLatestInbox($account, $limit);
        } catch (Throwable $exception) {
            $account->forceFill([
                'last_error' => Str::limit($exception->getMessage(), 5000, ''),
            ])->save();

            throw $exception;
        }

        $created = 0;
        $updated = 0;
        $processed = 0;
        $storedPaths = [];

        try {
            DB::transaction(function () use (
                $account,
                $companyId,
                $remoteMessages,
                &$created,
                &$updated,
                &$processed,
                &$storedPaths
            ): void {
                foreach ($remoteMessages as $remoteMessage) {
                    $message = EmailMessage::query()->updateOrCreate(
                        [
                            'company_id' => $companyId,
                            'email_account_id' => (int) $account->id,
                            'folder' => (string) ($remoteMessage['folder'] ?? 'INBOX'),
                            'message_uid' => (string) ($remoteMessage['uid'] ?? ''),
                        ],
                        [
                            'message_id' => $this->sanitizerService->sanitizeHeader($remoteMessage['message_id'] ?? null, 255),
                            'from_email' => $this->sanitizeEmail($remoteMessage['from_email'] ?? null),
                            'from_name' => $this->sanitizerService->sanitizeHeader($remoteMessage['from_name'] ?? null, 190),
                            'to_email' => $this->sanitizeEmail($remoteMessage['to_email'] ?? null),
                            'to_name' => $this->sanitizerService->sanitizeHeader($remoteMessage['to_name'] ?? null, 190),
                            'subject' => $this->sanitizerService->sanitizeHeader($remoteMessage['subject'] ?? null, 255),
                            'body_text' => $this->sanitizerService->sanitizeBodyText($remoteMessage['body_text'] ?? null),
                            'body_html' => $this->sanitizerService->sanitizeBodyHtml($remoteMessage['body_html'] ?? null),
                            'snippet' => $this->sanitizerService->snippet(
                                $remoteMessage['body_text'] ?? null,
                                $remoteMessage['body_html'] ?? null
                            ),
                            'received_at' => $remoteMessage['received_at'] ?? null,
                            'is_seen' => (bool) ($remoteMessage['is_seen'] ?? false),
                            'has_attachments' => (bool) ($remoteMessage['has_attachments'] ?? false),
                            'raw_headers' => is_array($remoteMessage['raw_headers'] ?? null)
                                ? $remoteMessage['raw_headers']
                                : null,
                            'synced_at' => now(),
                        ]
                    );

                    if ($message->wasRecentlyCreated) {
                        $created++;
                    } else {
                        $updated++;
                    }

                    $this->syncAttachments($message, $remoteMessage['attachments'] ?? [], $storedPaths);
                    $processed++;
                }

                $account->forceFill([
                    'last_synced_at' => now(),
                    'last_error' => null,
                ])->save();
            });
        } catch (Throwable $exception) {
            foreach ($storedPaths as $path) {
                Storage::disk('local')->delete($path);
            }

            throw $exception;
        }

        return [
            'processed' => $processed,
            'created' => $created,
            'updated' => $updated,
        ];
    }

    /**
     * @param array<int, array{
     *   filename?: mixed,
     *   mime_type?: mixed,
     *   size_bytes?: mixed,
     *   content_id?: mixed,
     *   is_inline?: mixed,
     *   content_base64?: mixed
     * }> $attachments
     * @param array<int, string> $storedPaths
     */
    private function syncAttachments(EmailMessage $message, array $attachments, array &$storedPaths): void
    {
        $existing = $message->attachments()->get(['storage_path']);
        foreach ($existing as $existingAttachment) {
            $existingPath = (string) ($existingAttachment->storage_path ?? '');
            if ($existingPath !== '' && str_starts_with(trim($existingPath, '/'), 'email/')) {
                Storage::disk('local')->delete($existingPath);
            }
        }

        $message->attachments()->delete();

        foreach ($attachments as $attachment) {
            $filename = $this->sanitizerService->sanitizeHeader((string) ($attachment['filename'] ?? ''), 190);
            if (! $filename) {
                continue;
            }

            $storagePath = $this->persistAttachmentPayload(
                $message,
                $filename,
                $attachment['content_base64'] ?? null,
                $attachment['size_bytes'] ?? null
            );
            if (is_string($storagePath) && $storagePath !== '') {
                $storedPaths[] = $storagePath;
            }

            $message->attachments()->create([
                'company_id' => (int) $message->company_id,
                'filename' => $filename,
                'mime_type' => $this->sanitizerService->sanitizeHeader($attachment['mime_type'] ?? null, 120),
                'size_bytes' => is_numeric($attachment['size_bytes'] ?? null) ? max(0, (int) $attachment['size_bytes']) : null,
                'storage_path' => $storagePath,
                'content_id' => $this->sanitizerService->sanitizeHeader($attachment['content_id'] ?? null, 190),
                'is_inline' => (bool) ($attachment['is_inline'] ?? false),
            ]);
        }
    }

    private function persistAttachmentPayload(
        EmailMessage $message,
        string $filename,
        mixed $contentBase64,
        mixed $sizeBytes
    ): ?string {
        if (! is_string($contentBase64) || trim($contentBase64) === '') {
            return null;
        }

        $size = is_numeric($sizeBytes) ? max(0, (int) $sizeBytes) : null;
        if ($size !== null && $size > self::MAX_ATTACHMENT_BYTES) {
            return null;
        }

        $binary = base64_decode($contentBase64, true);
        if (! is_string($binary) || $binary === '') {
            return null;
        }

        if (strlen($binary) > self::MAX_ATTACHMENT_BYTES) {
            return null;
        }

        $sanitizedFilename = $this->sanitizeFilenameForPath($filename);
        $hash = substr(sha1($message->id.'|'.$message->message_uid.'|'.$filename.'|'.strlen($binary)), 0, 16);
        $path = sprintf(
            'email/%d/messages/%d/attachments/%s-%s',
            (int) $message->company_id,
            (int) $message->id,
            $hash,
            $sanitizedFilename
        );

        Storage::disk('local')->put($path, $binary);

        return $path;
    }

    private function sanitizeFilenameForPath(string $filename): string
    {
        $normalized = preg_replace('/[\/\\\\]+/', '-', trim($filename)) ?: '';
        $normalized = str_replace("\0", '', $normalized);
        $normalized = trim($normalized, ". \t\n\r\0\x0B");
        $normalized = $normalized !== '' ? $normalized : 'attachment.bin';

        $extension = strtolower(pathinfo($normalized, PATHINFO_EXTENSION));
        $base = pathinfo($normalized, PATHINFO_FILENAME);
        $safeBase = Str::slug($base);
        $safeBase = $safeBase !== '' ? $safeBase : 'attachment';

        $safeExtension = preg_replace('/[^a-z0-9]/', '', $extension) ?: '';

        return $safeExtension !== ''
            ? Str::limit($safeBase, 80, '').'.'.Str::limit($safeExtension, 10, '')
            : Str::limit($safeBase, 90, '');
    }

    private function sanitizeEmail(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = strtolower(trim($value));

        return filter_var($normalized, FILTER_VALIDATE_EMAIL) ? $normalized : null;
    }
}
