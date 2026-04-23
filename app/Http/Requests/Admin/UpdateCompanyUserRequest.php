<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCompanyUserRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'hourly_cost' => $this->normalizeNullableNumeric($this->input('hourly_cost')),
        ]);
    }

    public function authorize(): bool
    {
        $user = $this->user();

        return $user
            && $user->isCompanyUser()
            && $user->can('company.users.update');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'role' => ['required', 'string', Rule::in(User::companyRoleNames())],
            'hourly_cost' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    private function normalizeNullableNumeric(mixed $value): float|string|null
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);
        if ($normalized === '') {
            return null;
        }

        return str_replace(',', '.', $normalized);
    }
}
