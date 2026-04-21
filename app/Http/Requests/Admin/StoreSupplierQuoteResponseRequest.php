<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Validator;

class StoreSupplierQuoteResponseRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $commercialDiscountText = $this->normalizeNullableString($this->input('commercial_discount_text'));

        $this->merge([
            'shipping_cost' => $this->normalizeNullableNumeric($this->input('shipping_cost')),
            'delivery_days' => $this->normalizeNullableInteger($this->input('delivery_days')),
            'supplier_document_date' => $this->normalizeNullableString($this->input('supplier_document_date')),
            'supplier_document_number' => $this->normalizeNullableString($this->input('supplier_document_number')),
            'commercial_discount_text' => $commercialDiscountText,
            'commercial_discount_percent' => $this->extractCommercialDiscountPercent($commercialDiscountText),
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
            'supplier_document_date' => ['required', 'date'],
            'supplier_document_number' => ['required', 'string', 'max:120'],
            'commercial_discount_text' => ['nullable', 'string', 'max:255'],
            'commercial_discount_percent' => ['nullable', 'numeric', 'between:0,100'],
            'payment_terms_text' => ['nullable', 'string', 'max:255'],
            'valid_until' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'supplier_document_pdf' => ['nullable', 'file', 'mimetypes:application/pdf', 'max:12288'],
            'received_at' => ['required', 'date', 'before_or_equal:now'],
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

            $proposalDate = $this->input('supplier_document_date');
            $validUntil = $this->input('valid_until');
            if ($proposalDate !== null && Carbon::parse((string) $proposalDate)->isAfter(now()->endOfDay())) {
                $validator->errors()->add('supplier_document_date', 'A data da proposta nao pode ser futura.');
            }

            if ($proposalDate !== null && $validUntil !== null) {
                $proposalDateValue = Carbon::parse((string) $proposalDate)->startOfDay();
                $validUntilValue = Carbon::parse((string) $validUntil)->startOfDay();

                if ($validUntilValue->lessThanOrEqualTo($proposalDateValue)) {
                    $validator->errors()->add('valid_until', 'A validade da proposta tem de ser superior a data da proposta.');
                }
            }

            if ($this->filled('commercial_discount_text') && $this->input('commercial_discount_percent') === null) {
                $validator->errors()->add('commercial_discount_text', 'Indique um desconto comercial em percentagem (ex.: 3% pp).');
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
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'received_at.required' => 'A data de rececao e obrigatoria.',
            'received_at.date' => 'A data de rececao deve ser uma data valida.',
            'received_at.before_or_equal' => 'A data de rececao nao pode ser futura.',

            'supplier_document_date.required' => 'A data da proposta e obrigatoria.',
            'supplier_document_date.date' => 'A data da proposta deve ser uma data valida.',

            'supplier_document_number.required' => 'O numero do documento do fornecedor e obrigatorio.',
            'supplier_document_number.max' => 'O numero do documento do fornecedor nao pode exceder 120 caracteres.',

            'shipping_cost.numeric' => 'O valor de portes deve ser numerico.',
            'shipping_cost.min' => 'O valor de portes nao pode ser negativo.',

            'delivery_days.integer' => 'O prazo de entrega deve ser um numero inteiro.',
            'delivery_days.min' => 'O prazo de entrega nao pode ser negativo.',
            'delivery_days.max' => 'O prazo de entrega excede o limite permitido.',

            'commercial_discount_text.max' => 'O desconto comercial nao pode exceder 255 caracteres.',
            'commercial_discount_percent.numeric' => 'O desconto comercial deve ser numerico.',
            'commercial_discount_percent.between' => 'O desconto comercial deve estar entre 0% e 100%.',

            'payment_terms_text.max' => 'As condicoes de pagamento nao podem exceder 255 caracteres.',

            'valid_until.date' => 'A validade da proposta deve ser uma data valida.',
            'notes.max' => 'As observacoes nao podem exceder 5000 caracteres.',

            'supplier_document_pdf.file' => 'O documento do fornecedor tem de ser um ficheiro valido.',
            'supplier_document_pdf.mimetypes' => 'O documento do fornecedor deve estar em formato PDF.',
            'supplier_document_pdf.max' => 'O documento do fornecedor nao pode exceder 12MB.',

            'items.required' => 'Tem de indicar pelo menos uma linha para resposta.',
            'items.array' => 'As linhas de resposta sao invalidas.',
            'items.min' => 'Tem de indicar pelo menos uma linha para resposta.',
            'items.max' => 'Excedeu o numero maximo de linhas permitido.',

            'items.*.supplier_quote_request_item_id.required' => 'A referencia da linha do pedido e obrigatoria.',
            'items.*.supplier_quote_request_item_id.integer' => 'A referencia da linha do pedido e invalida.',
            'items.*.is_responded.required' => 'Indique se a linha foi respondida.',
            'items.*.is_responded.boolean' => 'O estado de resposta da linha e invalido.',
            'items.*.is_available.required' => 'Indique a disponibilidade da linha.',
            'items.*.is_available.boolean' => 'A disponibilidade da linha e invalida.',
            'items.*.quantity.numeric' => 'A quantidade proposta deve ser numerica.',
            'items.*.quantity.min' => 'A quantidade proposta nao pode ser negativa.',
            'items.*.unit_price.numeric' => 'O preco unitario deve ser numerico.',
            'items.*.unit_price.min' => 'O preco unitario nao pode ser negativo.',
            'items.*.discount_percent.numeric' => 'O desconto de linha deve ser numerico.',
            'items.*.discount_percent.between' => 'O desconto de linha deve estar entre 0% e 100%.',
            'items.*.is_alternative.required' => 'Indique se a linha e alternativa.',
            'items.*.is_alternative.boolean' => 'O estado de alternativa da linha e invalido.',
            'items.*.alternative_description.max' => 'A descricao alternativa nao pode exceder 5000 caracteres.',
            'items.*.brand.max' => 'A marca nao pode exceder 120 caracteres.',
            'items.*.notes.max' => 'As notas da linha nao podem exceder 2000 caracteres.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'received_at' => 'data de rececao',
            'supplier_document_date' => 'data da proposta',
            'supplier_document_number' => 'numero do documento do fornecedor',
            'shipping_cost' => 'portes',
            'delivery_days' => 'prazo de entrega',
            'commercial_discount_text' => 'desconto comercial',
            'commercial_discount_percent' => 'percentagem do desconto comercial',
            'payment_terms_text' => 'condicoes de pagamento',
            'valid_until' => 'validade da proposta',
            'notes' => 'observacoes',
            'supplier_document_pdf' => 'documento do fornecedor',
            'items' => 'linhas da resposta',
        ];
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

    public function commercialDiscountPercent(): float
    {
        return (float) ($this->input('commercial_discount_percent') ?? 0);
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

    private function extractCommercialDiscountPercent(?string $text): ?float
    {
        if ($text === null) {
            return null;
        }

        if (! preg_match('/(\d+(?:[.,]\d+)?)/', $text, $matches)) {
            return null;
        }

        return (float) str_replace(',', '.', (string) $matches[1]);
    }
}
