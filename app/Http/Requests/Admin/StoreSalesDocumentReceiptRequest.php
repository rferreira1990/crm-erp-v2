<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreSalesDocumentReceiptRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'receipt_date' => trim((string) $this->input('receipt_date')),
            'payment_method_id' => $this->normalizeNullableInteger($this->input('payment_method_id')),
            'amount' => $this->normalizeNullableNumeric($this->input('amount')),
            'notes' => $this->normalizeNullableString($this->input('notes')),
        ]);
    }

    public function authorize(): bool
    {
        $user = $this->user();

        return $user
            && $user->isCompanyUser()
            && $user->can('company.sales_document_receipts.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'receipt_date' => ['required', 'date'],
            'payment_method_id' => ['nullable', 'integer', 'min:1'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'receipt_date.required' => 'A data do recibo e obrigatoria.',
            'amount.required' => 'O valor do recibo e obrigatorio.',
            'amount.gt' => 'O valor do recibo deve ser superior a zero.',
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

    private function normalizeNullableNumeric(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        $normalized = str_replace([' ', "\u{00A0}"], '', trim((string) $value));
        $normalized = str_replace(',', '.', $normalized);

        return is_numeric($normalized) ? (float) $normalized : null;
    }
}
