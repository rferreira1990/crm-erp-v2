<?php

namespace App\Http\Requests\Admin;

use App\Models\PaymentTerm;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdatePaymentTermRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => PaymentTerm::normalizeName((string) $this->input('name')),
            'calculation_type' => trim(Str::lower((string) $this->input('calculation_type'))),
        ]);
    }

    public function authorize(): bool
    {
        $user = $this->user();

        return $user
            && $user->isCompanyUser()
            && $user->can('company.payment_terms.update');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'calculation_type' => ['required', 'string', Rule::in(PaymentTerm::calculationTypes())],
            'days' => ['required', 'integer', 'min:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $companyId = (int) $this->user()->company_id;
            $nameKey = PaymentTerm::normalizeNameKey((string) $this->input('name'));
            $paymentTermId = (int) $this->route('paymentTerm');

            $existsInContext = PaymentTerm::query()
                ->visibleToCompany($companyId)
                ->whereRaw('LOWER(name) = ?', [$nameKey])
                ->whereKeyNot($paymentTermId)
                ->exists();

            if ($existsInContext) {
                $validator->errors()->add(
                    'name',
                    'Já existe uma condição de pagamento visível no seu contexto com este nome.'
                );
            }
        });
    }
}
