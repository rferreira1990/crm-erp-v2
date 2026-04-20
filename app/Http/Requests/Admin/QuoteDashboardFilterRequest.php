<?php

namespace App\Http\Requests\Admin;

use App\Models\Quote;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class QuoteDashboardFilterRequest extends FormRequest
{
    /**
     * @var list<string>
     */
    private const PERIOD_OPTIONS = [
        'today',
        'this_week',
        'this_month',
        'this_quarter',
        'this_year',
        'custom',
    ];

    protected function prepareForValidation(): void
    {
        $this->merge([
            'period' => strtolower(trim((string) $this->input('period', 'this_year'))),
            'status' => $this->normalizeNullableString($this->input('status')),
            'customer_id' => $this->normalizeNullableInteger($this->input('customer_id')),
            'assigned_user_id' => $this->normalizeNullableInteger($this->input('assigned_user_id')),
            'date_from' => $this->normalizeNullableString($this->input('date_from')),
            'date_to' => $this->normalizeNullableString($this->input('date_to')),
        ]);
    }

    public function authorize(): bool
    {
        $user = $this->user();

        return $user
            && $user->isCompanyUser()
            && $user->can('company.quotes.view');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'period' => ['required', 'string', Rule::in(self::PERIOD_OPTIONS)],
            'status' => ['nullable', 'string', Rule::in(Quote::statuses())],
            'customer_id' => ['nullable', 'integer', 'min:1'],
            'assigned_user_id' => ['nullable', 'integer', 'min:1'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if ($this->input('period') !== 'custom') {
                return;
            }

            if ($this->input('date_from') === null) {
                $validator->errors()->add('date_from', 'A data inicial e obrigatoria no periodo personalizado.');
            }

            if ($this->input('date_to') === null) {
                $validator->errors()->add('date_to', 'A data final e obrigatoria no periodo personalizado.');
            }
        });
    }

    /**
     * @return list<string>
     */
    public static function periodOptions(): array
    {
        return self::PERIOD_OPTIONS;
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
}

