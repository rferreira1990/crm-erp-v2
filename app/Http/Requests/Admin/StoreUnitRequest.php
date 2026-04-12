<?php

namespace App\Http\Requests\Admin;

use App\Models\Unit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreUnitRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => Unit::normalizeCode((string) $this->input('code')),
            'name' => trim((string) $this->input('name')),
        ]);
    }

    public function authorize(): bool
    {
        $user = $this->user();

        return $user
            && $user->isCompanyUser()
            && $user->can('company.units.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:20', 'regex:/^[A-Z0-9._-]+$/'],
            'name' => ['required', 'string', 'max:100'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $companyId = (int) $this->user()->company_id;
            $code = (string) $this->input('code');

            $existsInContext = Unit::query()
                ->where('code', $code)
                ->where(function ($query) use ($companyId): void {
                    $query->where(function ($systemQuery): void {
                        $systemQuery->where('is_system', true)
                            ->whereNull('company_id');
                    })->orWhere('company_id', $companyId);
                })
                ->exists();

            if ($existsInContext) {
                $validator->errors()->add(
                    'code',
                    'Ja existe uma unidade visivel no seu contexto com este codigo.'
                );
            }
        });
    }
}
