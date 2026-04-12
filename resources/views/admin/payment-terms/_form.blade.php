@php
    $isEdit = isset($paymentTerm);
@endphp

<div class="row g-3">
    <div class="col-12 col-md-6">
        <label for="name" class="form-label">Nome</label>
        <input
            type="text"
            id="name"
            name="name"
            value="{{ old('name', $paymentTerm->name ?? '') }}"
            class="form-control @error('name') is-invalid @enderror"
            maxlength="120"
            required
        >
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12 col-md-3">
        <label for="calculation_type" class="form-label">Tipo de calculo</label>
        <select
            id="calculation_type"
            name="calculation_type"
            class="form-select @error('calculation_type') is-invalid @enderror"
            required
        >
            @foreach (($calculationTypeOptions ?? []) as $value => $label)
                <option
                    value="{{ $value }}"
                    @selected(old('calculation_type', $paymentTerm->calculation_type ?? \App\Models\PaymentTerm::CALCULATION_FIXED_DAYS) === $value)
                >
                    {{ $label }}
                </option>
            @endforeach
        </select>
        @error('calculation_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12 col-md-3">
        <label for="days" class="form-label">Dias</label>
        <input
            type="number"
            id="days"
            name="days"
            value="{{ old('days', $paymentTerm->days ?? 0) }}"
            class="form-control @error('days') is-invalid @enderror"
            min="0"
            step="1"
            required
        >
        @error('days')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12 d-flex gap-2 justify-content-end mt-3">
        <a href="{{ route('admin.payment-terms.index') }}" class="btn btn-phoenix-secondary">Cancelar</a>
        <button type="submit" class="btn btn-primary">
            {{ $isEdit ? 'Guardar alterações' : 'Criar condição de pagamento' }}
        </button>
    </div>
</div>
