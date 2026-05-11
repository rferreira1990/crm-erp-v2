<?php

namespace App\Services\Telegram\Commands;

use App\Models\ConstructionSite;
use App\Models\ConstructionSiteLog;
use App\Models\ConstructionSiteLogImage;
use App\Services\Telegram\TelegramBotService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TelegramConstructionSiteDailyLogAttachmentService
{
    private const MAX_FILE_SIZE_BYTES = 5242880; // 5 MB

    /**
     * @var array<string, string>
     */
    private const ALLOWED_MIME_MAP = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public function __construct(
        private readonly TelegramBotService $telegramBotService
    ) {
    }

    /**
     * @param list<array{file_id:string,file_size:int|null,source:string}> $images
     * @return array{attached:int,rejected:int}
     */
    public function attachImages(ConstructionSite $site, ConstructionSiteLog $log, array $images): array
    {
        if ($images === []) {
            return ['attached' => 0, 'rejected' => 0];
        }

        $attached = 0;
        $rejected = 0;
        $companyId = (int) $site->company_id;
        $directory = 'construction-sites/'.$companyId.'/'.$site->id.'/logs/'.$log->id.'/images';
        $nextSortOrder = ((int) $log->images()->max('sort_order')) + 1;
        $hasPrimary = $log->images()->where('is_primary', true)->exists();

        foreach ($images as $image) {
            $fileId = trim((string) ($image['file_id'] ?? ''));
            if ($fileId === '') {
                $rejected++;

                continue;
            }

            $declaredSize = is_int($image['file_size'] ?? null) ? (int) $image['file_size'] : null;
            if ($declaredSize !== null && $declaredSize > self::MAX_FILE_SIZE_BYTES) {
                $rejected++;

                continue;
            }

            $filePath = $this->telegramBotService->getFilePath($fileId);
            if ($filePath === null) {
                $rejected++;

                continue;
            }

            $binary = $this->telegramBotService->downloadFileContents($filePath);
            if ($binary === null || $binary === '') {
                $rejected++;

                continue;
            }

            $realSize = strlen($binary);
            if ($realSize <= 0 || $realSize > self::MAX_FILE_SIZE_BYTES) {
                $rejected++;

                continue;
            }

            $detectedMime = $this->detectMimeType($binary);
            if ($detectedMime === null || ! isset(self::ALLOWED_MIME_MAP[$detectedMime])) {
                $rejected++;

                continue;
            }

            if (@getimagesizefromstring($binary) === false) {
                $rejected++;

                continue;
            }

            $extension = self::ALLOWED_MIME_MAP[$detectedMime];
            $filename = Str::uuid()->toString().'.'.$extension;
            $storedPath = $directory.'/'.$filename;

            Storage::disk('local')->put($storedPath, $binary);

            $isPrimary = ! $hasPrimary;
            if ($isPrimary) {
                $hasPrimary = true;
            }

            ConstructionSiteLogImage::query()->create([
                'construction_site_log_id' => $log->id,
                'company_id' => $companyId,
                'original_name' => 'telegram_'.$fileId.'.'.$extension,
                'file_path' => $storedPath,
                'mime_type' => $detectedMime,
                'file_size' => $realSize,
                'sort_order' => $nextSortOrder,
                'is_primary' => $isPrimary,
            ]);

            $nextSortOrder++;
            $attached++;
        }

        if ($rejected > 0) {
            Log::info('Telegram daily-log image attachments rejected.', [
                'company_id' => $companyId,
                'construction_site_id' => (int) $site->id,
                'construction_site_log_id' => (int) $log->id,
                'rejected_count' => $rejected,
            ]);
        }

        return [
            'attached' => $attached,
            'rejected' => $rejected,
        ];
    }

    private function detectMimeType(string $binary): ?string
    {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->buffer($binary);
        if (! is_string($mime)) {
            return null;
        }

        return trim($mime);
    }
}
