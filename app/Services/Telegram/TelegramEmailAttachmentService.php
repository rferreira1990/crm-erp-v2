<?php

namespace App\Services\Telegram;

use App\Models\TelegramEmailDraft;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TelegramEmailAttachmentService
{
    public const MAX_FILE_BYTES = 10_485_760; // 10MB

    /**
     * @var array<string, string>
     */
    private array $allowedExtensions = [
        'pdf' => 'application/pdf',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ];

    public function __construct(
        private readonly TelegramBotService $telegramBotService
    ) {
    }

    /**
     * @param array{
     *   file_id:string,
     *   file_size:int|null,
     *   file_name:string|null,
     *   mime_type:string|null,
     *   source:string
     * } $payload
     * @return array{
     *   accepted:bool,
     *   reason:?string,
     *   attachment?:array{original_name:string,path:string,mime:string,size:int}
     * }
     */
    public function handleIncomingAttachment(TelegramEmailDraft $draft, array $payload): array
    {
        $maxBytes = max(1_000_000, (int) config('telegram.email.max_file_bytes', self::MAX_FILE_BYTES));
        $fileId = trim((string) ($payload['file_id'] ?? ''));
        if ($fileId === '') {
            return ['accepted' => false, 'reason' => 'Ficheiro invalido.'];
        }

        $declaredSize = isset($payload['file_size']) ? (int) $payload['file_size'] : null;
        if ($declaredSize !== null && $declaredSize > $maxBytes) {
            return ['accepted' => false, 'reason' => 'Ficheiro acima do limite de 10MB.'];
        }

        $filePath = $this->telegramBotService->getFilePath($fileId);
        if (! $filePath) {
            return ['accepted' => false, 'reason' => 'Nao foi possivel descarregar o ficheiro.'];
        }

        $binary = $this->telegramBotService->downloadFileContents($filePath);
        if (! is_string($binary) || $binary === '') {
            return ['accepted' => false, 'reason' => 'Nao foi possivel descarregar o ficheiro.'];
        }

        $size = strlen($binary);
        if ($size <= 0 || $size > $maxBytes) {
            return ['accepted' => false, 'reason' => 'Ficheiro acima do limite de 10MB.'];
        }

        $detectedMime = $this->detectMime($binary);
        $extension = $this->resolveExtension(
            source: (string) ($payload['source'] ?? 'document'),
            originalName: (string) ($payload['file_name'] ?? ''),
            telegramPath: $filePath,
            detectedMime: $detectedMime
        );

        if ($extension === null || ! isset($this->allowedExtensions[$extension])) {
            return ['accepted' => false, 'reason' => 'Tipo de ficheiro nao permitido.'];
        }

        $canonicalMime = $this->allowedExtensions[$extension];
        if (! $this->isMimeCompatible($canonicalMime, $detectedMime)) {
            return ['accepted' => false, 'reason' => 'Tipo de ficheiro nao permitido.'];
        }

        $storedName = Str::uuid()->toString().'.'.$extension;
        $path = sprintf(
            'companies/%d/telegram-email-attachments/%d/%s',
            (int) $draft->company_id,
            (int) $draft->id,
            $storedName
        );

        Storage::disk('local')->put($path, $binary);

        $originalName = $this->sanitizeOriginalName((string) ($payload['file_name'] ?? ''));
        if ($originalName === '') {
            $originalName = 'telegram_'.$fileId.'.'.$extension;
        }

        return [
            'accepted' => true,
            'reason' => null,
            'attachment' => [
                'original_name' => $originalName,
                'path' => $path,
                'mime' => $canonicalMime,
                'size' => $size,
            ],
        ];
    }

    private function sanitizeOriginalName(string $value): string
    {
        $name = trim($value);
        if ($name === '') {
            return '';
        }

        $name = str_replace(['\\', '/'], '-', $name);
        $name = preg_replace('/[\x00-\x1F\x7F]+/u', '', $name) ?? $name;
        $name = preg_replace('/\s+/u', ' ', $name) ?? $name;

        return trim($name);
    }

    private function detectMime(string $binary): string
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo === false) {
            return 'application/octet-stream';
        }

        $mime = (string) finfo_buffer($finfo, $binary);
        finfo_close($finfo);

        return trim($mime) !== '' ? trim($mime) : 'application/octet-stream';
    }

    private function isMimeCompatible(string $canonical, string $detected): bool
    {
        if ($canonical === $detected) {
            return true;
        }

        // Some systems detect legacy office files as octet-stream.
        if (in_array($canonical, [
            'application/msword',
            'application/vnd.ms-excel',
        ], true) && $detected === 'application/octet-stream') {
            return true;
        }

        return false;
    }

    private function resolveExtension(string $source, string $originalName, string $telegramPath, string $detectedMime): ?string
    {
        $fromName = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
        if ($fromName !== '') {
            return $fromName;
        }

        $fromPath = strtolower((string) pathinfo($telegramPath, PATHINFO_EXTENSION));
        if ($fromPath !== '') {
            return $fromPath;
        }

        if ($source === 'photo' && str_starts_with($detectedMime, 'image/')) {
            return match ($detectedMime) {
                'image/png' => 'png',
                'image/webp' => 'webp',
                default => 'jpg',
            };
        }

        return match ($detectedMime) {
            'application/pdf' => 'pdf',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.ms-excel' => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            default => null,
        };
    }
}
