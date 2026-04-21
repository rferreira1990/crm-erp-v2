<?php

namespace App\Http\Requests\Admin;

use App\Models\SupplierQuoteAward;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSupplierQuoteAwardRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $itemSupplierIds = $this->input('item_supplier_ids', []);
        if (! is_array($itemSupplierIds)) {
            $itemSupplierIds = [];
        }

        $normalizedItemSupplierIds = [];
        foreach ($itemSupplierIds as $rfqItemId => $supplierId) {
            $normalizedItemSupplierIds[(int) $rfqItemId] = $supplierId === '' || $supplierId === null
                ? null
                : (int) $supplierId;
        }

        $this->merge([
            'mode' => strtolower(trim((string) $this->input('mode'))),
            'awarded_supplier_id' => $this->input('awarded_supplier_id') !== null && $this->input('awarded_supplier_id') !== ''
                ? (int) $this->input('awarded_supplier_id')
                : null,
            'award_reason' => $this->normalizeNullableString($this->input('award_reason')),
            'award_notes' => $this->normalizeNullableString($this->input('award_notes')),
            'item_supplier_ids' => $normalizedItemSupplierIds,
        ]);
    }

    public function authorize(): bool
    {
        $user = $this->user();

        return $user
            && $user->isCompanyUser()
            && $user->can('company.rfq.award');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $manualTotal = $this->input('mode') === SupplierQuoteAward::MODE_MANUAL_TOTAL;
        $manualItem = $this->input('mode') === SupplierQuoteAward::MODE_MANUAL_ITEM;

        return [
            'mode' => ['required', 'string', Rule::in(SupplierQuoteAward::modes())],
            'awarded_supplier_id' => [
                Rule::requiredIf($manualTotal),
                'nullable',
                'integer',
                'min:1',
            ],
            'award_reason' => ['nullable', 'string', 'max:120'],
            'award_notes' => ['nullable', 'string', 'max:5000'],
            'item_supplier_ids' => [Rule::requiredIf($manualItem), 'nullable', 'array'],
            'item_supplier_ids.*' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'mode.required' => 'O modo de adjudicacao e obrigatorio.',
            'mode.in' => 'O modo de adjudicacao selecionado e invalido.',
            'awarded_supplier_id.required' => 'Selecione um fornecedor para adjudicacao manual global.',
            'awarded_supplier_id.integer' => 'O fornecedor selecionado e invalido.',
            'item_supplier_ids.required' => 'Selecione fornecedores para as linhas da adjudicacao manual por item.',
            'item_supplier_ids.array' => 'Os fornecedores por linha sao invalidos.',
            'item_supplier_ids.*.integer' => 'Existe uma selecao de fornecedor por linha invalida.',
            'award_reason.max' => 'O motivo da adjudicacao nao pode exceder 120 caracteres.',
            'award_notes.max' => 'As notas da adjudicacao nao podem exceder 5000 caracteres.',
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

