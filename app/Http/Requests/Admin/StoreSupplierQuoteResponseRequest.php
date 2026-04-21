<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreSupplierQuoteResponseRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'shipping_cost' => $this->normalizeNullableNumeric($this->input('shipping_cost')),
            'delivery_days' => $this->normalizeNullableInteger($this->input('delivery_days')),
            'supplier_document_date' => $this->normalizeNullableString($this->input('supplier_document_date')),
            'supplier_document_number' => $this->normalizeNullableString($this->input('supplier_document_number')),
            'commercial_discount_text' => $this->normalizeNullableString($this->input('commercial_discount_text')),
            'payment_terms_text' => $this->normalizeNullableString($this->input('payment_terms_text')),
            'valid_until' => $this->normalizeNullableString($this->input('valid_until')),
            'notes' => $this->normalizeNullableString($this->input('notes')),
            'received_at' => $this->normalizeNullableString($this->input('received_at')) ?? now()->toDateTimeString(),
            'items' => $this->normalizeItems($this->input('items', [])),
        ]);
    }

    public function authorize(): bool
    {
        $user = $this->user();

        return $user
            && $user->isCompanyUser()
            && $user->can('company.rfq.update');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'shipping_cost' => ['nullable', 'numeric', 'min:0'],
            'delivery_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'supplier_document_date' => ['nullable', 'date'],
            'supplier_document_number' => ['nullable', 'string', 'max:120'],
            'commercial_discount_text' => ['nullable', 'string', 'max:255'],
            'payment_terms_text' => ['nullable', 'string', 'max:255'],
            'valid_until' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'supplier_document_pdf' => ['nullable', 'file', 'mimetypes:application/pdf', 'max:12288'],
            'received_at' => ['required', 'date'],
            'items' => ['required', 'array', 'min:1', 'max:400'],
            'items.*.supplier_quote_request_item_id' => ['required', 'integer'],
            'items.*.is_responded' => ['required', 'boolean'],
            'items.*.is_available' => ['required', 'boolean'],
            'items.*.quantity' => ['nullable', 'numeric', 'min:0'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.discount_percent' => ['nullable', 'numeric', 'between:0,100'],
            'items.*.is_alternative' => ['required', 'boolean'],
            'items.*.alternative_description' => ['nullable', 'string', 'max:5000'],
            'items.*.brand' => ['nullable', 'string', 'max:120'],
            'items.*.notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $hasAnyRespondedItem = false;

            foreach ((array) $this->input('items', []) as $index => $item) {
                $prefix = "items.$index";
                $isResponded = (bool) ($item['is_responded'] ?? false);
                $isAvailable = (bool) ($item['is_available'] ?? true);
                $quantity = $item['quantity'] ?? null;
                $unitPrice = $item['unit_price'] ?? null;
                $isAlternative = (bool) ($item['is_alternative'] ?? false);
                $alternativeDescription = trim((string) ($item['alternative_description'] ?? ''));
                $brand = trim((string) ($item['brand'] ?? ''));

                if (! $isResponded) {
                    continue;
                }

                $hasAnyRespondedItem = true;

                if ($isAvailable) {
                    if ($quantity === null || (float) $quantity <= 0) {
                        $validator->errors()->add("$prefix.quantity", 'Quantidade obrigatoria para item respondido e disponivel.');
                    }

                    if ($unitPrice === null || (float) $unitPrice < 0) {
                        $validator->errors()->add("$prefix.unit_price", 'Preco unitario obrigatorio para item respondido e disponivel.');
                    }
                }

                if ($isAlternative && $alternativeDescription === '' && $brand === '') {
                    $validator->errors()->add("$prefix.alternative_description", 'Indique descricao ou marca quando a linha for alternativa.');
                }
            }

            if (! $hasAnyRespondedItem) {
                $validator->errors()->add('items', 'Registe pelo menos uma linha na resposta do fornecedor.');
            }
        });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function respondedItems(): array
    {
        return collect((array) $this->input('items', []))
            ->filter(fn (array $item): bool => (bool) ($item['is_responded'] ?? false))
            ->values()
            ->all();
    }

    /**
     * @param mixed $items
     * @return array<int, array<string, mixed>>
     */
    private function normalizeItems(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        $normalized = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $normalized[] = [
                'supplier_quote_request_item_id' => $this->normalizeNullableInteger($item['supplier_quote_request_item_id'] ?? null),
                'is_responded' => (bool) ($item['is_responded'] ?? false),
                'is_available' => (bool) ($item['is_available'] ?? true),
                'quantity' => $this->normalizeNullableNumeric($item['quantity'] ?? null),
                'unit_price' => $this->normalizeNullableNumeric($item['unit_price'] ?? null),
                'discount_percent' => $this->normalizeNullableNumeric($item['discount_percent'] ?? null),
                'is_alternative' => (bool) ($item['is_alternative'] ?? false),
                'alternative_description' => $this->normalizeNullableString($item['alternative_description'] ?? null),
                'brand' => $this->normalizeNullableString($item['brand'] ?? null),
                'notes' => $this->normalizeNullableString($item['notes'] ?? null),
            ];
        }

        return $normalized;
    }

    private function normalizeNullableInteger(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private function normalizeNullableNumeric(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) str_replace(',', '.', (string) $value);
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
