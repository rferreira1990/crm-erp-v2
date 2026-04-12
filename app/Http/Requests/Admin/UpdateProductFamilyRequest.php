<?php

namespace App\Http\Requests\Admin;

use App\Models\ProductFamily;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateProductFamilyRequest extends FormRequest
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
            && $user->can('company.product_families.update');
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
                    $query->where(function ($systemQuery): void {
                        $systemQuery->where('is_system', true)
                            ->whereNull('company_id');
                    })->orWhere('company_id', $companyId);
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
            $familyId = (int) $this->route('productFamily');
            $parentId = $this->input('parent_id') !== null ? (int) $this->input('parent_id') : null;

            $existsInContextAndParent = ProductFamily::query()
                ->where(function ($query) use ($companyId): void {
                    $query->where(function ($systemQuery): void {
                        $systemQuery->where('is_system', true)
                            ->whereNull('company_id');
                    })->orWhere('company_id', $companyId);
                })
                ->whereRaw('LOWER(name) = ?', [$nameKey])
                ->whereKeyNot($familyId)
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

            if ($parentId === null) {
                return;
            }

            if ($parentId === $familyId) {
                $validator->errors()->add(
                    'parent_id',
                    'A familia pai nao pode ser a propria familia.'
                );

                return;
            }

            if ($this->introducesCycle($familyId, $parentId)) {
                $validator->errors()->add(
                    'parent_id',
                    'A familia pai selecionada cria um ciclo invalido.'
                );
            }
        });
    }

    private function introducesCycle(int $familyId, int $parentId): bool
    {
        $visited = [];
        $cursorId = $parentId;
        $depth = 0;

        while ($cursorId > 0 && $depth < 50) {
            if (isset($visited[$cursorId])) {
                return true;
            }

            if ($cursorId === $familyId) {
                return true;
            }

            $visited[$cursorId] = true;

            $nextParentId = ProductFamily::query()
                ->whereKey($cursorId)
                ->value('parent_id');

            if ($nextParentId === null) {
                return false;
            }

            $cursorId = (int) $nextParentId;
            $depth++;
        }

        return false;
    }
}

