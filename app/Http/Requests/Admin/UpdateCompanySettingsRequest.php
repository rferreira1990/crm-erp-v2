<?php

namespace App\Http\Requests\Admin;

use App\Models\Company;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateCompanySettingsRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $mailUseCustom = $this->boolean('mail_use_custom_settings');
        $mailPasswordInput = (string) $this->input('mail_password', '');
        $mailPassword = trim($mailPasswordInput) !== '' ? $mailPasswordInput : null;
        $mailEncryptionInput = strtolower(trim((string) $this->input('mail_encryption', '')));
        $mailEncryption = in_array($mailEncryptionInput, ['tls', 'ssl', 'none'], true)
            ? $mailEncryptionInput
            : null;

        $this->merge([
            'address' => $this->normalizeNullableString($this->input('address')),
            'locality' => $this->normalizeNullableString($this->input('locality')),
            'city' => $this->normalizeNullableString($this->input('city')),
            'postal_code' => $this->normalizeNullableString($this->input('postal_code')),
            'phone' => $this->normalizeNullableString($this->input('phone')),
            'mobile' => $this->normalizeNullableString($this->input('mobile')),
            'email' => $this->normalizeNullableString($this->input('email')),
            'website' => $this->normalizeNullableString($this->input('website')),
            'remove_logo' => $this->boolean('remove_logo'),
            'bank_name' => $this->normalizeNullableString($this->input('bank_name')),
            'iban' => $this->normalizeIban($this->input('iban')),
            'bic_swift' => $this->normalizeNullableString($this->input('bic_swift')),
            'mail_use_custom_settings' => $mailUseCustom,
            'mail_from_name' => $this->normalizeNullableString($this->input('mail_from_name')),
            'mail_from_address' => $this->normalizeNullableString($this->input('mail_from_address')),
            'mail_host' => $this->normalizeNullableString($this->input('mail_host')),
            'mail_port' => $this->normalizeNullableInteger($this->input('mail_port')),
            'mail_username' => $this->normalizeNullableString($this->input('mail_username')),
            'mail_password' => $mailPassword,
            'mail_encryption' => $mailEncryption,
        ]);
    }

    public function authorize(): bool
    {
        $user = $this->user();

        return $user
            && $user->isCompanyUser()
            && $user->can('company.settings.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'address' => ['nullable', 'string', 'max:255'],
            'locality' => ['nullable', 'string', 'max:120'],
            'city' => ['nullable', 'string', 'max:120'],
            'postal_code' => ['nullable', 'regex:/^\d{4}-\d{3}$/'],
            'phone' => ['nullable', 'string', 'max:30'],
            'mobile' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email:rfc', 'max:190'],
            'website' => ['nullable', 'url:http,https', 'max:255'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:3072'],
            'remove_logo' => ['nullable', 'boolean'],

            'bank_name' => ['nullable', 'string', 'max:190'],
            'iban' => ['nullable', 'string', 'max:40'],
            'bic_swift' => ['nullable', 'string', 'max:20'],

            'mail_use_custom_settings' => ['required', 'boolean'],
            'mail_from_name' => ['nullable', 'required_if:mail_use_custom_settings,1', 'string', 'max:255'],
            'mail_from_address' => ['nullable', 'required_if:mail_use_custom_settings,1', 'email:rfc', 'max:190'],
            'mail_host' => ['nullable', 'required_if:mail_use_custom_settings,1', 'string', 'max:255'],
            'mail_port' => ['nullable', 'required_if:mail_use_custom_settings,1', 'integer', 'between:1,65535'],
            'mail_username' => ['nullable', 'string', 'max:255'],
            'mail_password' => ['nullable', 'string', 'max:255'],
            'mail_encryption' => ['nullable', 'required_if:mail_use_custom_settings,1', Rule::in(['tls', 'ssl', 'none'])],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if (! $this->boolean('mail_use_custom_settings')) {
                return;
            }

            $company = $this->user()?->company;
            $hasExistingPassword = $company instanceof Company && is_string($company->mail_password) && trim($company->mail_password) !== '';
            $hasNewPassword = is_string($this->input('mail_password')) && trim((string) $this->input('mail_password')) !== '';

            if (! $hasExistingPassword && ! $hasNewPassword) {
                $validator->errors()->add('mail_password', 'A password SMTP e obrigatoria na primeira configuracao de conta propria.');
            }
        });
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }

    private function normalizeNullableInteger(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private function normalizeIban(mixed $value): ?string
    {
        $normalized = $this->normalizeNullableString($value);

        if ($normalized === null) {
            return null;
        }

        return strtoupper((string) preg_replace('/\s+/', '', $normalized));
    }
}

