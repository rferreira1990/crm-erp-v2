<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class TestCompanySmtpRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'test_email' => $this->normalizeNullableString($this->input('test_email')),
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
            'test_email' => ['nullable', 'email:rfc', 'max:190'],
        ];
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = strtolower(trim((string) $value));

        return $normalized !== '' ? $normalized : null;
    }
}

