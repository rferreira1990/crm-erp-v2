<?php

namespace App\Http\Requests\Admin;

use App\Models\EmailAccount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmailAccountRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $folder = strtoupper(trim((string) $this->input('imap_folder', 'INBOX')));
        $imapPassword = trim((string) $this->input('imap_password'));
        $smtpPassword = trim((string) $this->input('smtp_password'));

        $this->merge([
            'name' => trim((string) $this->input('name')),
            'email' => strtolower(trim((string) $this->input('email'))),
            'imap_host' => trim((string) $this->input('imap_host')),
            'imap_port' => $this->normalizeNullableInteger($this->input('imap_port')) ?? 993,
            'imap_encryption' => strtolower(trim((string) $this->input('imap_encryption', EmailAccount::ENCRYPTION_SSL))),
            'imap_username' => trim((string) $this->input('imap_username')),
            'imap_password' => $imapPassword !== '' ? $imapPassword : null,
            'imap_folder' => $folder !== '' ? $folder : 'INBOX',
            'is_active' => $this->boolean('is_active'),
            'smtp_use_custom_settings' => true,
            'smtp_from_name' => $this->normalizeNullableString($this->input('smtp_from_name')),
            'smtp_from_address' => $this->normalizeNullableEmail($this->input('smtp_from_address')),
            'smtp_host' => $this->normalizeNullableString($this->input('smtp_host')),
            'smtp_port' => $this->normalizeNullableInteger($this->input('smtp_port')),
            'smtp_encryption' => $this->normalizeNullableString($this->input('smtp_encryption')),
            'smtp_username' => $this->normalizeNullableString($this->input('smtp_username')),
            'smtp_password' => $smtpPassword !== '' ? $smtpPassword : null,
        ]);
    }

    public function authorize(): bool
    {
        $user = $this->user();

        return $user
            && $user->isCompanyUser()
            && $user->can('company.email_accounts.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:190'],
            'imap_host' => ['required', 'string', 'max:190'],
            'imap_port' => ['required', 'integer', 'between:1,65535'],
            'imap_encryption' => ['required', 'string', Rule::in(EmailAccount::encryptions())],
            'imap_username' => ['required', 'string', 'max:190'],
            'imap_password' => ['nullable', 'string', 'max:255'],
            'imap_folder' => ['required', 'string', 'max:120'],
            'is_active' => ['required', 'boolean'],
            'smtp_use_custom_settings' => ['required', 'boolean', 'accepted'],
            'smtp_from_name' => ['nullable', 'string', 'max:190'],
            'smtp_from_address' => ['required', 'email:rfc', 'max:190'],
            'smtp_host' => ['required', 'string', 'max:190'],
            'smtp_port' => ['required', 'integer', 'between:1,65535'],
            'smtp_encryption' => ['required', 'string', Rule::in(EmailAccount::encryptions())],
            'smtp_username' => ['required', 'string', 'max:190'],
            'smtp_password' => ['nullable', 'string', 'max:255'],
        ];
    }

    private function normalizeNullableInteger(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }

    private function normalizeNullableEmail(mixed $value): ?string
    {
        $normalized = $this->normalizeNullableString($value);

        return $normalized !== null ? strtolower($normalized) : null;
    }
}
