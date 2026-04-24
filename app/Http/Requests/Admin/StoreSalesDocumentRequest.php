<?php

namespace App\Http\Requests\Admin;

use App\Models\SalesDocument;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreSalesDocumentRequest extends FormRequest
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
            $unitId = $this->normalizeNullableInteger($line['unit_id'] ?? null);
            $unitNameSnapshot = $this->normalizeNullableString($line['unit_name_snapshot'] ?? null);
            $quantity = $this->normalizeNullableNumeric($line['quantity'] ?? null);
            $unitPrice = $this->normalizeNullableNumeric($line['unit_price'] ?? null);
            $discountPercent = $this->normalizeNullableNumeric($line['discount_percent'] ?? null);
            $taxRate = $this->normalizeNullableNumeric($line['tax_rate'] ?? null);

            if (
                $articleId === null
                && $description === null
                && $unitId === null
                && $quantity === null
                && $unitPrice === null
                && $discountPercent === null
                && $taxRate === null
            ) {
                continue;
            }

            $normalizedItems[] = [
                'article_id' => $articleId,
                'description' => $description,
                'unit_id' => $unitId,
                'unit_name_snapshot' => $unitNameSnapshot,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'discount_percent' => $discountPercent ?? 0,
                'tax_rate' => $taxRate ?? 0,
            ];
        }

        $this->merge([
            'source_type' => strtolower(trim((string) $this->input('source_type', SalesDocument::SOURCE_MANUAL))),
            'quote_id' => $this->normalizeNullableInteger($this->input('quote_id')),
            'construction_site_id' => $this->normalizeNullableInteger($this->input('construction_site_id')),
            'customer_id' => $this->normalizeNullableInteger($this->input('customer_id')),
            'customer_contact_id' => $this->normalizeNullableInteger($this->input('customer_contact_id')),
            'issue_date' => trim((string) $this->input('issue_date')),
            'due_date' => $this->normalizeNullableString($this->input('due_date')),
            'notes' => $this->normalizeNullableString($this->input('notes')),
            'items' => $normalizedItems,
        ]);
    }

    public function authorize(): bool
    {
        $user = $this->user();

        return $user
            && $user->isCompanyUser()
            && $user->can('company.sales_documents.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'source_type' => ['required', Rule::in(SalesDocument::sources())],
            'quote_id' => ['nullable', 'integer', 'min:1', 'required_if:source_type,'.SalesDocument::SOURCE_QUOTE],
            'construction_site_id' => ['nullable', 'integer', 'min:1', 'required_if:source_type,'.SalesDocument::SOURCE_CONSTRUCTION_SITE],
            'customer_id' => ['required', 'integer', 'min:1'],
            'customer_contact_id' => ['nullable', 'integer', 'min:1'],
            'issue_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:issue_date'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'items' => ['required', 'array', 'min:1', 'max:500'],
            'items.*.article_id' => ['nullable', 'integer', 'min:1'],
            'items.*.description' => ['nullable', 'string', 'max:1000'],
            'items.*.unit_id' => ['nullable', 'integer', 'min:1'],
            'items.*.unit_name_snapshot' => ['nullable', 'string', 'max:50'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
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
                    $validator->errors()->add("items.$index.description", 'Cada linha deve ter artigo ou descricao.');
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
            'source_type.required' => 'A origem do documento e obrigatoria.',
            'source_type.in' => 'A origem selecionada e invalida.',
            'quote_id.required_if' => 'Tem de selecionar um orcamento.',
            'construction_site_id.required_if' => 'Tem de selecionar uma obra.',
            'customer_id.required' => 'O cliente e obrigatorio.',
            'issue_date.required' => 'A data do documento e obrigatoria.',
            'due_date.after_or_equal' => 'A data de vencimento nao pode ser anterior a data do documento.',
            'items.required' => 'Tem de adicionar pelo menos uma linha.',
            'items.min' => 'Tem de adicionar pelo menos uma linha.',
            'items.*.quantity.gt' => 'A quantidade deve ser superior a zero.',
            'items.*.unit_price.min' => 'O preco unitario nao pode ser negativo.',
            'items.*.discount_percent.max' => 'O desconto nao pode ser superior a 100%.',
            'items.*.tax_rate.max' => 'A taxa nao pode ser superior a 100%.',
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
