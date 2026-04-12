@php
    $isEdit = isset($unit);
@endphp

<div class="row g-3">
    <div class="col-12 col-md-4">
        <label for="code" class="form-label">Codigo</label>
        <input
            type="text"
            id="code"
            name="code"
            value="{{ old('code', $unit->code ?? '') }}"
            class="form-control @error('code') is-invalid @enderror"
            placeholder="Ex: KG"
            maxlength="20"
            required
        >
        @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12 col-md-8">
        <label for="name" class="form-label">Nome</label>
        <input
            type="text"
            id="name"
            name="name"
            value="{{ old('name', $unit->name ?? '') }}"
            class="form-control @error('name') is-invalid @enderror"
            placeholder="Ex: Quilograma"
            maxlength="100"
            required
        >
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12 d-flex gap-2 justify-content-end mt-3">
        <a href="{{ route('admin.units.index') }}" class="btn btn-phoenix-secondary">Cancelar</a>
        <button type="submit" class="btn btn-primary">
            {{ $isEdit ? 'Guardar alteracoes' : 'Criar unidade' }}
        </button>
    </div>
</div>
