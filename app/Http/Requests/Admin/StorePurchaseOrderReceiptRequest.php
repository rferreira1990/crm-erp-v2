<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StorePurchaseOrderReceiptRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $items = $this->input('items', []);
        if (! is_array($items)) {
            $items = [];
        }

        $normalizedItems = [];
        foreach ($items as $key => $line) {
            if (! is_array($line)) {
                continue;
            }

            $normalizedItems[$key] = [
                'purchase_order_item_id' => isset($line['purchase_order_item_id']) ? (int) $line['purchase_order_item_id'] : null,
                'received_quantity' => isset($line['received_quantity']) ? (float) $line['received_quantity'] : 0,
                'notes' => $this->normalizeNullableString($line['notes'] ?? null),
            ];
        }

        $this->merge([
            'receipt_date' => trim((string) $this->input('receipt_date')),
            'supplier_document_number' => $this->normalizeNullableString($this->input('supplier_document_number')),
            'supplier_document_date' => $this->normalizeNullableString($this->input('supplier_document_date')),
            'notes' => $this->normalizeNullableString($this->input('notes')),
            'internal_notes' => $this->normalizeNullableString($this->input('internal_notes')),
            'items' => $normalizedItems,
        ]);
    }

    public function authorize(): bool
    {
        $user = $this->user();

        return $user
            && $user->isCompanyUser()
            && $user->can('company.purchase_order_receipts.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'receipt_date' => ['required', 'date'],
            'supplier_document_number' => ['nullable', 'string', 'max:120'],
            'supplier_document_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'internal_notes' => ['nullable', 'string', 'max:5000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.purchase_order_item_id' => ['nullable', 'integer', 'min:1'],
            'items.*.received_quantity' => ['required', 'numeric', 'min:0'],
            'items.*.notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'receipt_date.required' => 'A data da rececao e obrigatoria.',
            'receipt_date.date' => 'A data da rececao e invalida.',
            'items.required' => 'Tem de indicar as linhas da rececao.',
            'items.array' => 'As linhas da rececao sao invalidas.',
            'items.min' => 'Tem de existir pelo menos uma linha.',
            'items.*.received_quantity.required' => 'A quantidade recebida e obrigatoria.',
            'items.*.received_quantity.numeric' => 'A quantidade recebida tem de ser numerica.',
            'items.*.received_quantity.min' => 'A quantidade recebida nao pode ser negativa.',
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
