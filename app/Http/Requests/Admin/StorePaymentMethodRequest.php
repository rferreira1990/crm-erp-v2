<?php

namespace App\Http\Requests\Admin;

use App\Models\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StorePaymentMethodRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => PaymentMethod::normalizeName((string) $this->input('name')),
        ]);
    }

    public function authorize(): bool
    {
        $user = $this->user();

        return $user
            && $user->isCompanyUser()
            && $user->can('company.payment_methods.create');
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
            $nameKey = PaymentMethod::normalizeNameKey((string) $this->input('name'));

            $existsInContext = PaymentMethod::query()
                ->where(function ($query) use ($companyId): void {
                    $query->where(function ($systemQuery): void {
                        $systemQuery->where('is_system', true)
                            ->whereNull('company_id');
                    })->orWhere('company_id', $companyId);
                })
                ->whereRaw('LOWER(name) = ?', [$nameKey])
                ->exists();

            if ($existsInContext) {
                $validator->errors()->add(
                    'name',
                    'Já existe um modo de pagamento visível no seu contexto com este nome.'
                );
            }
        });
    }
}
