@php
    $quote = $quote ?? null;
    $isEdit = isset($quote);
    $selectedCustomerId = old('customer_id', $quote->customer_id ?? '');
    $selectedCustomerContactId = old('customer_contact_id', $quote->customer_contact_id ?? '');
    $selectedPriceTierId = old('price_tier_id', $quote->price_tier_id ?? '');
    $selectedPaymentTermId = old('payment_term_id', $quote->payment_term_id ?? '');
    $selectedPaymentMethodId = old('payment_method_id', $quote->payment_method_id ?? '');
    $selectedVatRateId = old('default_vat_rate_id', $quote->default_vat_rate_id ?? '');
    $selectedAssignedUserId = old('assigned_user_id', $quote->assigned_user_id ?? '');
    $currencyValue = old('currency', $quote->currency ?? ($defaults['currency'] ?? 'EUR'));
@endphp

<div class="card mb-4">
    <div class="card-header bg-body-tertiary">
        <h5 class="mb-0">Cabecalho</h5>
    </div>
    <div class="card-body">
        <div class="row g-3">
            @if ($isEdit)
                <div class="col-12 col-md-3">
                    <label class="form-label">Numero</label>
                    <input type="text" class="form-control" value="{{ $quote->number }}" readonly>
                </div>
                <div class="col-12 col-md-2">
                    <label class="form-label">Versao</label>
                    <input type="text" class="form-control" value="{{ $quote->version }}" readonly>
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label">Estado</label>
                    <input type="text" class="form-control" value="{{ $statusLabels[$quote->status] ?? $quote->status }}" readonly>
                </div>
            @endif

            <div class="col-12 col-md-{{ $isEdit ? '4' : '6' }}">
                <label for="issue_date" class="form-label">Data emissao</label>
                <input type="date" id="issue_date" name="issue_date" value="{{ old('issue_date', optional($quote->issue_date ?? null)->format('Y-m-d') ?? ($defaults['issue_date'] ?? now()->format('Y-m-d'))) }}" class="form-control @error('issue_date') is-invalid @enderror" required>
                @error('issue_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12 col-md-3">
                <label for="valid_until" class="form-label">Valido ate</label>
                <input type="date" id="valid_until" name="valid_until" value="{{ old('valid_until', optional($quote->valid_until ?? null)->format('Y-m-d')) }}" class="form-control @error('valid_until') is-invalid @enderror">
                @error('valid_until')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12 col-md-3">
                <label for="currency" class="form-label">Moeda</label>
                <input type="text" id="currency" name="currency" value="{{ $currencyValue }}" class="form-control @error('currency') is-invalid @enderror" maxlength="3" required>
                @error('currency')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12 col-md-6">
                <label for="title" class="form-label">Titulo</label>
                <input type="text" id="title" name="title" value="{{ old('title', $quote->title ?? '') }}" class="form-control @error('title') is-invalid @enderror" maxlength="190">
                @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12 col-md-6">
                <label for="subject" class="form-label">Assunto</label>
                <input type="text" id="subject" name="subject" value="{{ old('subject', $quote->subject ?? '') }}" class="form-control @error('subject') is-invalid @enderror" maxlength="255">
                @error('subject')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header bg-body-tertiary">
        <h5 class="mb-0">Cliente e Condicoes Comerciais</h5>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-12 col-md-4">
                <label for="customer_id" class="form-label">Cliente</label>
                <select id="customer_id" name="customer_id" class="form-select @error('customer_id') is-invalid @enderror" required>
                    <option value="">Selecionar cliente</option>
                    @foreach (($customers ?? []) as $customer)
                        <option
                            value="{{ $customer->id }}"
                            data-email="{{ $customer->email }}"
                            data-price-tier-id="{{ $customer->price_tier_id }}"
                            data-payment-term-id="{{ $customer->payment_term_id }}"
                            data-vat-rate-id="{{ $customer->default_vat_rate_id }}"
                            @selected((string) $selectedCustomerId === (string) $customer->id)
                        >
                            {{ $customer->name }}
                        </option>
                    @endforeach
                </select>
                @error('customer_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12 col-md-4">
                <label for="customer_contact_id" class="form-label">Contacto</label>
                <select id="customer_contact_id" name="customer_contact_id" class="form-select @error('customer_contact_id') is-invalid @enderror">
                    <option value="">Sem contacto</option>
                    @foreach (($customerContacts ?? []) as $contact)
                        <option value="{{ $contact->id }}" data-customer-id="{{ $contact->customer_id }}" @selected((string) $selectedCustomerContactId === (string) $contact->id)>
                            {{ $contact->name }}{{ $contact->email ? ' - '.$contact->email : '' }}
                        </option>
                    @endforeach
                </select>
                @error('customer_contact_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12 col-md-4">
                <label for="assigned_user_id" class="form-label">Responsavel</label>
                <select id="assigned_user_id" name="assigned_user_id" class="form-select @error('assigned_user_id') is-invalid @enderror">
                    <option value="">Sem responsavel</option>
                    @foreach (($assignedUserOptions ?? []) as $assignedUserOption)
                        <option value="{{ $assignedUserOption->id }}" @selected((string) $selectedAssignedUserId === (string) $assignedUserOption->id)>
                            {{ $assignedUserOption->name }}
                        </option>
                    @endforeach
                </select>
                @error('assigned_user_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12 col-md-4">
                <label for="price_tier_id" class="form-label">Escalao de preco</label>
                <select id="price_tier_id" name="price_tier_id" class="form-select @error('price_tier_id') is-invalid @enderror">
                    <option value="">Default</option>
                    @foreach (($priceTierOptions ?? []) as $priceTierOption)
                        <option value="{{ $priceTierOption->id }}" @selected((string) $selectedPriceTierId === (string) $priceTierOption->id)>
                            {{ $priceTierOption->name }} ({{ number_format((float) $priceTierOption->percentage_adjustment, 2) }}%)
                        </option>
                    @endforeach
                </select>
                @error('price_tier_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12 col-md-4">
                <label for="payment_term_id" class="form-label">Condicao de pagamento</label>
                <select id="payment_term_id" name="payment_term_id" class="form-select @error('payment_term_id') is-invalid @enderror">
                    <option value="">Sem condicao</option>
                    @foreach (($paymentTermOptions ?? []) as $paymentTermOption)
                        <option value="{{ $paymentTermOption->id }}" @selected((string) $selectedPaymentTermId === (string) $paymentTermOption->id)>
                            {{ $paymentTermOption->name }}
                        </option>
                    @endforeach
                </select>
                @error('payment_term_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12 col-md-4">
                <label for="payment_method_id" class="form-label">Modo de pagamento</label>
                <select id="payment_method_id" name="payment_method_id" class="form-select @error('payment_method_id') is-invalid @enderror">
                    <option value="">Sem modo</option>
                    @foreach (($paymentMethodOptions ?? []) as $paymentMethodOption)
                        <option value="{{ $paymentMethodOption->id }}" @selected((string) $selectedPaymentMethodId === (string) $paymentMethodOption->id)>
                            {{ $paymentMethodOption->name }}
                        </option>
                    @endforeach
                </select>
                @error('payment_method_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12 col-md-4">
                <label for="default_vat_rate_id" class="form-label">Taxa IVA por defeito</label>
                <select id="default_vat_rate_id" name="default_vat_rate_id" class="form-select @error('default_vat_rate_id') is-invalid @enderror">
                    <option value="">Sem taxa</option>
                    @foreach (($vatRateOptions ?? []) as $vatRateOption)
                        <option value="{{ $vatRateOption->id }}" @selected((string) $selectedVatRateId === (string) $vatRateOption->id)>
                            {{ $vatRateOption->name }} ({{ number_format((float) $vatRateOption->rate, 2) }}%)
                        </option>
                    @endforeach
                </select>
                @error('default_vat_rate_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12 col-md-4">
                <label for="follow_up_date" class="form-label">Data follow-up</label>
                <input type="date" id="follow_up_date" name="follow_up_date" value="{{ old('follow_up_date', optional($quote->follow_up_date ?? null)->format('Y-m-d')) }}" class="form-control @error('follow_up_date') is-invalid @enderror">
                @error('follow_up_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12 col-md-4">
                <div class="form-check mt-4">
                    <input type="hidden" name="is_active" value="0">
                    <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" @checked(old('is_active', $isEdit ? $quote->is_active : ($defaults['is_active'] ?? true)))>
                    <label class="form-check-label" for="is_active">Orcamento ativo</label>
                </div>
            </div>
        </div>
    </div>
</div>

@include('admin.quotes._items')

@include('admin.quotes._totals', [
    'subtotal' => old('subtotal', $quote->subtotal ?? 0),
    'discountTotal' => old('discount_total', $quote->discount_total ?? 0),
    'taxTotal' => old('tax_total', $quote->tax_total ?? 0),
    'grandTotal' => old('grand_total', $quote->grand_total ?? 0),
    'currency' => $currencyValue,
])

<div class="card mb-4">
    <div class="card-header bg-body-tertiary">
        <h5 class="mb-0">Notas</h5>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-12 col-md-6">
                <label for="header_notes" class="form-label">Notas de cabecalho</label>
                <textarea id="header_notes" name="header_notes" rows="3" class="form-control @error('header_notes') is-invalid @enderror">{{ old('header_notes', $quote->header_notes ?? '') }}</textarea>
                @error('header_notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12 col-md-6">
                <label for="footer_notes" class="form-label">Notas de rodape</label>
                <textarea id="footer_notes" name="footer_notes" rows="3" class="form-control @error('footer_notes') is-invalid @enderror">{{ old('footer_notes', $quote->footer_notes ?? '') }}</textarea>
                @error('footer_notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12 col-md-6">
                <label for="customer_message" class="form-label">Mensagem ao cliente</label>
                <textarea id="customer_message" name="customer_message" rows="3" class="form-control @error('customer_message') is-invalid @enderror">{{ old('customer_message', $quote->customer_message ?? '') }}</textarea>
                @error('customer_message')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12 col-md-6">
                <label for="internal_notes" class="form-label">Notas internas</label>
                <textarea id="internal_notes" name="internal_notes" rows="3" class="form-control @error('internal_notes') is-invalid @enderror">{{ old('internal_notes', $quote->internal_notes ?? '') }}</textarea>
                @error('internal_notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12">
                <label for="print_comments" class="form-label">Comentarios para impressao</label>
                <textarea id="print_comments" name="print_comments" rows="3" class="form-control @error('print_comments') is-invalid @enderror">{{ old('print_comments', $quote->print_comments ?? '') }}</textarea>
                @error('print_comments')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>
</div>

<div class="d-flex gap-2 justify-content-end">
    <a href="{{ route('admin.quotes.index') }}" class="btn btn-phoenix-secondary">Cancelar</a>
    <button type="submit" class="btn btn-primary">{{ $isEdit ? 'Guardar alteracoes' : 'Criar orcamento' }}</button>
</div>

@once
    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const customerSelect = document.getElementById('customer_id');
                const contactSelect = document.getElementById('customer_contact_id');

                if (!customerSelect || !contactSelect) {
                    return;
                }

                const selectedContactBeforeFilter = contactSelect.value;

                const filterContacts = () => {
                    const customerId = customerSelect.value;
                    let hasSelected = false;

                    Array.from(contactSelect.options).forEach((option) => {
                        if (!option.value) {
                            option.hidden = false;
                            return;
                        }

                        const matches = !customerId || option.dataset.customerId === customerId;
                        option.hidden = !matches;

                        if (matches && option.value === contactSelect.value) {
                            hasSelected = true;
                        }
                    });

                    if (!hasSelected) {
                        contactSelect.value = '';
                    }
                };

                customerSelect.addEventListener('change', filterContacts);
                filterContacts();

                if (selectedContactBeforeFilter && !contactSelect.value) {
                    contactSelect.value = selectedContactBeforeFilter;
                    filterContacts();
                }
            });
        </script>
    @endpush
@endonce

