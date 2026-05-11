<?php

namespace App\Http\Requests\Calendar;

use App\Models\CalendarEvent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCalendarEventRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'title' => $this->normalizeNullableString($this->input('title')),
            'description' => $this->normalizeNullableString($this->input('description')),
            'type' => strtolower(trim((string) $this->input('type', CalendarEvent::TYPE_TASK))),
            'status' => strtolower(trim((string) $this->input('status', CalendarEvent::STATUS_PENDING))),
            'priority' => strtolower(trim((string) $this->input('priority', CalendarEvent::PRIORITY_NORMAL))),
            'starts_at' => $this->normalizeNullableString($this->input('starts_at')),
            'ends_at' => $this->normalizeNullableString($this->input('ends_at')),
            'all_day' => $this->boolean('all_day'),
            'user_id' => $this->normalizeNullableInteger($this->input('user_id')),
            'customer_id' => $this->normalizeNullableInteger($this->input('customer_id')),
            'supplier_id' => $this->normalizeNullableInteger($this->input('supplier_id')),
            'construction_site_id' => $this->normalizeNullableInteger($this->input('construction_site_id')),
            'quote_id' => $this->normalizeNullableInteger($this->input('quote_id')),
        ]);
    }

    public function authorize(): bool
    {
        $user = $this->user();

        return $user
            && $user->isCompanyUser()
            && $user->can('company.calendar.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:190'],
            'description' => ['nullable', 'string', 'max:5000'],
            'type' => ['required', 'string', Rule::in(CalendarEvent::types())],
            'status' => ['required', 'string', Rule::in(CalendarEvent::statuses())],
            'priority' => ['required', 'string', Rule::in(CalendarEvent::priorities())],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'all_day' => ['required', 'boolean'],
            'user_id' => ['nullable', 'integer', 'min:1'],
            'customer_id' => ['nullable', 'integer', 'min:1'],
            'supplier_id' => ['nullable', 'integer', 'min:1'],
            'construction_site_id' => ['nullable', 'integer', 'min:1'],
            'quote_id' => ['nullable', 'integer', 'min:1'],
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

