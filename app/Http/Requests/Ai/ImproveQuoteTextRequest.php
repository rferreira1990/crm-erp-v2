<?php

namespace App\Http\Requests\Ai;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ImproveQuoteTextRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'text' => trim((string) $this->input('text')),
            'quote_id' => $this->normalizeNullableInteger($this->input('quote_id')),
        ]);
    }

    public function authorize(): bool
    {
        $user = $this->user();

        return $user
            && $user->is_active
            && $user->isCompanyUser()
            && $user->can('company.ai.quote_text_improve');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'text' => ['required', 'string', 'max:3000'],
            'quote_id' => [
                'nullable',
                'integer',
                Rule::exists('quotes', 'id')->where(function ($query): void {
                    $query->where('company_id', (int) $this->user()->company_id);
                }),
            ],
        ];
    }

    private function normalizeNullableInteger(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }
}
