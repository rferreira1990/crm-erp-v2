<?php

namespace App\Http\Requests\Admin;

use App\Models\Customer;
use App\Models\PaymentTerm;
use App\Models\PriceTier;
use App\Models\VatExemptionReason;
use App\Models\VatRate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCustomerRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $companyId = (int) ($this->user()?->company_id ?? 0);
        $hasCreditLimit = $this->boolean('has_credit_limit');

        $countryId = $this->normalizeNullableInteger($this->input('country_id'));
        if ($countryId === null) {
            $countryId = Customer::defaultCountryId();
        }

        $paymentTermId = $this->normalizeNullableInteger($this->input('payment_term_id'));
        if ($paymentTermId === null && $companyId > 0) {
            $paymentTermId = Customer::defaultPaymentTermIdForCompany($companyId);
        }

        $this->merge([
            'customer_type' => trim((string) $this->input('customer_type')),
            'name' => trim((string) $this->input('name')),
            'address' => $this->normalizeNullableString($this->input('address')),
            'postal_code' => $this->normalizeNullableString($this->input('postal_code')),
            'locality' => $this->normalizeNullableString($this->input('locality')),
            'city' => $this->normalizeNullableString($this->input('city')),
            'country_id' => $countryId,
            'nif' => $this->normalizeNullableString($this->input('nif')),
            'phone' => $this->normalizeNullableString($this->input('phone')),
            'mobile' => $this->normalizeNullableString($this->input('mobile')),
            'email' => $this->normalizeNullableString($this->input('email')),
            'website' => $this->normalizeNullableString($this->input('website')),
            'notes' => $this->normalizeNullableString($this->input('notes')),
            'internal_notes' => $this->normalizeNullableString($this->input('internal_notes')),
            'price_tier_id' => $this->normalizeNullableInteger($this->input('price_tier_id')),
            'payment_term_id' => $paymentTermId,
            'default_vat_rate_id' => $this->normalizeNullableInteger($this->input('default_vat_rate_id')),
            'default_vat_exemption_reason_id' => $this->normalizeNullableInteger($this->input('default_vat_exemption_reason_id')),
            'default_commercial_discount' => $this->normalizeNullableNumeric($this->input('default_commercial_discount')),
            'has_credit_limit' => $hasCreditLimit,
            'credit_limit' => $hasCreditLimit
                ? $this->normalizeNullableNumeric($this->input('credit_limit'))
                : null,
            'print_comments' => $this->normalizeNullableString($this->input('print_comments')),
            'is_active' => $this->boolean('is_active', true),
        ]);
    }

    public function authorize(): bool
    {
        $user = $this->user();

        return $user
            && $user->isCompanyUser()
            && $user->can('company.customers.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $companyId = (int) $this->user()->company_id;

        return [
            'customer_type' => ['required', 'string', Rule::in(Customer::customerTypes())],
            'name' => ['required', 'string', 'max:190'],
            'address' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'regex:/^\d{4}-\d{3}$/'],
            'locality' => ['nullable', 'string', 'max:120'],
            'city' => ['nullable', 'string', 'max:120'],
            'country_id' => ['nullable', 'integer', Rule::exists('countries', 'id')],
            'nif' => [
                'nullable',
                'regex:/^\d{9}$/',
                Rule::unique('customers', 'nif')->where(fn ($query) => $query->where('company_id', $companyId)),
            ],
            'phone' => ['nullable', 'string', 'max:30'],
            'mobile' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email:rfc', 'max:190'],
            'website' => ['nullable', 'url:http,https', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'internal_notes' => ['nullable', 'string', 'max:5000'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:3072'],
            'price_tier_id' => ['nullable', 'integer', Rule::exists('price_tiers', 'id')],
            'payment_term_id' => ['nullable', 'integer', Rule::exists('payment_terms', 'id')],
            'default_vat_rate_id' => ['nullable', 'integer', Rule::exists('vat_rates', 'id')],
            'default_vat_exemption_reason_id' => ['nullable', 'integer', Rule::exists('vat_exemption_reasons', 'id')],
            'default_commercial_discount' => ['nullable', 'numeric', 'between:0,100'],
            'has_credit_limit' => ['required', 'boolean'],
            'credit_limit' => ['nullable', 'numeric', 'min:0'],
            'print_comments' => ['nullable', 'string', 'max:5000'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $companyId = (int) $this->user()->company_id;
            $priceTierId = $this->input('price_tier_id');
            $paymentTermId = $this->input('payment_term_id');

            if ($priceTierId !== null) {
                $tierExists = PriceTier::query()
                    ->visibleToCompany($companyId)
                    ->where('is_active', true)
                    ->whereKey((int) $priceTierId)
                    ->exists();

                if (! $tierExists) {
                    $validator->errors()->add('price_tier_id', 'O escalao de preco selecionado nao esta disponivel para a empresa.');
                }
            }

            if ($paymentTermId !== null) {
                $termExists = PaymentTerm::query()
                    ->visibleToCompany($companyId)
                    ->whereKey((int) $paymentTermId)
                    ->exists();

                if (! $termExists) {
                    $validator->errors()->add('payment_term_id', 'A condicao de pagamento selecionada nao esta disponivel para a empresa.');
                }
            }

            $defaultVatRateId = $this->input('default_vat_rate_id');
            $defaultVatExemptionReasonId = $this->input('default_vat_exemption_reason_id');
            $selectedVatRate = null;
            if ($defaultVatRateId !== null) {
                $vatRate = VatRate::query()
                    ->with([
                        'companyOverrides' => fn ($query) => $query->where('company_id', $companyId),
                    ])
                    ->visibleToCompany($companyId)
                    ->whereKey((int) $defaultVatRateId)
                    ->first();

                if (! $vatRate || ! $vatRate->isEnabledForCompany($companyId)) {
                    $validator->errors()->add('default_vat_rate_id', 'A taxa de IVA selecionada nao esta ativa para a empresa.');
                } else {
                    $selectedVatRate = $vatRate;
                }
            }

            if ($defaultVatExemptionReasonId !== null) {
                $reason = VatExemptionReason::query()
                    ->with([
                        'companyOverrides' => fn ($query) => $query->where('company_id', $companyId),
                    ])
                    ->visibleToCompany($companyId)
                    ->whereKey((int) $defaultVatExemptionReasonId)
                    ->first();

                if (! $reason || ! $reason->isEnabledForCompany($companyId)) {
                    $validator->errors()->add('default_vat_exemption_reason_id', 'O motivo de isencao selecionado nao esta ativo para a empresa.');
                }
            }

            if ($selectedVatRate === null && $defaultVatExemptionReasonId !== null) {
                $validator->errors()->add('default_vat_exemption_reason_id', 'Nao pode indicar motivo de isencao sem taxa de IVA.');
            }

            if ($selectedVatRate !== null && $selectedVatRate->is_exempt && $defaultVatExemptionReasonId === null) {
                $validator->errors()->add('default_vat_exemption_reason_id', 'Quando a taxa de IVA habitual e isenta, o motivo de isencao e obrigatorio.');
            }

            if ($selectedVatRate !== null && ! $selectedVatRate->is_exempt && $defaultVatExemptionReasonId !== null) {
                $validator->errors()->add('default_vat_exemption_reason_id', 'Apenas pode indicar motivo de isencao quando a taxa de IVA habitual e isenta.');
            }
        });
    }

    private function normalizeNullableInteger(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }

    private function normalizeNullableNumeric(mixed $value): float|int|string|null
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $value;
    }
}
