@php
    $supplier = $supplier ?? null;
    $isEdit = isset($supplier);
    $selectedCountryId = old('country_id', $supplier->country_id ?? ($defaults['country_id'] ?? ''));
    $selectedPaymentTermId = old('payment_term_id', $supplier->payment_term_id ?? '');
    $selectedVatRateId = old('default_vat_rate_id', $supplier->default_vat_rate_id ?? '');
    $selectedPaymentMethodId = old('default_payment_method_id', $supplier->default_payment_method_id ?? '');
    $selectedSupplierType = old('supplier_type', $supplier->supplier_type ?? '');
@endphp

<div class="card mb-4">
    <div class="card-header bg-body-tertiary">
        <h5 class="mb-0">Identificacao</h5>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-12 col-md-4">
                <label for="supplier_type" class="form-label">Tipo de fornecedor</label>
                <select id="supplier_type" name="supplier_type" class="form-select @error('supplier_type') is-invalid @enderror" required>
                    <option value="">Selecionar tipo</option>
                    @foreach (($supplierTypeOptions ?? []) as $key => $label)
                        <option value="{{ $key }}" @selected((string) $selectedSupplierType === (string) $key)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('supplier_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12 col-md-8">
                <label for="name" class="form-label">Designacao</label>
                <input
                    type="text"
                    id="name"
                    name="name"
                    value="{{ old('name', $supplier->name ?? '') }}"
                    class="form-control @error('name') is-invalid @enderror"
                    maxlength="190"
                    required
                >
                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header bg-body-tertiary">
        <h5 class="mb-0">Morada</h5>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-12">
                <label for="address" class="form-label">Morada</label>
                <input type="text" id="address" name="address" value="{{ old('address', $supplier->address ?? '') }}" class="form-control @error('address') is-invalid @enderror" maxlength="255">
                @error('address')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12 col-md-3">
                <label for="postal_code" class="form-label">Codigo postal</label>
                <input type="text" id="postal_code" name="postal_code" value="{{ old('postal_code', $supplier->postal_code ?? '') }}" class="form-control @error('postal_code') is-invalid @enderror" maxlength="8" placeholder="1234-123">
                @error('postal_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12 col-md-4">
                <label for="locality" class="form-label">Localidade</label>
                <input type="text" id="locality" name="locality" value="{{ old('locality', $supplier->locality ?? '') }}" class="form-control @error('locality') is-invalid @enderror" maxlength="120">
                @error('locality')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12 col-md-3">
                <label for="city" class="form-label">Cidade</label>
                <input type="text" id="city" name="city" value="{{ old('city', $supplier->city ?? '') }}" class="form-control @error('city') is-invalid @enderror" maxlength="120">
                @error('city')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12 col-md-2">
                <label for="country_id" class="form-label">Pais</label>
                <select id="country_id" name="country_id" class="form-select @error('country_id') is-invalid @enderror">
                    <option value="">Sem pais</option>
                    @foreach (($countries ?? []) as $country)
                        <option value="{{ $country->id }}" @selected((string) $selectedCountryId === (string) $country->id)>{{ $country->name }}</option>
                    @endforeach
                </select>
                @error('country_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header bg-body-tertiary">
        <h5 class="mb-0">Fiscal e Contacto</h5>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-12 col-md-3">
                <label for="nif" class="form-label">NIF</label>
                <input type="text" id="nif" name="nif" value="{{ old('nif', $supplier->nif ?? '') }}" class="form-control @error('nif') is-invalid @enderror" maxlength="9" placeholder="9 digitos">
                @error('nif')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12 col-md-3">
                <label for="phone" class="form-label">Telefone</label>
                <input type="text" id="phone" name="phone" value="{{ old('phone', $supplier->phone ?? '') }}" class="form-control @error('phone') is-invalid @enderror" maxlength="30">
                @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12 col-md-3">
                <label for="mobile" class="form-label">Telemovel</label>
                <input type="text" id="mobile" name="mobile" value="{{ old('mobile', $supplier->mobile ?? '') }}" class="form-control @error('mobile') is-invalid @enderror" maxlength="30">
                @error('mobile')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12 col-md-3">
                <label for="email" class="form-label">Email</label>
                <input type="email" id="email" name="email" value="{{ old('email', $supplier->email ?? '') }}" class="form-control @error('email') is-invalid @enderror" maxlength="190">
                @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12">
                <label for="website" class="form-label">Website</label>
                <input type="url" id="website" name="website" value="{{ old('website', $supplier->website ?? '') }}" class="form-control @error('website') is-invalid @enderror" maxlength="255" placeholder="https://">
                @error('website')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header bg-body-tertiary">
        <h5 class="mb-0">Condicoes Financeiras</h5>
    </div>
    <div class="card-body">
        <div class="row g-3">
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
                <label for="default_vat_rate_id" class="form-label">Taxa IVA habitual</label>
                <select id="default_vat_rate_id" name="default_vat_rate_id" class="form-select @error('default_vat_rate_id') is-invalid @enderror">
                    <option value="">Sem taxa habitual</option>
                    @foreach (($vatRateOptions ?? []) as $vatRateOption)
                        <option value="{{ $vatRateOption->id }}" @selected((string) $selectedVatRateId === (string) $vatRateOption->id)>
                            {{ $vatRateOption->name }} ({{ number_format((float) $vatRateOption->rate, 2) }}%)
                        </option>
                    @endforeach
                </select>
                @error('default_vat_rate_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12 col-md-4">
                <label for="default_payment_method_id" class="form-label">Modo de pagamento habitual</label>
                <select id="default_payment_method_id" name="default_payment_method_id" class="form-select @error('default_payment_method_id') is-invalid @enderror">
                    <option value="">Sem modo habitual</option>
                    @foreach (($paymentMethodOptions ?? []) as $paymentMethodOption)
                        <option value="{{ $paymentMethodOption->id }}" @selected((string) $selectedPaymentMethodId === (string) $paymentMethodOption->id)>
                            {{ $paymentMethodOption->name }}
                        </option>
                    @endforeach
                </select>
                @error('default_payment_method_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12 col-md-4">
                <label for="iban" class="form-label">IBAN</label>
                <input type="text" id="iban" name="iban" value="{{ old('iban', $supplier->iban ?? '') }}" class="form-control @error('iban') is-invalid @enderror" maxlength="34">
                @error('iban')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header bg-body-tertiary">
        <h5 class="mb-0">Notas e Pagamentos</h5>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-12 col-md-6">
                <label for="notes" class="form-label">Notas</label>
                <textarea id="notes" name="notes" rows="4" class="form-control @error('notes') is-invalid @enderror" maxlength="5000">{{ old('notes', $supplier->notes ?? '') }}</textarea>
                @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12 col-md-6">
                <label for="payment_notes" class="form-label">Notas de pagamento</label>
                <textarea id="payment_notes" name="payment_notes" rows="4" class="form-control @error('payment_notes') is-invalid @enderror" maxlength="5000">{{ old('payment_notes', $supplier->payment_notes ?? '') }}</textarea>
                @error('payment_notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header bg-body-tertiary">
        <h5 class="mb-0">Logotipo e Estado</h5>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-12 col-md-6">
                <label for="logo" class="form-label">Logotipo</label>
                <input type="file" id="logo" name="logo" class="form-control @error('logo') is-invalid @enderror" accept=".jpg,.jpeg,.png,.webp,.svg">
                @error('logo')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            @if ($isEdit && $supplier->logo_path)
                <div class="col-12 col-md-6 d-flex align-items-end">
                    <div>
                        <a href="{{ route('admin.suppliers.logo.show', $supplier->id) }}" target="_blank" rel="noopener noreferrer" class="btn btn-phoenix-secondary btn-sm">
                            Ver logotipo atual
                        </a>
                        <div class="form-check mt-2">
                            <input class="form-check-input" type="checkbox" id="remove_logo" name="remove_logo" value="1" @checked(old('remove_logo', false))>
                            <label class="form-check-label" for="remove_logo">Remover logotipo atual</label>
                        </div>
                        @error('remove_logo')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                </div>
            @endif
            <div class="col-12">
                <div class="form-check mt-2">
                    <input type="hidden" name="is_active" value="0">
                    <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" @checked(old('is_active', $isEdit ? $supplier->is_active : ($defaults['is_active'] ?? true)))>
                    <label class="form-check-label" for="is_active">Fornecedor ativo</label>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="d-flex gap-2 justify-content-end">
    <a href="{{ route('admin.suppliers.index') }}" class="btn btn-phoenix-secondary">Cancelar</a>
    <button type="submit" class="btn btn-primary">{{ $isEdit ? 'Guardar alteracoes' : 'Criar fornecedor' }}</button>
</div>
