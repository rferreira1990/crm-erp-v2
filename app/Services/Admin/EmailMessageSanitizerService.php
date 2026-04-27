<?php

namespace App\Services\Admin;

use Illuminate\Support\Str;

class EmailMessageSanitizerService
{
    public function sanitizeHeader(?string $value, int $max = 255): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = $this->normalizeUtf8($value);
        $normalized = preg_replace('/\s+/u', ' ', trim($normalized)) ?: '';

        if ($normalized === '') {
            return null;
        }

        return Str::limit($normalized, $max, '');
    }

    public function sanitizeBodyText(?string $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = $this->normalizeUtf8($value);
        $normalized = str_replace(["\r\n", "\r"], "\n", $normalized);
        $normalized = preg_replace('/[^\P{C}\n\t]/u', '', $normalized) ?: '';
        $normalized = trim($normalized);

        return $normalized !== '' ? Str::limit($normalized, 65535, '') : null;
    }

    public function sanitizeBodyHtml(?string $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalizedInput = $this->normalizeUtf8($value);
        $withoutScripts = preg_replace('/<(script|style)\b[^>]*>.*?<\/\1>/is', '', $normalizedInput) ?? '';
        $normalized = trim($withoutScripts);

        return $normalized !== '' ? Str::limit($normalized, 120000, '') : null;
    }

    public function snippet(?string $textBody, ?string $htmlBody): ?string
    {
        $source = $this->sanitizeBodyText($textBody);

        if (! $source && is_string($htmlBody)) {
            $htmlText = trim(strip_tags($htmlBody));
            $source = $this->sanitizeBodyText($htmlText);
        }

        if (! $source) {
            return null;
        }

        $singleLine = preg_replace('/\s+/u', ' ', $source) ?: '';

        return Str::limit($singleLine, 220, '...');
    }

    private function normalizeUtf8(string $value): string
    {
        $value = str_replace("\0", '', $value);

        if (mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }

        $converted = @mb_convert_encoding($value, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');
        if (is_string($converted) && $converted !== '') {
            $value = $converted;
        }

        $cleaned = @iconv('UTF-8', 'UTF-8//IGNORE', $value);
        if (is_string($cleaned)) {
            $value = $cleaned;
        }

        return $value;
    }
}
