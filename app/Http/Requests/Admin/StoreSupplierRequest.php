<?php

namespace App\Http\Requests\Admin;

use App\Models\PaymentMethod;
use App\Models\PaymentTerm;
use App\Models\Supplier;
use App\Models\VatRate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreSupplierRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $companyId = (int) ($this->user()?->company_id ?? 0);

        $countryId = $this->normalizeNullableInteger($this->input('country_id'));
        if ($countryId === null) {
            $countryId = Supplier::defaultCountryId();
        }

        $this->merge([
            'supplier_type' => trim((string) $this->input('supplier_type')),
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
            'payment_term_id' => $this->normalizeNullableInteger($this->input('payment_term_id')),
            'default_vat_rate_id' => $this->normalizeNullableInteger($this->input('default_vat_rate_id')),
            'default_payment_method_id' => $this->normalizeNullableInteger($this->input('default_payment_method_id')),
            'iban' => $this->normalizeNullableString($this->input('iban')),
            'payment_notes' => $this->normalizeNullableString($this->input('payment_notes')),
            'is_active' => $this->boolean('is_active', true),
        ]);
    }

    public function authorize(): bool
    {
        $user = $this->user();

        return $user
            && $user->isCompanyUser()
            && $user->can('company.suppliers.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $companyId = (int) $this->user()->company_id;

        return [
            'supplier_type' => ['required', 'string', Rule::in(Supplier::supplierTypes())],
            'name' => ['required', 'string', 'max:190'],
            'address' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'regex:/^\d{4}-\d{3}$/'],
            'locality' => ['nullable', 'string', 'max:120'],
            'city' => ['nullable', 'string', 'max:120'],
            'country_id' => ['nullable', 'integer', Rule::exists('countries', 'id')],
            'nif' => [
                'nullable',
                'regex:/^\d{9}$/',
                Rule::unique('suppliers', 'nif')->where(fn ($query) => $query->where('company_id', $companyId)),
            ],
            'phone' => ['nullable', 'string', 'max:30'],
            'mobile' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email:rfc', 'max:190'],
            'website' => ['nullable', 'url:http,https', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:3072'],
            'payment_term_id' => ['nullable', 'integer', Rule::exists('payment_terms', 'id')],
            'default_vat_rate_id' => ['nullable', 'integer', Rule::exists('vat_rates', 'id')],
            'default_payment_method_id' => ['nullable', 'integer', Rule::exists('payment_methods', 'id')],
            'iban' => ['nullable', 'string', 'max:34'],
            'payment_notes' => ['nullable', 'string', 'max:5000'],
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
            $paymentTermId = $this->input('payment_term_id');
            $paymentMethodId = $this->input('default_payment_method_id');

            if ($paymentTermId !== null) {
                $termExists = PaymentTerm::query()
                    ->visibleToCompany($companyId)
                    ->whereKey((int) $paymentTermId)
                    ->exists();

                if (! $termExists) {
                    $validator->errors()->add('payment_term_id', 'A condicao de pagamento selecionada nao esta disponivel para a empresa.');
                }
            }

            if ($paymentMethodId !== null) {
                $methodExists = PaymentMethod::query()
                    ->visibleToCompany($companyId)
                    ->whereKey((int) $paymentMethodId)
                    ->exists();

                if (! $methodExists) {
                    $validator->errors()->add('default_payment_method_id', 'O modo de pagamento selecionado nao esta disponivel para a empresa.');
                }
            }

            $defaultVatRateId = $this->input('default_vat_rate_id');
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
                }
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
}
