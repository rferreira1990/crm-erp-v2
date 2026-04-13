<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerContactRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $payload = [
            'name' => trim((string) $this->input('name')),
            'email' => $this->normalizeNullableString($this->input('email')),
            'phone' => $this->normalizeNullableString($this->input('phone')),
            'job_title' => $this->normalizeNullableString($this->input('job_title')),
            'notes' => $this->normalizeNullableString($this->input('notes')),
        ];

        if ($this->has('is_primary')) {
            $payload['is_primary'] = $this->boolean('is_primary');
        }

        $this->merge($payload);
    }

    public function authorize(): bool
    {
        $user = $this->user();

        return $user
            && $user->isCompanyUser()
            && $user->can('company.customers.update');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:190'],
            'email' => ['nullable', 'email:rfc', 'max:190'],
            'phone' => ['nullable', 'string', 'max:30'],
            'job_title' => ['nullable', 'string', 'max:190'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'is_primary' => ['sometimes', 'boolean'],
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
}
