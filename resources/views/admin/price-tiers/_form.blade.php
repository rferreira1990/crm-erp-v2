@php
    $isEdit = isset($priceTier);
@endphp

<div class="row g-3">
    <div class="col-12 col-md-6">
        <label for="name" class="form-label">Nome</label>
        <input
            type="text"
            id="name"
            name="name"
            value="{{ old('name', $priceTier->name ?? '') }}"
            class="form-control @error('name') is-invalid @enderror"
            maxlength="120"
            required
        >
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12 col-md-3">
        <label for="percentage_adjustment" class="form-label">Ajuste %</label>
        <input
            type="number"
            id="percentage_adjustment"
            name="percentage_adjustment"
            value="{{ old('percentage_adjustment', $priceTier->percentage_adjustment ?? 0) }}"
            class="form-control @error('percentage_adjustment') is-invalid @enderror"
            min="-100"
            max="1000"
            step="0.01"
            required
        >
        @error('percentage_adjustment')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12 col-md-3">
        <div class="form-check mt-4">
            <input type="hidden" name="is_active" value="0">
            <input
                class="form-check-input"
                type="checkbox"
                id="is_active"
                name="is_active"
                value="1"
                @checked(old('is_active', $priceTier->is_active ?? true))
            >
            <label class="form-check-label" for="is_active">Ativo</label>
        </div>
    </div>

    <div class="col-12 d-flex gap-2 justify-content-end mt-3">
        <a href="{{ route('admin.price-tiers.index') }}" class="btn btn-phoenix-secondary">Cancelar</a>
        <button type="submit" class="btn btn-primary">
            {{ $isEdit ? 'Guardar alteracoes' : 'Criar escalao de preco' }}
        </button>
    </div>
</div>

