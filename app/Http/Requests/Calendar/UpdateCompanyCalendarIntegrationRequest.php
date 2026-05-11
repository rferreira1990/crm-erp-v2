<?php

namespace App\Http\Requests\Calendar;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCompanyCalendarIntegrationRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $password = trim((string) $this->input('password'));

        $this->merge([
            'name' => $this->normalizeNullableString($this->input('name')),
            'username' => $this->normalizeNullableString($this->input('username')),
            'password' => $password !== '' ? $password : null,
            'base_url' => $this->normalizeNullableString($this->input('base_url')),
            'calendar_url' => $this->normalizeNullableString($this->input('calendar_url')),
            'provider' => strtolower(trim((string) $this->input('provider', 'caldav'))),
            'is_active' => $this->boolean('is_active'),
            'sync_enabled' => $this->boolean('sync_enabled'),
            'user_id' => $this->normalizeNullableInteger($this->input('user_id')),
        ]);
    }

    public function authorize(): bool
    {
        $user = $this->user();

        return $user
            && $user->isCompanyUser()
            && $user->can('company.calendar.integrations.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'provider' => ['required', 'string', Rule::in(['caldav'])],
            'name' => ['required', 'string', 'max:120'],
            'username' => ['required', 'string', 'max:190'],
            'password' => ['nullable', 'string', 'max:255'],
            'base_url' => ['required', 'string', 'max:255', 'url:https'],
            'calendar_url' => ['required', 'string', 'max:255', 'url:https'],
            'is_active' => ['required', 'boolean'],
            'sync_enabled' => ['required', 'boolean'],
            'user_id' => ['nullable', 'integer', 'min:1'],
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
}

