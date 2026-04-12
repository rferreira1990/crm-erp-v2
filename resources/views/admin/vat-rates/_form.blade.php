@php
    $isEdit = isset($vatRate);
@endphp

<div class="row g-3">
    <div class="col-12 col-md-5">
        <label for="name" class="form-label">Nome</label>
        <input
            type="text"
            id="name"
            name="name"
            value="{{ old('name', $vatRate->name ?? '') }}"
            class="form-control @error('name') is-invalid @enderror"
            maxlength="120"
            required
        >
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12 col-md-3">
        <label for="region" class="form-label">Regiao fiscal</label>
        <select id="region" name="region" class="form-select @error('region') is-invalid @enderror">
            <option value="">Sem regiao especifica</option>
            @foreach (($regionOptions ?? []) as $value => $label)
                <option
                    value="{{ $value }}"
                    @selected(old('region', $vatRate->region ?? '') === $value)
                >
                    {{ $label }}
                </option>
            @endforeach
        </select>
        @error('region')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12 col-md-2">
        <label for="rate" class="form-label">Taxa (%)</label>
        <input
            type="number"
            id="rate"
            name="rate"
            value="{{ old('rate', isset($vatRate) ? number_format((float) $vatRate->rate, 2, '.', '') : '0.00') }}"
            class="form-control @error('rate') is-invalid @enderror"
            min="0"
            max="100"
            step="0.01"
            required
        >
        @error('rate')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12 col-md-2">
        <label for="is_exempt" class="form-label">Tipo</label>
        <select id="is_exempt" name="is_exempt" class="form-select @error('is_exempt') is-invalid @enderror" required>
            <option value="0" @selected((int) old('is_exempt', $vatRate->is_exempt ?? 0) === 0)>Normal</option>
            <option value="1" @selected((int) old('is_exempt', $vatRate->is_exempt ?? 0) === 1)>Isento</option>
        </select>
        @error('is_exempt')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12">
        <label for="vat_exemption_reason_id" class="form-label">Motivo de isencao</label>
        <select
            id="vat_exemption_reason_id"
            name="vat_exemption_reason_id"
            class="form-select @error('vat_exemption_reason_id') is-invalid @enderror"
        >
            <option value="">Sem motivo</option>
            @foreach (($exemptionReasons ?? []) as $reason)
                <option
                    value="{{ $reason->id }}"
                    @selected((int) old('vat_exemption_reason_id', $vatRate->vat_exemption_reason_id ?? 0) === (int) $reason->id)
                >
                    {{ $reason->code }} - {{ $reason->name }}
                </option>
            @endforeach
        </select>
        <small class="text-body-secondary">
            Obrigatorio quando o tipo for Isento. Para taxas normais deve ficar sem motivo.
        </small>
        @error('vat_exemption_reason_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12 d-flex gap-2 justify-content-end mt-3">
        <a href="{{ route('admin.vat-rates.index') }}" class="btn btn-phoenix-secondary">Cancelar</a>
        <button type="submit" class="btn btn-primary">
            {{ $isEdit ? 'Guardar alteracoes' : 'Criar taxa de IVA' }}
        </button>
    </div>
</div>

