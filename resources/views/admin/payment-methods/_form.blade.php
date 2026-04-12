@php
    $isEdit = isset($paymentMethod);
@endphp

<div class="row g-3">
    <div class="col-12">
        <label for="name" class="form-label">Nome</label>
        <input
            type="text"
            id="name"
            name="name"
            value="{{ old('name', $paymentMethod->name ?? '') }}"
            class="form-control @error('name') is-invalid @enderror"
            maxlength="120"
            required
        >
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12 d-flex gap-2 justify-content-end mt-3">
        <a href="{{ route('admin.payment-methods.index') }}" class="btn btn-phoenix-secondary">Cancelar</a>
        <button type="submit" class="btn btn-primary">
            {{ $isEdit ? 'Guardar alteracoes' : 'Criar modo de pagamento' }}
        </button>
    </div>
</div>
