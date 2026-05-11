<?php

namespace App\Mail\Concerns;

trait BuildsCompanySignature
{
    protected function normalizeSignatureHtml(?string $signatureHtml): ?string
    {
        $value = trim((string) $signatureHtml);

        return $value !== '' ? $value : null;
    }

    protected function normalizeSignatureText(?string $signatureHtml): ?string
    {
        $html = $this->normalizeSignatureHtml($signatureHtml);
        if ($html === null) {
            return null;
        }

        $withNewLines = str_ireplace(['<br>', '<br/>', '<br />'], "\n", $html);
        $text = trim((string) strip_tags($withNewLines));
        $text = preg_replace("/\r\n|\r/u", "\n", $text) ?? $text;
        $text = preg_replace("/\n{3,}/u", "\n\n", $text) ?? $text;

        return trim($text) !== '' ? trim($text) : null;
    }
}
