<?php

namespace App\Http\Requests\Admin;

use App\Models\VatExemptionReason;
use App\Models\VatRate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateVatRateRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => VatRate::normalizeName((string) $this->input('name')),
            'region' => $this->input('region') !== null ? strtolower(trim((string) $this->input('region'))) : null,
            'is_exempt' => $this->boolean('is_exempt'),
            'vat_exemption_reason_id' => $this->input('vat_exemption_reason_id') !== null && $this->input('vat_exemption_reason_id') !== ''
                ? (int) $this->input('vat_exemption_reason_id')
                : null,
        ]);
    }

    public function authorize(): bool
    {
        $user = $this->user();

        return $user
            && $user->isCompanyUser()
            && $user->can('company.vat_rates.update');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'region' => ['nullable', 'string', Rule::in(VatRate::regions())],
            'rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'is_exempt' => ['required', 'boolean'],
            'vat_exemption_reason_id' => ['nullable', 'integer', 'exists:vat_exemption_reasons,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $companyId = (int) $this->user()->company_id;
            $vatRateId = (int) $this->route('vatRate');
            $nameKey = VatRate::normalizeNameKey((string) $this->input('name'));
            $region = $this->input('region');
            $isExempt = (bool) $this->input('is_exempt');
            $rate = (float) $this->input('rate');
            $reasonId = $this->input('vat_exemption_reason_id');

            $existsInContext = VatRate::query()
                ->visibleToCompany($companyId)
                ->whereRaw('LOWER(name) = ?', [$nameKey])
                ->where('region', $region)
                ->whereKeyNot($vatRateId)
                ->exists();

            if ($existsInContext) {
                $validator->errors()->add(
                    'name',
                    'Ja existe uma taxa de IVA visivel no seu contexto com este nome e regiao.'
                );
            }

            if (! $isExempt && $reasonId !== null) {
                $validator->errors()->add(
                    'vat_exemption_reason_id',
                    'Uma taxa nao isenta nao pode ter motivo de isencao.'
                );
            }

            if ($isExempt && $reasonId === null) {
                $validator->errors()->add(
                    'vat_exemption_reason_id',
                    'Uma taxa isenta requer motivo de isencao.'
                );
            }

            if ($isExempt && $rate !== 0.0) {
                $validator->errors()->add(
                    'rate',
                    'Uma taxa isenta deve ter valor 0.'
                );
            }

            if ($reasonId !== null) {
                $reasonVisible = VatExemptionReason::query()
                    ->visibleToCompany($companyId)
                    ->whereKey($reasonId)
                    ->exists();

                if (! $reasonVisible) {
                    $validator->errors()->add(
                        'vat_exemption_reason_id',
                        'O motivo de isencao selecionado nao esta disponivel para a sua empresa.'
                    );
                }
            }
        });
    }
}

