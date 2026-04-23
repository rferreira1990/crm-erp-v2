@php
    $site = $site ?? null;
    $isEdit = isset($site);
    $selectedCustomerId = old('customer_id', $site->customer_id ?? '');
    $selectedCustomerContactId = old('customer_contact_id', $site->customer_contact_id ?? '');
    $selectedQuoteId = old('quote_id', $site->quote_id ?? '');
    $selectedAssignedUserId = old('assigned_user_id', $site->assigned_user_id ?? '');
    $selectedCountryId = old('country_id', $site->country_id ?? '');
    $selectedStatus = old('status', $site->status ?? ($defaults['status'] ?? \App\Models\ConstructionSite::STATUS_DRAFT));
    $quoteStatusLabels = \App\Models\Quote::statusLabels();
@endphp

<div class="card mb-4">
    <div class="card-header bg-body-tertiary">
        <h5 class="mb-0">Identificacao</h5>
    </div>
    <div class="card-body">
        <div class="row g-3">
            @if ($isEdit)
                <div class="col-12 col-md-3">
                    <label class="form-label">Codigo</label>
                    <input type="text" class="form-control" value="{{ $site->code }}" readonly>
                </div>
            @endif
            <div class="col-12 {{ $isEdit ? 'col-md-9' : '' }}">
                <label for="name" class="form-label">Nome da obra</label>
                <input type="text" id="name" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $site->name ?? '') }}" maxlength="190" required>
                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header bg-body-tertiary">
        <h5 class="mb-0">Cliente e Ligacao Comercial</h5>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-12 col-md-4">
                <label for="customer_id" class="form-label">Cliente</label>
                <select id="customer_id" name="customer_id" class="form-select @error('customer_id') is-invalid @enderror" required>
                    <option value="">Selecionar cliente</option>
                    @foreach (($customerOptions ?? []) as $customer)
                        <option value="{{ $customer->id }}" @selected((string) $selectedCustomerId === (string) $customer->id)>
                            {{ $customer->name }}
                        </option>
                    @endforeach
                </select>
                @error('customer_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12 col-md-4">
                <label for="customer_contact_id" class="form-label">Contacto cliente</label>
                <select id="customer_contact_id" name="customer_contact_id" class="form-select @error('customer_contact_id') is-invalid @enderror">
                    <option value="">Sem contacto</option>
                    @foreach (($customerContactOptions ?? []) as $contact)
                        <option value="{{ $contact->id }}" data-customer="{{ $contact->customer_id }}" @selected((string) $selectedCustomerContactId === (string) $contact->id)>
                            {{ $contact->name }}{{ $contact->email ? ' - '.$contact->email : '' }}
                        </option>
                    @endforeach
                </select>
                @error('customer_contact_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12 col-md-4">
                <label for="quote_id" class="form-label">Orcamento associado</label>
                <select id="quote_id" name="quote_id" class="form-select @error('quote_id') is-invalid @enderror">
                    <option value="">Sem orcamento</option>
                    @foreach (($quoteOptions ?? []) as $quote)
                        <option value="{{ $quote->id }}" @selected((string) $selectedQuoteId === (string) $quote->id)>
                            {{ $quote->number }}{{ $quote->customer_name ? ' - '.$quote->customer_name : '' }} ({{ $quoteStatusLabels[$quote->status] ?? $quote->status }})
                        </option>
                    @endforeach
                </select>
                @error('quote_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header bg-body-tertiary">
        <h5 class="mb-0">Localizacao da Obra</h5>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-12">
                <label for="address" class="form-label">Morada</label>
                <input type="text" id="address" name="address" class="form-control @error('address') is-invalid @enderror" value="{{ old('address', $site->address ?? '') }}" maxlength="255">
                @error('address')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12 col-md-3">
                <label for="postal_code" class="form-label">Codigo postal</label>
                <input type="text" id="postal_code" name="postal_code" class="form-control @error('postal_code') is-invalid @enderror" value="{{ old('postal_code', $site->postal_code ?? '') }}" maxlength="8" placeholder="1234-123">
                @error('postal_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12 col-md-3">
                <label for="locality" class="form-label">Localidade</label>
                <input type="text" id="locality" name="locality" class="form-control @error('locality') is-invalid @enderror" value="{{ old('locality', $site->locality ?? '') }}" maxlength="120">
                @error('locality')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12 col-md-3">
                <label for="city" class="form-label">Cidade</label>
                <input type="text" id="city" name="city" class="form-control @error('city') is-invalid @enderror" value="{{ old('city', $site->city ?? '') }}" maxlength="120">
                @error('city')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12 col-md-3">
                <label for="country_id" class="form-label">Pais</label>
                <select id="country_id" name="country_id" class="form-select @error('country_id') is-invalid @enderror">
                    <option value="">Sem pais</option>
                    @foreach (($countryOptions ?? []) as $country)
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
        <h5 class="mb-0">Gestao e Datas</h5>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-12 col-md-4">
                <label for="assigned_user_id" class="form-label">Responsavel interno</label>
                <select id="assigned_user_id" name="assigned_user_id" class="form-select @error('assigned_user_id') is-invalid @enderror">
                    <option value="">Sem responsavel</option>
                    @foreach (($assignedUserOptions ?? []) as $assignedUser)
                        <option value="{{ $assignedUser->id }}" @selected((string) $selectedAssignedUserId === (string) $assignedUser->id)>{{ $assignedUser->name }}</option>
                    @endforeach
                </select>
                @error('assigned_user_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12 col-md-4">
                <label for="status" class="form-label">Estado</label>
                <select id="status" name="status" class="form-select @error('status') is-invalid @enderror" required>
                    @foreach (($statusOptions ?? []) as $key => $label)
                        <option value="{{ $key }}" @selected((string) $selectedStatus === (string) $key)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12 col-md-4 d-flex align-items-end">
                <div class="form-check">
                    <input type="hidden" name="is_active" value="0">
                    <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" @checked(old('is_active', $isEdit ? $site->is_active : ($defaults['is_active'] ?? true)))>
                    <label class="form-check-label" for="is_active">Obra ativa</label>
                </div>
            </div>
            <div class="col-12 col-md-3">
                <label for="planned_start_date" class="form-label">Inicio planeado</label>
                <input type="date" id="planned_start_date" name="planned_start_date" class="form-control @error('planned_start_date') is-invalid @enderror" value="{{ old('planned_start_date', optional($site->planned_start_date ?? null)->format('Y-m-d')) }}">
                @error('planned_start_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12 col-md-3">
                <label for="planned_end_date" class="form-label">Fim planeado</label>
                <input type="date" id="planned_end_date" name="planned_end_date" class="form-control @error('planned_end_date') is-invalid @enderror" value="{{ old('planned_end_date', optional($site->planned_end_date ?? null)->format('Y-m-d')) }}">
                @error('planned_end_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12 col-md-3">
                <label for="actual_start_date" class="form-label">Inicio real</label>
                <input type="date" id="actual_start_date" name="actual_start_date" class="form-control @error('actual_start_date') is-invalid @enderror" value="{{ old('actual_start_date', optional($site->actual_start_date ?? null)->format('Y-m-d')) }}">
                @error('actual_start_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12 col-md-3">
                <label for="actual_end_date" class="form-label">Fim real</label>
                <input type="date" id="actual_end_date" name="actual_end_date" class="form-control @error('actual_end_date') is-invalid @enderror" value="{{ old('actual_end_date', optional($site->actual_end_date ?? null)->format('Y-m-d')) }}">
                @error('actual_end_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header bg-body-tertiary">
        <h5 class="mb-0">Observacoes</h5>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-12 col-md-6">
                <label for="description" class="form-label">Descricao</label>
                <textarea id="description" name="description" rows="4" class="form-control @error('description') is-invalid @enderror" maxlength="5000">{{ old('description', $site->description ?? '') }}</textarea>
                @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12 col-md-6">
                <label for="internal_notes" class="form-label">Notas internas</label>
                <textarea id="internal_notes" name="internal_notes" rows="4" class="form-control @error('internal_notes') is-invalid @enderror" maxlength="5000">{{ old('internal_notes', $site->internal_notes ?? '') }}</textarea>
                @error('internal_notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header bg-body-tertiary">
        <h5 class="mb-0">Uploads</h5>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-12 col-md-6">
                <label for="images" class="form-label">Imagens</label>
                <input type="file" id="images" name="images[]" class="form-control @error('images') is-invalid @enderror @error('images.*') is-invalid @enderror" accept=".jpg,.jpeg,.png,.webp" multiple>
                @error('images')<div class="invalid-feedback">{{ $message }}</div>@enderror
                @error('images.*')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12 col-md-6">
                <label for="documents" class="form-label">Ficheiros</label>
                <input type="file" id="documents" name="documents[]" class="form-control @error('documents') is-invalid @enderror @error('documents.*') is-invalid @enderror" accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.txt,.jpg,.jpeg,.png,.webp" multiple>
                @error('documents')<div class="invalid-feedback">{{ $message }}</div>@enderror
                @error('documents.*')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>
</div>

@if ($isEdit)
    <div class="card mb-4">
        <div class="card-header bg-body-tertiary">
            <h5 class="mb-0">Imagens existentes</h5>
        </div>
        <div class="card-body">
            @forelse ($site->images as $image)
                <div class="d-flex justify-content-between align-items-center border rounded p-2 mb-2">
                    <div>
                        <a href="{{ route('admin.construction-sites.images.show', [$site->id, $image->id]) }}" target="_blank" rel="noopener noreferrer">{{ $image->original_name }}</a>
                        @if ($image->is_primary)
                            <span class="badge badge-phoenix badge-phoenix-success ms-2">Primaria</span>
                        @endif
                    </div>
                    <form method="POST" action="{{ route('admin.construction-sites.images.destroy', [$site->id, $image->id]) }}" data-confirm="Tem a certeza que pretende remover esta imagem?">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-phoenix-danger btn-sm">Remover</button>
                    </form>
                </div>
            @empty
                <div class="text-body-tertiary">Sem imagens registadas.</div>
            @endforelse
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header bg-body-tertiary">
            <h5 class="mb-0">Ficheiros existentes</h5>
        </div>
        <div class="card-body">
            @forelse ($site->files as $file)
                <div class="d-flex justify-content-between align-items-center border rounded p-2 mb-2">
                    <a href="{{ route('admin.construction-sites.files.download', [$site->id, $file->id]) }}" class="fw-semibold">{{ $file->original_name }}</a>
                    <form method="POST" action="{{ route('admin.construction-sites.files.destroy', [$site->id, $file->id]) }}" data-confirm="Tem a certeza que pretende remover este ficheiro?">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-phoenix-danger btn-sm">Remover</button>
                    </form>
                </div>
            @empty
                <div class="text-body-tertiary">Sem ficheiros registados.</div>
            @endforelse
        </div>
    </div>
@endif

<div class="d-flex gap-2 justify-content-end">
    @if ($isEdit)
        <form method="POST" action="{{ route('admin.construction-sites.destroy', $site->id) }}" data-confirm="Tem a certeza que pretende apagar esta obra?">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-phoenix-danger">Apagar obra</button>
        </form>
    @endif
    <a href="{{ route('admin.construction-sites.index') }}" class="btn btn-phoenix-secondary">Cancelar</a>
    <button type="submit" class="btn btn-primary">{{ $isEdit ? 'Guardar alteracoes' : 'Criar obra' }}</button>
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

                const syncContactOptions = () => {
                    const selectedCustomerId = customerSelect.value;
                    let hasVisibleSelected = false;

                    Array.from(contactSelect.options).forEach((option, index) => {
                        if (index === 0) {
                            option.hidden = false;
                            option.disabled = false;
                            return;
                        }

                        const optionCustomerId = option.getAttribute('data-customer');
                        const visible = selectedCustomerId !== '' && optionCustomerId === selectedCustomerId;
                        option.hidden = !visible;
                        option.disabled = !visible;

                        if (visible && option.selected) {
                            hasVisibleSelected = true;
                        }
                    });

                    if (!hasVisibleSelected) {
                        contactSelect.value = '';
                    }
                };

                customerSelect.addEventListener('change', syncContactOptions);
                syncContactOptions();
            });
        </script>
    @endpush
@endonce
