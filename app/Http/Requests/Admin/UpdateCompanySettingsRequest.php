<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCompanySettingsRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
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
            'pdf_layout' => $this->normalizePdfLayout($this->input('pdf_layout')),
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
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
            'remove_logo' => ['nullable', 'boolean'],

            'bank_name' => ['nullable', 'string', 'max:190'],
            'iban' => ['nullable', 'string', 'max:40'],
            'bic_swift' => ['nullable', 'string', 'max:20'],
            'pdf_layout' => ['nullable', Rule::in(['classic', 'modern'])],
        ];
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

    private function normalizePdfLayout(mixed $value): string
    {
        $normalized = strtolower(trim((string) $value));

        return in_array($normalized, ['classic', 'modern'], true)
            ? $normalized
            : 'classic';
    }
}
