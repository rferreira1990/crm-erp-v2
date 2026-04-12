<?php

namespace App\Http\Requests\Admin;

use App\Models\ProductFamily;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreProductFamilyRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => ProductFamily::normalizeName((string) $this->input('name')),
            'parent_id' => $this->input('parent_id') !== null && $this->input('parent_id') !== ''
                ? (int) $this->input('parent_id')
                : null,
        ]);
    }

    public function authorize(): bool
    {
        $user = $this->user();

        return $user
            && $user->isCompanyUser()
            && $user->can('company.product_families.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $companyId = (int) $this->user()->company_id;

        return [
            'name' => ['required', 'string', 'max:120'],
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('product_families', 'id')->where(function ($query) use ($companyId): void {
                    $query->where('company_id', $companyId)
                        ->where('is_system', false);
                }),
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $companyId = (int) $this->user()->company_id;
            $nameKey = ProductFamily::normalizeNameKey((string) $this->input('name'));
            $parentId = $this->input('parent_id') !== null ? (int) $this->input('parent_id') : null;

            $existsInContextAndParent = ProductFamily::query()
                ->where('company_id', $companyId)
                ->where('is_system', false)
                ->whereRaw('LOWER(name) = ?', [$nameKey])
                ->where(function ($query) use ($parentId): void {
                    if ($parentId === null) {
                        $query->whereNull('parent_id');

                        return;
                    }

                    $query->where('parent_id', $parentId);
                })
                ->exists();

            if ($existsInContextAndParent) {
                $validator->errors()->add(
                    'name',
                    'Ja existe uma familia visivel no seu contexto com este nome para a categoria pai selecionada.'
                );
            }
        });
    }
}
