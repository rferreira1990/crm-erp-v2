<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ResolvePurchaseOrderReceiptLineRequest extends FormRequest
{
    public const ACTION_ASSIGN_EXISTING = 'assign_existing';
    public const ACTION_CREATE_NEW = 'create_new';
    public const ACTION_MARK_NON_STOCKABLE = 'mark_non_stockable';

    protected function prepareForValidation(): void
    {
        $this->merge([
            'action' => trim((string) $this->input('action')),
            'designation' => trim((string) $this->input('designation')),
            'moves_stock' => $this->boolean('moves_stock', true),
            'is_active' => $this->boolean('is_active', true),
        ]);
    }

    public function authorize(): bool
    {
        $user = $this->user();

        return $user
            && $user->isCompanyUser()
            && $user->can('company.purchase_order_receipts.update');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'action' => ['required', 'string', Rule::in([
                self::ACTION_ASSIGN_EXISTING,
                self::ACTION_CREATE_NEW,
                self::ACTION_MARK_NON_STOCKABLE,
            ])],
            'article_id' => ['nullable', 'integer', 'min:1'],
            'designation' => ['nullable', 'string', 'max:190'],
            'product_family_id' => ['nullable', 'integer', 'min:1'],
            'category_id' => ['nullable', 'integer', 'min:1'],
            'unit_id' => ['nullable', 'integer', 'min:1'],
            'vat_rate_id' => ['nullable', 'integer', 'min:1'],
            'moves_stock' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $action = (string) $this->input('action');

            if ($action === self::ACTION_ASSIGN_EXISTING) {
                if ((int) $this->input('article_id', 0) <= 0) {
                    $validator->errors()->add('article_id', 'Selecione um artigo existente para associar a linha.');
                }
            }

            if ($action === self::ACTION_CREATE_NEW) {
                if (trim((string) $this->input('designation')) === '') {
                    $validator->errors()->add('designation', 'A designacao do artigo e obrigatoria.');
                }

                if ((int) $this->input('product_family_id', 0) <= 0) {
                    $validator->errors()->add('product_family_id', 'Selecione a familia do novo artigo.');
                }

                if ((int) $this->input('vat_rate_id', 0) <= 0) {
                    $validator->errors()->add('vat_rate_id', 'Selecione a taxa de IVA do novo artigo.');
                }
            }
        });
    }
}
