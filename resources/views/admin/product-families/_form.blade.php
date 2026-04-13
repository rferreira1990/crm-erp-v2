@php
    $isEdit = isset($family);
@endphp

<div class="row g-3">
    <div class="col-12">
        <label for="name" class="form-label">Nome</label>
        <input
            type="text"
            id="name"
            name="name"
            value="{{ old('name', $family->name ?? '') }}"
            class="form-control @error('name') is-invalid @enderror"
            placeholder="Ex: Material eletrico"
            maxlength="120"
            required
        >
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12 col-md-4">
        <label for="family_code" class="form-label">Codigo da familia (2 digitos)</label>
        <input
            type="text"
            id="family_code"
            name="family_code"
            value="{{ old('family_code', $family->family_code ?? '') }}"
            class="form-control @error('family_code') is-invalid @enderror"
            maxlength="2"
            pattern="\d{2}"
            placeholder="Ex: 01"
        >
        @error('family_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12">
        <label for="parent_id" class="form-label">Familia pai</label>
        <select
            id="parent_id"
            name="parent_id"
            class="form-select @error('parent_id') is-invalid @enderror"
        >
            <option value="">Sem familia pai (top-level)</option>
            @foreach (($parentOptions ?? []) as $parentOption)
                <option
                    value="{{ $parentOption['id'] }}"
                    @selected((string) old('parent_id', $family->parent_id ?? '') === (string) $parentOption['id'])
                >
                    {{ $parentOption['label'] }}
                </option>
            @endforeach
        </select>
        @error('parent_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12 d-flex gap-2 justify-content-end mt-3">
        <a href="{{ route('admin.product-families.index') }}" class="btn btn-phoenix-secondary">Cancelar</a>
        <button type="submit" class="btn btn-primary">
            {{ $isEdit ? 'Guardar alteracoes' : 'Criar familia' }}
        </button>
    </div>
</div>
