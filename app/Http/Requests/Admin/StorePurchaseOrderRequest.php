<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StorePurchaseOrderRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $itemsInput = $this->input('items', []);
        if (! is_array($itemsInput)) {
            $itemsInput = [];
        }

        $normalizedItems = [];
        foreach ($itemsInput as $line) {
            if (! is_array($line)) {
                continue;
            }

            $articleId = $this->normalizeNullableInteger($line['article_id'] ?? null);
            $description = $this->normalizeNullableString($line['description'] ?? null);
            $unitName = $this->normalizeNullableString($line['unit_name'] ?? null);
            $quantity = $this->normalizeNullableNumeric($line['quantity'] ?? null);
            $unitPrice = $this->normalizeNullableNumeric($line['unit_price'] ?? null);
            $discountPercent = $this->normalizeNullableNumeric($line['discount_percent'] ?? null);
            $notes = $this->normalizeNullableString($line['notes'] ?? null);

            if ($articleId === null && $description === null && $unitName === null && $quantity === null && $unitPrice === null && $discountPercent === null && $notes === null) {
                continue;
            }

            $normalizedItems[] = [
                'article_id' => $articleId,
                'description' => $description,
                'unit_name' => $unitName,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'discount_percent' => $discountPercent ?? 0,
                'notes' => $notes,
            ];
        }

        $this->merge([
            'supplier_id' => $this->normalizeNullableInteger($this->input('supplier_id')),
            'issue_date' => trim((string) $this->input('issue_date')),
            'expected_delivery_date' => $this->normalizeNullableString($this->input('expected_delivery_date')),
            'shipping_total' => $this->normalizeNullableNumeric($this->input('shipping_total')) ?? 0,
            'supplier_notes' => $this->normalizeNullableString($this->input('supplier_notes')),
            'internal_notes' => $this->normalizeNullableString($this->input('internal_notes')),
            'items' => $normalizedItems,
        ]);
    }

    public function authorize(): bool
    {
        $user = $this->user();

        return $user
            && $user->isCompanyUser()
            && $user->can('company.purchase_orders.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'supplier_id' => ['required', 'integer', 'min:1'],
            'issue_date' => ['required', 'date'],
            'expected_delivery_date' => ['nullable', 'date', 'after_or_equal:issue_date'],
            'shipping_total' => ['required', 'numeric', 'min:0'],
            'supplier_notes' => ['nullable', 'string', 'max:5000'],
            'internal_notes' => ['nullable', 'string', 'max:5000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.article_id' => ['nullable', 'integer', 'min:1'],
            'items.*.description' => ['nullable', 'string', 'max:1000'],
            'items.*.unit_name' => ['nullable', 'string', 'max:50'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $items = $this->input('items', []);
            if (! is_array($items) || $items === []) {
                $validator->errors()->add('items', 'Tem de adicionar pelo menos uma linha.');

                return;
            }

            foreach ($items as $index => $line) {
                if (! is_array($line)) {
                    continue;
                }

                $hasArticle = ! empty($line['article_id']);
                $hasDescription = trim((string) ($line['description'] ?? '')) !== '';

                if (! $hasArticle && ! $hasDescription) {
                    $validator->errors()->add(
                        "items.$index.description",
                        'Cada linha deve ter artigo ou descricao.'
                    );
                }
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'supplier_id.required' => 'O fornecedor e obrigatorio.',
            'issue_date.required' => 'A data da encomenda e obrigatoria.',
            'issue_date.date' => 'A data da encomenda e invalida.',
            'expected_delivery_date.after_or_equal' => 'A data prevista tem de ser igual ou posterior a data da encomenda.',
            'items.required' => 'Tem de adicionar pelo menos uma linha.',
            'items.min' => 'Tem de adicionar pelo menos uma linha.',
            'items.*.quantity.gt' => 'A quantidade tem de ser superior a zero.',
            'items.*.unit_price.min' => 'O preco unitario nao pode ser negativo.',
            'items.*.discount_percent.max' => 'O desconto nao pode ser superior a 100%.',
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
