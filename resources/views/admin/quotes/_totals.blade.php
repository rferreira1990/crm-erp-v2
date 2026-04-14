@php
    $subtotalValue = $subtotal ?? 0;
    $discountTotalValue = $discountTotal ?? 0;
    $taxTotalValue = $taxTotal ?? 0;
    $grandTotalValue = $grandTotal ?? 0;
    $currencyCode = $currency ?? 'EUR';
@endphp

<div class="card mb-4">
    <div class="card-header bg-body-tertiary">
        <h5 class="mb-0">Totais</h5>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-12 col-md-3">
                <label class="form-label">Subtotal</label>
                <input type="text" class="form-control" id="quote-preview-subtotal" value="{{ number_format((float) $subtotalValue, 2, ',', '.') }} {{ $currencyCode }}" readonly>
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label">Total desconto</label>
                <input type="text" class="form-control" id="quote-preview-discount-total" value="{{ number_format((float) $discountTotalValue, 2, ',', '.') }} {{ $currencyCode }}" readonly>
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label">Total IVA</label>
                <input type="text" class="form-control" id="quote-preview-tax-total" value="{{ number_format((float) $taxTotalValue, 2, ',', '.') }} {{ $currencyCode }}" readonly>
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label">Total final</label>
                <input type="text" class="form-control fw-semibold" id="quote-preview-grand-total" value="{{ number_format((float) $grandTotalValue, 2, ',', '.') }} {{ $currencyCode }}" readonly>
            </div>
        </div>
    </div>
</div>
