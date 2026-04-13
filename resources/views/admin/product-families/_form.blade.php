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
        <label class="form-label">Codigo da familia (2 digitos)</label>
        @if ($isEdit)
            <input
                type="text"
                value="{{ $family->family_code ?? '-' }}"
                class="form-control"
                readonly
            >
            <small class="text-body-tertiary">Codigo gerado automaticamente na criacao.</small>
        @else
            <input
                type="text"
                value="Gerado automaticamente"
                class="form-control"
                readonly
            >
            <small class="text-body-tertiary">Sera atribuido automaticamente ao gravar.</small>
        @endif
        @error('family_code')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
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
