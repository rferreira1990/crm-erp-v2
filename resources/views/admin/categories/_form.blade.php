@php
    $isEdit = isset($category);
@endphp

<div class="row g-3">
    <div class="col-12">
        <label for="name" class="form-label">Nome</label>
        <input
            type="text"
            id="name"
            name="name"
            value="{{ old('name', $category->name ?? '') }}"
            class="form-control @error('name') is-invalid @enderror"
            placeholder="Ex: Produto"
            maxlength="120"
            required
        >
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12">
        <label for="parent_id" class="form-label">Categoria pai</label>
        <select
            id="parent_id"
            name="parent_id"
            class="form-select @error('parent_id') is-invalid @enderror"
        >
            <option value="">Sem parent (top-level)</option>
            @foreach (($parentOptions ?? []) as $parentOption)
                <option
                    value="{{ $parentOption['id'] }}"
                    @selected((string) old('parent_id', $category->parent_id ?? '') === (string) $parentOption['id'])
                >
                    {{ $parentOption['label'] }}
                </option>
            @endforeach
        </select>
        @error('parent_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12 d-flex gap-2 justify-content-end mt-3">
        <a href="{{ route('admin.categories.index') }}" class="btn btn-phoenix-secondary">Cancelar</a>
        <button type="submit" class="btn btn-primary">
            {{ $isEdit ? 'Guardar alteracoes' : 'Criar categoria' }}
        </button>
    </div>
</div>
