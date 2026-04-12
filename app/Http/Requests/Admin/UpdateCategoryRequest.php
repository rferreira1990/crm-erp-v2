<?php

namespace App\Http\Requests\Admin;

use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateCategoryRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => Category::normalizeName((string) $this->input('name')),
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
            && $user->can('company.categories.update');
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
                Rule::exists('categories', 'id')->where(function ($query) use ($companyId): void {
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
            $nameKey = Category::normalizeNameKey((string) $this->input('name'));
            $categoryId = (int) $this->route('category');
            $parentId = $this->input('parent_id') !== null ? (int) $this->input('parent_id') : null;

            $existsInContext = Category::query()
                ->where(function ($query) use ($companyId): void {
                    $query->where(function ($systemQuery): void {
                        $systemQuery->where('is_system', true)
                            ->whereNull('company_id');
                    })->orWhere('company_id', $companyId);
                })
                ->whereRaw('LOWER(name) = ?', [$nameKey])
                ->whereKeyNot($categoryId)
                ->exists();

            if ($existsInContext) {
                $validator->errors()->add(
                    'name',
                    'Ja existe uma categoria visivel no seu contexto com este nome.'
                );
            }

            if ($parentId === null) {
                return;
            }

            if ($parentId === $categoryId) {
                $validator->errors()->add(
                    'parent_id',
                    'A categoria pai nao pode ser a propria categoria.'
                );

                return;
            }

            if ($this->introducesCycle($categoryId, $parentId)) {
                $validator->errors()->add(
                    'parent_id',
                    'A categoria pai selecionada cria um ciclo invalido.'
                );
            }
        });
    }

    private function introducesCycle(int $categoryId, int $parentId): bool
    {
        $visited = [];
        $cursorId = $parentId;
        $depth = 0;

        while ($cursorId > 0 && $depth < 50) {
            if (isset($visited[$cursorId])) {
                return true;
            }

            if ($cursorId === $categoryId) {
                return true;
            }

            $visited[$cursorId] = true;

            $nextParentId = Category::query()
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
