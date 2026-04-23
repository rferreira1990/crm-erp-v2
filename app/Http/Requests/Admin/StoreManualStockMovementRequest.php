<?php

namespace App\Http\Requests\Admin;

use App\Models\StockMovement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreManualStockMovementRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'article_id' => $this->normalizeNullableInteger($this->input('article_id')),
            'type' => trim((string) $this->input('type')),
            'quantity' => $this->normalizeNullableNumeric($this->input('quantity')),
            'reason_code' => trim((string) $this->input('reason_code')),
            'notes' => $this->normalizeNullableString($this->input('notes')),
            'movement_date' => $this->normalizeNullableString($this->input('movement_date')),
        ]);
    }

    public function authorize(): bool
    {
        $user = $this->user();

        return $user
            && $user->isCompanyUser()
            && $user->can('company.stock_movements.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'article_id' => ['required', 'integer', 'min:1', 'exists:articles,id'],
            'type' => ['required', Rule::in(StockMovement::manualTypes())],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'reason_code' => ['required', Rule::in(array_keys(StockMovement::reasonLabels()))],
            'notes' => ['nullable', 'string', 'max:5000'],
            'movement_date' => ['nullable', 'date'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $type = (string) $this->input('type');
            $reasonCode = (string) $this->input('reason_code');
            $allowedReasons = StockMovement::reasonCodesForType($type);

            if (! in_array($reasonCode, $allowedReasons, true)) {
                $validator->errors()->add(
                    'reason_code',
                    'O motivo selecionado nao e valido para o tipo de movimento.'
                );
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'article_id.required' => 'Tem de selecionar um artigo.',
            'article_id.exists' => 'O artigo selecionado nao existe.',
            'type.required' => 'Tem de selecionar o tipo de movimento.',
            'type.in' => 'O tipo de movimento selecionado nao e valido.',
            'quantity.required' => 'A quantidade e obrigatoria.',
            'quantity.numeric' => 'A quantidade tem de ser numerica.',
            'quantity.gt' => 'A quantidade tem de ser maior que zero.',
            'reason_code.required' => 'Tem de selecionar um motivo.',
        ];
    }

    private function normalizeNullableInteger(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private function normalizeNullableNumeric(mixed $value): float|int|string|null
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $value;
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
