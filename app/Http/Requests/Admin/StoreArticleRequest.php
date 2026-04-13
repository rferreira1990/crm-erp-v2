<?php

namespace App\Http\Requests\Admin;

use App\Models\Article;
use App\Models\ProductFamily;
use App\Models\VatExemptionReason;
use App\Models\VatRate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreArticleRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $companyId = (int) ($this->user()?->company_id ?? 0);

        $categoryId = $this->normalizeNullableInteger($this->input('category_id'));
        if ($categoryId === null && $companyId > 0) {
            $categoryId = Article::defaultCategoryIdForCompany($companyId);
        }

        $unitId = $this->normalizeNullableInteger($this->input('unit_id'));
        if ($unitId === null && $companyId > 0) {
            $unitId = Article::defaultUnitIdForCompany($companyId);
        }

        $this->merge([
            'designation' => trim((string) $this->input('designation')),
            'abbreviation' => $this->normalizeNullableString($this->input('abbreviation')),
            'product_family_id' => $this->normalizeNullableInteger($this->input('product_family_id')),
            'brand_id' => $this->normalizeNullableInteger($this->input('brand_id')),
            'category_id' => $categoryId,
            'unit_id' => $unitId,
            'vat_rate_id' => $this->normalizeNullableInteger($this->input('vat_rate_id')),
            'vat_exemption_reason_id' => $this->normalizeNullableInteger($this->input('vat_exemption_reason_id')),
            'supplier_id' => $this->normalizeNullableInteger($this->input('supplier_id')),
            'supplier_reference' => $this->normalizeNullableString($this->input('supplier_reference')),
            'ean' => $this->normalizeNullableString($this->input('ean')),
            'internal_notes' => $this->normalizeNullableString($this->input('internal_notes')),
            'print_notes' => $this->normalizeNullableString($this->input('print_notes')),
            'cost_price' => $this->normalizeNullableNumeric($this->input('cost_price')),
            'sale_price' => $this->normalizeNullableNumeric($this->input('sale_price')),
            'default_margin' => $this->normalizeNullableNumeric($this->input('default_margin')),
            'direct_discount' => $this->normalizeNullableNumeric($this->input('direct_discount')),
            'max_discount' => $this->normalizeNullableNumeric($this->input('max_discount')),
            'minimum_stock' => $this->normalizeNullableNumeric($this->input('minimum_stock')),
            'moves_stock' => $this->boolean('moves_stock'),
            'stock_alert_enabled' => $this->boolean('stock_alert_enabled'),
            'is_active' => $this->boolean('is_active', true),
        ]);
    }

    public function authorize(): bool
    {
        $user = $this->user();

        return $user
            && $user->isCompanyUser()
            && $user->can('company.articles.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $companyId = (int) $this->user()->company_id;

        return [
            'designation' => ['required', 'string', 'max:190'],
            'abbreviation' => ['nullable', 'string', 'max:50'],

            'product_family_id' => [
                'required',
                'integer',
                Rule::exists('product_families', 'id')->where(function ($query) use ($companyId): void {
                    $query->where('company_id', $companyId)
                        ->where('is_system', false);
                }),
            ],
            'brand_id' => [
                'nullable',
                'integer',
                Rule::exists('brands', 'id')->where(function ($query) use ($companyId): void {
                    $query->where('company_id', $companyId);
                }),
            ],
            'category_id' => [
                'required',
                'integer',
                Rule::exists('categories', 'id')->where(function ($query) use ($companyId): void {
                    $query->where(function ($where) use ($companyId): void {
                        $where->where(function ($systemQuery): void {
                            $systemQuery->where('is_system', true)
                                ->whereNull('company_id');
                        })->orWhere('company_id', $companyId);
                    });
                }),
            ],
            'unit_id' => [
                'required',
                'integer',
                Rule::exists('units', 'id')->where(function ($query) use ($companyId): void {
                    $query->where(function ($where) use ($companyId): void {
                        $where->where(function ($systemQuery): void {
                            $systemQuery->where('is_system', true)
                                ->whereNull('company_id');
                        })->orWhere('company_id', $companyId);
                    });
                }),
            ],
            'vat_rate_id' => [
                'required',
                'integer',
                Rule::exists('vat_rates', 'id')->where(function ($query): void {
                    $query->where('is_system', true)
                        ->whereNull('company_id');
                }),
            ],
            'vat_exemption_reason_id' => [
                'nullable',
                'integer',
                Rule::exists('vat_exemption_reasons', 'id')->where(function ($query): void {
                    $query->where('is_system', true)
                        ->whereNull('company_id');
                }),
            ],

            'supplier_id' => ['nullable', 'integer', 'min:1'],
            'supplier_reference' => ['nullable', 'string', 'max:120'],
            'ean' => ['nullable', 'regex:/^\d{8,14}$/'],
            'internal_notes' => ['nullable', 'string', 'max:5000'],
            'print_notes' => ['nullable', 'string', 'max:5000'],

            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'sale_price' => ['nullable', 'numeric', 'min:0'],
            'default_margin' => ['nullable', 'numeric', 'between:0,100'],
            'direct_discount' => ['nullable', 'numeric', 'between:0,100'],
            'max_discount' => ['nullable', 'numeric', 'between:0,100'],

            'moves_stock' => ['required', 'boolean'],
            'stock_alert_enabled' => ['required', 'boolean'],
            'minimum_stock' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['required', 'boolean'],

            'images' => ['nullable', 'array', 'max:12'],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'documents' => ['nullable', 'array', 'max:12'],
            'documents.*' => ['file', 'mimes:pdf,doc,docx,xls,xlsx,csv,txt,jpg,jpeg,png,webp', 'max:10240'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $companyId = (int) $this->user()->company_id;
            $productFamilyId = (int) $this->input('product_family_id');

            $family = ProductFamily::query()
                ->visibleToCompany($companyId)
                ->whereKey($productFamilyId)
                ->first();

            if ($family !== null && ! preg_match('/^\d{2}$/', (string) $family->family_code)) {
                $validator->errors()->add(
                    'product_family_id',
                    'A familia selecionada deve ter um codigo de 2 digitos para gerar o codigo do artigo.'
                );
            }

            $vatRate = VatRate::query()
                ->with([
                    'companyOverrides' => fn ($query) => $query->where('company_id', $companyId),
                ])
                ->visibleToCompany($companyId)
                ->whereKey((int) $this->input('vat_rate_id'))
                ->first();

            if (! $vatRate) {
                $validator->errors()->add('vat_rate_id', 'A taxa de IVA selecionada nao e valida.');

                return;
            }

            if (! $vatRate->isEnabledForCompany($companyId)) {
                $validator->errors()->add('vat_rate_id', 'A taxa de IVA selecionada nao esta ativa para a empresa.');
            }

            $reasonId = $this->input('vat_exemption_reason_id') !== null
                ? (int) $this->input('vat_exemption_reason_id')
                : null;

            if ($vatRate->is_exempt) {
                if ($reasonId === null) {
                    $validator->errors()->add(
                        'vat_exemption_reason_id',
                        'Quando a taxa de IVA e isenta, o motivo de isencao e obrigatorio.'
                    );

                    return;
                }

                $reason = VatExemptionReason::query()
                    ->with([
                        'companyOverrides' => fn ($query) => $query->where('company_id', $companyId),
                    ])
                    ->visibleToCompany($companyId)
                    ->whereKey($reasonId)
                    ->first();

                if (! $reason || ! $reason->isEnabledForCompany($companyId)) {
                    $validator->errors()->add(
                        'vat_exemption_reason_id',
                        'O motivo de isencao selecionado nao esta ativo para a empresa.'
                    );
                }
            } elseif ($reasonId !== null) {
                $validator->errors()->add(
                    'vat_exemption_reason_id',
                    'Apenas pode indicar motivo de isencao quando a taxa de IVA e isenta.'
                );
            }

            $movesStock = (bool) $this->input('moves_stock');
            $stockAlertEnabled = (bool) $this->input('stock_alert_enabled');
            $minimumStock = $this->input('minimum_stock');

            if (! $movesStock) {
                if ($stockAlertEnabled) {
                    $validator->errors()->add(
                        'stock_alert_enabled',
                        'Nao pode ativar alerta de stock quando o artigo nao movimenta stock.'
                    );
                }

                if ($minimumStock !== null && (float) $minimumStock > 0) {
                    $validator->errors()->add(
                        'minimum_stock',
                        'Nao pode definir stock minimo quando o artigo nao movimenta stock.'
                    );
                }
            }

            if ($movesStock && ! $stockAlertEnabled && $minimumStock !== null && (float) $minimumStock > 0) {
                $validator->errors()->add(
                    'minimum_stock',
                    'Ative o alerta de stock para definir stock minimo.'
                );
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

