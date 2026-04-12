@php
    $isEdit = isset($reason);
@endphp

<div class="row g-3">
    <div class="col-12 col-md-3">
        <label for="code" class="form-label">Codigo</label>
        <input
            type="text"
            id="code"
            name="code"
            value="{{ old('code', $reason->code ?? '') }}"
            class="form-control @error('code') is-invalid @enderror"
            maxlength="20"
            required
        >
        @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12 col-md-9">
        <label for="name" class="form-label">Nome</label>
        <input
            type="text"
            id="name"
            name="name"
            value="{{ old('name', $reason->name ?? '') }}"
            class="form-control @error('name') is-invalid @enderror"
            maxlength="190"
            required
        >
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12">
        <label for="legal_reference" class="form-label">Enquadramento legal</label>
        <input
            type="text"
            id="legal_reference"
            name="legal_reference"
            value="{{ old('legal_reference', $reason->legal_reference ?? '') }}"
            class="form-control @error('legal_reference') is-invalid @enderror"
            maxlength="255"
        >
        @error('legal_reference')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12 d-flex gap-2 justify-content-end mt-3">
        <a href="{{ route('admin.vat-exemption-reasons.index') }}" class="btn btn-phoenix-secondary">Cancelar</a>
        <button type="submit" class="btn btn-primary">
            {{ $isEdit ? 'Guardar alteracoes' : 'Criar motivo de isencao' }}
        </button>
    </div>
</div>

