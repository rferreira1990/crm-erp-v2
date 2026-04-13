<?php

namespace App\Http\Requests\Admin;

use App\Models\PriceTier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePriceTierRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => PriceTier::normalizeName((string) $this->input('name')),
            'percentage_adjustment' => $this->input('percentage_adjustment'),
            'is_active' => $this->boolean('is_active', true),
        ]);
    }

    public function authorize(): bool
    {
        $user = $this->user();

        return $user
            && $user->isCompanyUser()
            && $user->can('company.price_tiers.update');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $companyId = (int) $this->user()->company_id;
        $priceTierId = (int) $this->route('priceTier');

        return [
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('price_tiers', 'name')
                    ->ignore($priceTierId)
                    ->where(fn ($query) => $query->where('company_id', $companyId)),
            ],
            'percentage_adjustment' => ['required', 'numeric', 'between:-100,1000'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}

