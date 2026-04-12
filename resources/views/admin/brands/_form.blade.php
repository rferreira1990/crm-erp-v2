@php
    $isEdit = isset($brand);
@endphp

<div class="row g-3">
    <div class="col-12 col-md-6">
        <label for="name" class="form-label">Nome</label>
        <input
            type="text"
            id="name"
            name="name"
            value="{{ old('name', $brand->name ?? '') }}"
            class="form-control @error('name') is-invalid @enderror"
            maxlength="120"
            required
        >
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12 col-md-6">
        <label for="website_url" class="form-label">Website</label>
        <input
            type="url"
            id="website_url"
            name="website_url"
            value="{{ old('website_url', $brand->website_url ?? '') }}"
            class="form-control @error('website_url') is-invalid @enderror"
            placeholder="https://exemplo.com"
            maxlength="255"
        >
        @error('website_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12">
        <label for="description" class="form-label">Descricao</label>
        <textarea
            id="description"
            name="description"
            rows="4"
            class="form-control @error('description') is-invalid @enderror"
            maxlength="5000"
        >{{ old('description', $brand->description ?? '') }}</textarea>
        @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12 col-md-6">
        <label for="logo" class="form-label">Logotipo</label>
        <input
            type="file"
            id="logo"
            name="logo"
            class="form-control @error('logo') is-invalid @enderror"
            accept=".jpg,.jpeg,.png,.webp,.svg"
        >
        @error('logo')<div class="invalid-feedback">{{ $message }}</div>@enderror

        @if ($isEdit && $brand->logo_path)
            <div class="mt-3 d-flex align-items-center gap-3">
                <img src="{{ Storage::disk('public')->url($brand->logo_path) }}" alt="Logotipo" style="max-height: 56px;">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="remove_logo" name="remove_logo" value="1">
                    <label class="form-check-label" for="remove_logo">Remover logotipo atual</label>
                </div>
            </div>
        @endif
    </div>

    <div class="col-12 col-md-6">
        <label for="files" class="form-label">Ficheiros / Catalogos</label>
        <input
            type="file"
            id="files"
            name="files[]"
            class="form-control @error('files') is-invalid @enderror @error('files.*') is-invalid @enderror"
            multiple
            accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx,.csv,.txt"
        >
        @error('files')<div class="invalid-feedback">{{ $message }}</div>@enderror
        @error('files.*')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12 d-flex gap-2 justify-content-end mt-3">
        <a href="{{ route('admin.brands.index') }}" class="btn btn-phoenix-secondary">Cancelar</a>
        <button type="submit" class="btn btn-primary">
            {{ $isEdit ? 'Guardar alteracoes' : 'Criar marca' }}
        </button>
    </div>
</div>
