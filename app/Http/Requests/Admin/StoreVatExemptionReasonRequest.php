<?php

namespace App\Http\Requests\Admin;

use App\Models\VatExemptionReason;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreVatExemptionReasonRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => VatExemptionReason::normalizeCode((string) $this->input('code')),
            'name' => VatExemptionReason::normalizeName((string) $this->input('name')),
            'legal_reference' => VatExemptionReason::normalizeName((string) $this->input('legal_reference')),
        ]);
    }

    public function authorize(): bool
    {
        $user = $this->user();

        return $user
            && $user->isCompanyUser()
            && $user->can('company.vat_exemption_reasons.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:20', 'regex:/^[A-Z0-9]+$/'],
            'name' => ['required', 'string', 'max:190'],
            'legal_reference' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $companyId = (int) $this->user()->company_id;
            $code = VatExemptionReason::normalizeCode((string) $this->input('code'));

            $existsInContext = VatExemptionReason::query()
                ->visibleToCompany($companyId)
                ->where('code', $code)
                ->exists();

            if ($existsInContext) {
                $validator->errors()->add(
                    'code',
                    'Ja existe um motivo de isencao visivel no seu contexto com este codigo.'
                );
            }
        });
    }
}

