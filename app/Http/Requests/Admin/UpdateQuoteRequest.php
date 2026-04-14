<?php

namespace App\Http\Requests\Admin;

use App\Models\Article;
use App\Models\Customer;
use App\Models\CustomerContact;
use App\Models\PaymentMethod;
use App\Models\PaymentTerm;
use App\Models\PriceTier;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\Unit;
use App\Models\User;
use App\Models\VatExemptionReason;
use App\Models\VatRate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateQuoteRequest extends FormRequest
{
    private ?Quote $resolvedQuote = null;
    private bool $resolvedQuoteLoaded = false;

    protected function prepareForValidation(): void
    {
        $items = $this->normalizeItems($this->input('items', []));

        $this->merge([
            'title' => $this->normalizeNullableString($this->input('title')),
            'subject' => $this->normalizeNullableString($this->input('subject')),
            'customer_id' => $this->normalizeNullableInteger($this->input('customer_id')),
            'customer_contact_id' => $this->normalizeNullableInteger($this->input('customer_contact_id')),
            'issue_date' => $this->normalizeNullableString($this->input('issue_date')),
            'valid_until' => $this->normalizeNullableString($this->input('valid_until')),
            'price_tier_id' => $this->normalizeNullableInteger($this->input('price_tier_id')),
            'payment_term_id' => $this->normalizeNullableInteger($this->input('payment_term_id')),
            'payment_method_id' => $this->normalizeNullableInteger($this->input('payment_method_id')),
            'currency' => strtoupper($this->normalizeNullableString($this->input('currency')) ?? 'EUR'),
            'default_vat_rate_id' => $this->normalizeNullableInteger($this->input('default_vat_rate_id')),
            'header_notes' => $this->normalizeNullableString($this->input('header_notes')),
            'footer_notes' => $this->normalizeNullableString($this->input('footer_notes')),
            'internal_notes' => $this->normalizeNullableString($this->input('internal_notes')),
            'customer_message' => $this->normalizeNullableString($this->input('customer_message')),
            'print_comments' => $this->normalizeNullableString($this->input('print_comments')),
            'assigned_user_id' => $this->normalizeNullableInteger($this->input('assigned_user_id')),
            'follow_up_date' => $this->normalizeNullableString($this->input('follow_up_date')),
            'is_active' => $this->boolean('is_active', true),
            'items' => $items,
        ]);
    }

    public function authorize(): bool
    {
        $user = $this->user();

        return $user
            && $user->isCompanyUser()
            && $user->can('company.quotes.update');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:190'],
            'subject' => ['nullable', 'string', 'max:255'],
            'customer_id' => ['required', 'integer'],
            'customer_contact_id' => ['nullable', 'integer'],
            'issue_date' => ['required', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:issue_date'],
            'price_tier_id' => ['nullable', 'integer'],
            'payment_term_id' => ['nullable', 'integer'],
            'payment_method_id' => ['nullable', 'integer'],
            'currency' => ['required', 'string', 'size:3'],
            'default_vat_rate_id' => ['nullable', 'integer'],
            'header_notes' => ['nullable', 'string', 'max:5000'],
            'footer_notes' => ['nullable', 'string', 'max:5000'],
            'internal_notes' => ['nullable', 'string', 'max:5000'],
            'customer_message' => ['nullable', 'string', 'max:5000'],
            'print_comments' => ['nullable', 'string', 'max:5000'],
            'assigned_user_id' => ['nullable', 'integer'],
            'follow_up_date' => ['nullable', 'date'],
            'is_active' => ['required', 'boolean'],

            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.sort_order' => ['nullable', 'integer', 'min:1'],
            'items.*.line_type' => ['required', 'string', Rule::in(QuoteItem::lineTypes())],
            'items.*.article_id' => ['nullable', 'integer'],
            'items.*.description' => ['nullable', 'string', 'max:5000'],
            'items.*.internal_description' => ['nullable', 'string', 'max:5000'],
            'items.*.quantity' => ['nullable', 'numeric', 'min:0'],
            'items.*.unit_id' => ['nullable', 'integer'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.discount_percent' => ['nullable', 'numeric', 'between:0,100'],
            'items.*.vat_rate_id' => ['nullable', 'integer'],
            'items.*.vat_exemption_reason_id' => ['nullable', 'integer'],
            'items.*.metadata' => ['nullable', 'array'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $companyId = (int) $this->user()->company_id;

            $customer = Customer::query()
                ->forCompany($companyId)
                ->whereKey((int) $this->input('customer_id'))
                ->first();

            if (! $customer) {
                $validator->errors()->add('customer_id', 'O cliente selecionado nao esta disponivel para a empresa.');

                return;
            }

            $customerContactId = $this->input('customer_contact_id');
            if ($customerContactId !== null) {
                $customerContact = CustomerContact::query()
                    ->forCompany($companyId)
                    ->whereKey((int) $customerContactId)
                    ->where('customer_id', $customer->id)
                    ->first();

                if (! $customerContact) {
                    $validator->errors()->add('customer_contact_id', 'O contacto selecionado nao pertence ao cliente.');
                }
            }

            $this->validatePriceTier($validator, $companyId);
            $this->validatePaymentTerm($validator, $companyId);
            $this->validatePaymentMethod($validator, $companyId);
            $this->validateDefaultVatRate($validator, $companyId);
            $this->validateAssignedUser($validator, $companyId);

            $this->validateItems($validator, $companyId);
        });
    }

    private function validatePriceTier(Validator $validator, int $companyId): void
    {
        $quote = $this->currentQuote($companyId);
        $allowedInactivePriceTierId = $quote?->price_tier_id !== null ? (int) $quote->price_tier_id : null;

        $priceTierId = $this->input('price_tier_id');
        if ($priceTierId === null) {
            return;
        }

        $exists = PriceTier::query()
            ->visibleToCompany($companyId)
            ->where(function ($query) use ($allowedInactivePriceTierId): void {
                $query->where('is_active', true);

                if ($allowedInactivePriceTierId !== null) {
                    $query->orWhere('id', $allowedInactivePriceTierId);
                }
            })
            ->whereKey((int) $priceTierId)
            ->exists();

        if (! $exists) {
            $validator->errors()->add('price_tier_id', 'O escalao de preco selecionado nao esta disponivel para a empresa.');
        }
    }

    private function validatePaymentTerm(Validator $validator, int $companyId): void
    {
        $quote = $this->currentQuote($companyId);
        $allowedHistoricalTermId = $quote?->payment_term_id !== null ? (int) $quote->payment_term_id : null;

        $paymentTermId = $this->input('payment_term_id');
        if ($paymentTermId === null) {
            return;
        }

        $exists = PaymentTerm::query()->where(function ($query) use ($companyId, $allowedHistoricalTermId): void {
            $query->visibleToCompany($companyId);

            if ($allowedHistoricalTermId !== null) {
                $query->orWhere(function ($selectedQuery) use ($companyId, $allowedHistoricalTermId): void {
                    $selectedQuery->where('id', $allowedHistoricalTermId)
                        ->where(function ($ownershipQuery) use ($companyId): void {
                            $ownershipQuery
                                ->where('company_id', $companyId)
                                ->orWhere(function ($systemQuery): void {
                                    $systemQuery->where('is_system', true)
                                        ->whereNull('company_id');
                                });
                        });
                });
            }
        })->whereKey((int) $paymentTermId)->exists();

        if (! $exists) {
            $validator->errors()->add('payment_term_id', 'A condicao de pagamento selecionada nao esta disponivel para a empresa.');
        }
    }

    private function validatePaymentMethod(Validator $validator, int $companyId): void
    {
        $quote = $this->currentQuote($companyId);
        $allowedHistoricalMethodId = $quote?->payment_method_id !== null ? (int) $quote->payment_method_id : null;

        $paymentMethodId = $this->input('payment_method_id');
        if ($paymentMethodId === null) {
            return;
        }

        $exists = PaymentMethod::query()->where(function ($query) use ($companyId, $allowedHistoricalMethodId): void {
            $query->visibleToCompany($companyId);

            if ($allowedHistoricalMethodId !== null) {
                $query->orWhere(function ($selectedQuery) use ($companyId, $allowedHistoricalMethodId): void {
                    $selectedQuery->where('id', $allowedHistoricalMethodId)
                        ->where(function ($ownershipQuery) use ($companyId): void {
                            $ownershipQuery
                                ->where('company_id', $companyId)
                                ->orWhere(function ($systemQuery): void {
                                    $systemQuery->where('is_system', true)
                                        ->whereNull('company_id');
                                });
                        });
                });
            }
        })->whereKey((int) $paymentMethodId)->exists();

        if (! $exists) {
            $validator->errors()->add('payment_method_id', 'O modo de pagamento selecionado nao esta disponivel para a empresa.');
        }
    }

    private function validateDefaultVatRate(Validator $validator, int $companyId): void
    {
        $quote = $this->currentQuote($companyId);
        $allowedHistoricalVatRateId = $quote?->default_vat_rate_id !== null ? (int) $quote->default_vat_rate_id : null;

        $vatRateId = $this->input('default_vat_rate_id');
        if ($vatRateId === null) {
            return;
        }

        $vatRate = VatRate::query()
            ->with([
                'companyOverrides' => fn ($query) => $query->where('company_id', $companyId),
            ])
            ->visibleToCompany($companyId)
            ->whereKey((int) $vatRateId)
            ->first();

        if (! $vatRate) {
            $validator->errors()->add('default_vat_rate_id', 'A taxa de IVA habitual nao esta ativa para a empresa.');

            return;
        }

        if (! $vatRate->isEnabledForCompany($companyId) && (int) $vatRate->id !== $allowedHistoricalVatRateId) {
            $validator->errors()->add('default_vat_rate_id', 'A taxa de IVA habitual nao esta ativa para a empresa.');
        }
    }

    private function validateAssignedUser(Validator $validator, int $companyId): void
    {
        $assignedUserId = $this->input('assigned_user_id');
        if ($assignedUserId === null) {
            return;
        }

        $exists = User::query()
            ->where('is_super_admin', false)
            ->where('is_active', true)
            ->where('company_id', $companyId)
            ->whereKey((int) $assignedUserId)
            ->exists();

        if (! $exists) {
            $validator->errors()->add('assigned_user_id', 'O responsavel selecionado nao esta disponivel para a empresa.');
        }
    }

    private function validateItems(Validator $validator, int $companyId): void
    {
        $quote = $this->currentQuote($companyId);
        $allowedHistoricalVatRateIds = $quote?->items()
            ->whereNotNull('vat_rate_id')
            ->pluck('vat_rate_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all() ?? [];
        $allowedHistoricalReasonIds = $quote?->items()
            ->whereNotNull('vat_exemption_reason_id')
            ->pluck('vat_exemption_reason_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all() ?? [];

        $items = $this->input('items', []);

        foreach ($items as $index => $item) {
            $prefix = "items.$index";
            $lineType = (string) ($item['line_type'] ?? '');
            $articleId = $item['article_id'] ?? null;
            $description = trim((string) ($item['description'] ?? ''));
            $quantity = (float) ($item['quantity'] ?? 0);
            $unitPrice = (float) ($item['unit_price'] ?? 0);
            $vatRateId = $item['vat_rate_id'] ?? null;
            $reasonId = $item['vat_exemption_reason_id'] ?? null;
            $unitId = $item['unit_id'] ?? null;

            if ($lineType === QuoteItem::TYPE_ARTICLE) {
                if ($articleId === null) {
                    $validator->errors()->add("$prefix.article_id", 'A linha de artigo exige artigo selecionado.');
                } else {
                    $article = Article::query()
                        ->forCompany($companyId)
                        ->whereKey((int) $articleId)
                        ->first();

                    if (! $article) {
                        $validator->errors()->add("$prefix.article_id", 'O artigo selecionado nao esta disponivel para a empresa.');
                    }
                }
            }

            if (in_array($lineType, [QuoteItem::TYPE_TEXT, QuoteItem::TYPE_SECTION, QuoteItem::TYPE_NOTE], true) && $description === '') {
                $validator->errors()->add("$prefix.description", 'A descricao da linha e obrigatoria.');
            }

            if (in_array($lineType, [QuoteItem::TYPE_ARTICLE, QuoteItem::TYPE_TEXT], true)) {
                if ($quantity <= 0) {
                    $validator->errors()->add("$prefix.quantity", 'A quantidade deve ser superior a zero.');
                }

                if ($unitPrice < 0) {
                    $validator->errors()->add("$prefix.unit_price", 'O preco unitario nao pode ser negativo.');
                }
            }

            if ($unitId !== null) {
                $unitExists = Unit::query()
                    ->visibleToCompany($companyId)
                    ->whereKey((int) $unitId)
                    ->exists();

                if (! $unitExists) {
                    $validator->errors()->add("$prefix.unit_id", 'A unidade selecionada nao esta disponivel para a empresa.');
                }
            }

            if ($vatRateId === null) {
                if ($reasonId !== null) {
                    $validator->errors()->add("$prefix.vat_exemption_reason_id", 'Nao pode indicar motivo de isencao sem taxa de IVA.');
                }

                continue;
            }

            $vatRate = VatRate::query()
                ->with([
                    'companyOverrides' => fn ($query) => $query->where('company_id', $companyId),
                ])
                ->visibleToCompany($companyId)
                ->whereKey((int) $vatRateId)
                ->first();

            if (! $vatRate) {
                $validator->errors()->add("$prefix.vat_rate_id", 'A taxa de IVA selecionada nao esta ativa para a empresa.');
                continue;
            }

            if (! $vatRate->isEnabledForCompany($companyId) && ! in_array((int) $vatRate->id, $allowedHistoricalVatRateIds, true)) {
                $validator->errors()->add("$prefix.vat_rate_id", 'A taxa de IVA selecionada nao esta ativa para a empresa.');
                continue;
            }

            if ($vatRate->is_exempt) {
                if ($reasonId === null) {
                    $validator->errors()->add("$prefix.vat_exemption_reason_id", 'Quando a taxa e isenta, o motivo de isencao e obrigatorio.');
                    continue;
                }

                $reason = VatExemptionReason::query()
                    ->with([
                        'companyOverrides' => fn ($query) => $query->where('company_id', $companyId),
                    ])
                    ->visibleToCompany($companyId)
                    ->whereKey((int) $reasonId)
                    ->first();

                if (! $reason) {
                    $validator->errors()->add("$prefix.vat_exemption_reason_id", 'O motivo de isencao selecionado nao esta ativo para a empresa.');
                    continue;
                }

                if (! $reason->isEnabledForCompany($companyId) && ! in_array((int) $reason->id, $allowedHistoricalReasonIds, true)) {
                    $validator->errors()->add("$prefix.vat_exemption_reason_id", 'O motivo de isencao selecionado nao esta ativo para a empresa.');
                }
            } elseif ($reasonId !== null) {
                $validator->errors()->add("$prefix.vat_exemption_reason_id", 'Apenas pode indicar motivo de isencao em taxas isentas.');
            }
        }
    }

    private function normalizeItems(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        $normalized = [];
        foreach ($items as $index => $item) {
            if (! is_array($item)) {
                continue;
            }

            $normalized[] = [
                'sort_order' => $this->normalizeNullableInteger($item['sort_order'] ?? ($index + 1)),
                'line_type' => strtolower(trim((string) ($item['line_type'] ?? QuoteItem::TYPE_ARTICLE))),
                'article_id' => $this->normalizeNullableInteger($item['article_id'] ?? null),
                'description' => $this->normalizeNullableString($item['description'] ?? null),
                'internal_description' => $this->normalizeNullableString($item['internal_description'] ?? null),
                'quantity' => $this->normalizeNullableNumeric($item['quantity'] ?? null),
                'unit_id' => $this->normalizeNullableInteger($item['unit_id'] ?? null),
                'unit_price' => $this->normalizeNullableNumeric($item['unit_price'] ?? null),
                'discount_percent' => $this->normalizeNullableNumeric($item['discount_percent'] ?? null),
                'vat_rate_id' => $this->normalizeNullableInteger($item['vat_rate_id'] ?? null),
                'vat_exemption_reason_id' => $this->normalizeNullableInteger($item['vat_exemption_reason_id'] ?? null),
                'metadata' => is_array($item['metadata'] ?? null) ? $item['metadata'] : null,
            ];
        }

        return $normalized;
    }

    private function normalizeNullableInteger(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private function normalizeNullableNumeric(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) str_replace(',', '.', (string) $value);
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }

    private function currentQuote(int $companyId): ?Quote
    {
        if ($this->resolvedQuoteLoaded) {
            return $this->resolvedQuote;
        }

        $quoteId = (int) $this->route('quote');
        if ($quoteId <= 0) {
            $this->resolvedQuoteLoaded = true;
            return null;
        }

        $this->resolvedQuote = Quote::query()
            ->forCompany($companyId)
            ->whereKey($quoteId)
            ->first();
        $this->resolvedQuoteLoaded = true;

        return $this->resolvedQuote;
    }
}
