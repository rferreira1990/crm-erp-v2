<?php

namespace App\Http\Requests\Admin;

use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateCategoryRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => Category::normalizeName((string) $this->input('name')),
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
        return [
            'name' => ['required', 'string', 'max:120'],
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
        });
    }
}
