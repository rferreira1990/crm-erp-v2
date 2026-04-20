<?php

namespace App\Services\Admin;

use App\Models\Company;
use Illuminate\Support\Facades\Crypt;
use Throwable;

class CompanyMailSettingsService
{
    public function applyRuntimeConfig(Company $company): void
    {
        if ($company->mail_use_custom_settings) {
            $this->applyCustomCompanyConfig($company);

            return;
        }

        $this->applyPlatformConfig();
    }

    private function applyCustomCompanyConfig(Company $company): void
    {
        $encryption = $this->normalizeEncryption($company->mail_encryption);
        $fromAddress = $this->normalizeEmail((string) $company->mail_from_address)
            ?? $this->normalizeEmail((string) $company->email)
            ?? (string) config('mail.from.address');
        $fromName = trim((string) ($company->mail_from_name ?: $company->name ?: config('mail.from.name')));
        $replyToAddress = $this->normalizeEmail((string) $company->email) ?: $fromAddress;

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => $company->mail_host,
            'mail.mailers.smtp.port' => $company->mail_port,
            'mail.mailers.smtp.username' => $company->mail_username,
            'mail.mailers.smtp.password' => $company->mail_password,
            'mail.mailers.smtp.encryption' => $encryption,
            'mail.from.address' => $fromAddress,
            'mail.from.name' => $fromName,
            'mail.reply_to' => [
                'address' => $replyToAddress,
                'name' => $fromName,
            ],
        ]);
    }

    private function applyPlatformConfig(): void
    {
        $mailer = (string) setting('mail.mailer', (string) env('MAIL_MAILER', 'smtp'));
        $host = setting('mail.host', env('MAIL_HOST'));
        $port = setting('mail.port', env('MAIL_PORT'));
        $username = setting('mail.username', env('MAIL_USERNAME'));
        $password = $this->resolvePlatformPassword();
        $encryption = $this->normalizeEncryption(setting('mail.encryption', env('MAIL_ENCRYPTION')));
        $fromAddress = (string) setting('mail.from_address', (string) env('MAIL_FROM_ADDRESS'));
        $fromName = (string) setting('mail.from_name', (string) env('MAIL_FROM_NAME', config('app.name')));
        $replyTo = $this->normalizeEmail((string) setting('mail.reply_to', ''));

        config([
            'mail.default' => $mailer !== '' ? $mailer : 'smtp',
            'mail.mailers.smtp.host' => $host,
            'mail.mailers.smtp.port' => is_numeric((string) $port) ? (int) $port : $port,
            'mail.mailers.smtp.username' => $username,
            'mail.mailers.smtp.password' => $password,
            'mail.mailers.smtp.encryption' => $encryption,
            'mail.from.address' => $fromAddress,
            'mail.from.name' => $fromName,
            'mail.reply_to' => $replyTo
                ? ['address' => $replyTo, 'name' => $fromName]
                : null,
        ]);
    }

    private function resolvePlatformPassword(): ?string
    {
        $encryptedOrPlain = setting('mail.password');

        if (! is_string($encryptedOrPlain) || trim($encryptedOrPlain) === '') {
            $fallback = env('MAIL_PASSWORD');

            return is_string($fallback) && $fallback !== '' ? $fallback : null;
        }

        try {
            return Crypt::decryptString($encryptedOrPlain);
        } catch (Throwable) {
            return $encryptedOrPlain;
        }
    }

    private function normalizeEncryption(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = strtolower(trim((string) $value));

        if ($normalized === '' || $normalized === 'none' || $normalized === 'null') {
            return null;
        }

        return in_array($normalized, ['tls', 'ssl'], true) ? $normalized : null;
    }

    private function normalizeEmail(string $value): ?string
    {
        $normalized = strtolower(trim($value));

        if ($normalized === '' || ! filter_var($normalized, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        return $normalized;
    }
}

