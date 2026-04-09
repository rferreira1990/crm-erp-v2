<?php

namespace App\Http\Requests\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateEmailSettingsRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $mailFromName = trim(strip_tags((string) $this->input('mail_from_name', '')));
        $mailFromAddress = Str::lower(trim((string) $this->input('mail_from_address', '')));
        $mailReplyToInput = trim((string) $this->input('mail_reply_to', ''));
        $mailReplyTo = $mailReplyToInput !== '' ? Str::lower($mailReplyToInput) : null;
        $appNameInput = trim(strip_tags((string) $this->input('app_name', '')));
        $appName = $appNameInput !== '' ? $appNameInput : null;
        $mailMailer = Str::lower(trim((string) $this->input('mail_mailer', 'smtp')));
        $mailHostInput = trim((string) $this->input('mail_host', ''));
        $mailHost = $mailHostInput !== '' ? Str::lower($mailHostInput) : null;
        $mailPortInput = trim((string) $this->input('mail_port', ''));
        $mailPort = $mailPortInput !== '' ? (int) $mailPortInput : null;
        $mailUsernameInput = trim((string) $this->input('mail_username', ''));
        $mailUsername = $mailUsernameInput !== '' ? $mailUsernameInput : null;
        $mailPasswordInput = (string) $this->input('mail_password', '');
        $mailPassword = trim($mailPasswordInput) !== '' ? $mailPasswordInput : null;
        $mailEncryptionInput = Str::lower(trim((string) $this->input('mail_encryption', '')));
        $mailEncryption = in_array($mailEncryptionInput, ['tls', 'ssl', 'null'], true)
            ? $mailEncryptionInput
            : null;

        $this->merge([
            'mail_mailer' => $mailMailer,
            'mail_host' => $mailHost,
            'mail_port' => $mailPort,
            'mail_username' => $mailUsername,
            'mail_password' => $mailPassword,
            'mail_encryption' => $mailEncryption,
            'mail_from_name' => $mailFromName,
            'mail_from_address' => $mailFromAddress,
            'mail_reply_to' => $mailReplyTo,
            'app_name' => $appName,
        ]);
    }

    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $allowedMailers = array_keys((array) config('mail.mailers', ['smtp' => []]));

        return [
            'mail_mailer' => ['required', 'string', Rule::in($allowedMailers)],
            'mail_host' => ['nullable', 'required_if:mail_mailer,smtp', 'string', 'max:255'],
            'mail_port' => ['nullable', 'required_if:mail_mailer,smtp', 'integer', 'between:1,65535'],
            'mail_username' => ['nullable', 'string', 'max:255'],
            'mail_password' => ['nullable', 'string', 'max:255'],
            'mail_encryption' => ['nullable', 'required_if:mail_mailer,smtp', Rule::in(['tls', 'ssl', 'null'])],
            'mail_from_name' => ['required', 'string', 'max:255'],
            'mail_from_address' => ['required', 'email:rfc,dns', 'max:255'],
            'mail_reply_to' => ['nullable', 'email:rfc,dns', 'max:255'],
            'app_name' => ['nullable', 'string', 'max:255'],
        ];
    }
}
