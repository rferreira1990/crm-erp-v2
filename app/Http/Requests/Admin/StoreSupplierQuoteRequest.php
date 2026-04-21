<?php

namespace App\Http\Requests\Admin;

use App\Models\Article;
use App\Models\Supplier;
use App\Models\SupplierQuoteRequestItem;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreSupplierQuoteRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'title' => $this->normalizeNullableString($this->input('title')),
            'issue_date' => $this->normalizeNullableString($this->input('issue_date')) ?? now()->toDateString(),
            'response_deadline' => $this->normalizeNullableString($this->input('response_deadline')),
            'internal_notes' => $this->normalizeNullableString($this->input('internal_notes')),
            'supplier_notes' => $this->normalizeNullableString($this->input('supplier_notes')),
            'assigned_user_id' => $this->normalizeNullableInteger($this->input('assigned_user_id')),
            'estimated_total' => $this->normalizeNullableNumeric($this->input('estimated_total')),
            'is_active' => $this->boolean('is_active', true),
            'supplier_ids' => $this->normalizeSupplierIds($this->input('supplier_ids', [])),
            'items' => $this->normalizeItems($this->input('items', [])),
        ]);
    }

    public function authorize(): bool
    {
        $user = $this->user();

        return $user
            && $user->isCompanyUser()
            && $user->can('company.rfq.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:190'],
            'issue_date' => ['required', 'date'],
            'response_deadline' => ['nullable', 'date', 'after_or_equal:issue_date'],
            'internal_notes' => ['nullable', 'string', 'max:5000'],
            'supplier_notes' => ['nullable', 'string', 'max:5000'],
            'assigned_user_id' => ['nullable', 'integer'],
            'estimated_total' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['required', 'boolean'],
            'supplier_ids' => ['required', 'array', 'min:1', 'max:100'],
            'supplier_ids.*' => ['required', 'integer', 'distinct'],

            'items' => ['required', 'array', 'min:1', 'max:400'],
            'items.*.line_order' => ['nullable', 'integer', 'min:1'],
            'items.*.line_type' => ['required', 'string', Rule::in(SupplierQuoteRequestItem::lineTypes())],
            'items.*.article_id' => ['nullable', 'integer'],
            'items.*.article_code' => ['nullable', 'string', 'max:60'],
            'items.*.description' => ['nullable', 'string', 'max:5000'],
            'items.*.unit_name' => ['nullable', 'string', 'max:120'],
            'items.*.quantity' => ['nullable', 'numeric', 'min:0'],
            'items.*.internal_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $companyId = (int) $this->user()->company_id;
            $this->validateAssignedUser($validator, $companyId);
            $this->validateSuppliers($validator, $companyId);
            $this->validateItems($validator, $companyId);
        });
    }

    private function validateAssignedUser(Validator $validator, int $companyId): void
    {
        $assignedUserId = $this->input('assigned_user_id');
        if ($assignedUserId === null) {
            return;
        }

        $exists = User::query()
            ->where('is_super_admin', false)
            ->where('is_active', true)
            ->where('company_id', $companyId)
            ->whereKey((int) $assignedUserId)
            ->exists();

        if (! $exists) {
            $validator->errors()->add('assigned_user_id', 'O responsavel selecionado nao esta disponivel para a empresa.');
        }
    }

    private function validateSuppliers(Validator $validator, int $companyId): void
    {
        $supplierIds = collect((array) $this->input('supplier_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->values()
            ->all();

        if ($supplierIds === []) {
            $validator->errors()->add('supplier_ids', 'Selecione pelo menos um fornecedor.');

            return;
        }

        $count = Supplier::query()
            ->forCompany($companyId)
            ->whereIn('id', $supplierIds)
            ->count();

        if ($count !== count($supplierIds)) {
            $validator->errors()->add('supplier_ids', 'Existem fornecedores invalidos para esta empresa.');
        }
    }

    private function validateItems(Validator $validator, int $companyId): void
    {
        $items = (array) $this->input('items', []);

        foreach ($items as $index => $item) {
            $prefix = "items.$index";
            $lineType = (string) ($item['line_type'] ?? '');
            $articleId = $item['article_id'] ?? null;
            $description = trim((string) ($item['description'] ?? ''));
            $quantity = (float) ($item['quantity'] ?? 0);

            if ($lineType === SupplierQuoteRequestItem::TYPE_ARTICLE) {
                if ($articleId === null) {
                    $validator->errors()->add("$prefix.article_id", 'A linha de artigo exige artigo selecionado.');
                } else {
                    $articleExists = Article::query()
                        ->forCompany($companyId)
                        ->whereKey((int) $articleId)
                        ->exists();

                    if (! $articleExists) {
                        $validator->errors()->add("$prefix.article_id", 'O artigo selecionado nao esta disponivel para a empresa.');
                    }
                }
            }

            if (in_array($lineType, [SupplierQuoteRequestItem::TYPE_TEXT, SupplierQuoteRequestItem::TYPE_SECTION, SupplierQuoteRequestItem::TYPE_NOTE], true)
                && $description === '') {
                $validator->errors()->add("$prefix.description", 'A descricao e obrigatoria para esta linha.');
            }

            if (in_array($lineType, [SupplierQuoteRequestItem::TYPE_ARTICLE, SupplierQuoteRequestItem::TYPE_TEXT], true) && $quantity <= 0) {
                $validator->errors()->add("$prefix.quantity", 'A quantidade deve ser superior a zero.');
            }
        }
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
        foreach ($items as $index => $item) {
            if (! is_array($item)) {
                continue;
            }

            $normalized[] = [
                'line_order' => $this->normalizeNullableInteger($item['line_order'] ?? ($index + 1)),
                'line_type' => strtolower(trim((string) ($item['line_type'] ?? SupplierQuoteRequestItem::TYPE_ARTICLE))),
                'article_id' => $this->normalizeNullableInteger($item['article_id'] ?? null),
                'article_code' => $this->normalizeNullableString($item['article_code'] ?? null),
                'description' => $this->normalizeNullableString($item['description'] ?? null),
                'unit_name' => $this->normalizeNullableString($item['unit_name'] ?? null),
                'quantity' => $this->normalizeNullableNumeric($item['quantity'] ?? null),
                'internal_notes' => $this->normalizeNullableString($item['internal_notes'] ?? null),
            ];
        }

        return $normalized;
    }

    /**
     * @param mixed $supplierIds
     * @return array<int, int>
     */
    private function normalizeSupplierIds(mixed $supplierIds): array
    {
        if (! is_array($supplierIds)) {
            return [];
        }

        return collect($supplierIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();
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

