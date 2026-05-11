<!doctype html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <title>{{ $payment->number }}</title>
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 11px; color: #0f172a; margin: 24px; }
        .band { background: #0f172a; color: #fff; padding: 14px 16px; border-radius: 8px; margin-bottom: 14px; }
        .title { margin: 0; font-size: 19px; font-weight: bold; }
        .meta { margin-top: 6px; font-size: 10px; opacity: 0.9; }
        .grid { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        .grid td { vertical-align: top; width: 50%; padding-right: 8px; }
        .box { border: 1px solid #cbd5e1; border-radius: 8px; padding: 10px; min-height: 88px; }
        .box-title { margin: 0 0 5px 0; font-size: 10px; text-transform: uppercase; color: #64748b; }
        .strong { font-weight: bold; }
        .summary { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .summary th, .summary td { border: 1px solid #cbd5e1; padding: 6px 8px; text-align: left; }
        .summary th { background: #f1f5f9; font-size: 10px; text-transform: uppercase; color: #475569; }
        .final { background: #dbeafe; font-weight: bold; }
        .notes { margin-top: 14px; border: 1px dashed #94a3b8; border-radius: 8px; padding: 10px; }
    </style>
</head>
<body>
    @php
        $companyAddress = trim((string) ($payment->company?->address ?? ''));
        $companyLocation = trim(implode(' ', array_filter([
            $payment->company?->postal_code,
            $payment->company?->locality,
            $payment->company?->city,
        ], fn ($value) => trim((string) $value) !== '')));
        $supplierAddress = trim((string) ($payment->supplier?->address ?? ''));
        $supplierLocation = trim(implode(' ', array_filter([
            $payment->supplier?->postal_code,
            $payment->supplier?->locality,
            $payment->supplier?->city,
        ], fn ($value) => trim((string) $value) !== '')));
    @endphp

    <div class="band">
        <p class="title">Documento de Pagamento</p>
        <div class="meta">
            {{ $payment->number }} | {{ optional($payment->payment_date)->format('Y-m-d') }} | {{ $payment->statusLabel() }}
        </div>
        <div class="meta">
            Documento de Compra: {{ $payment->purchaseDocument?->number ?? '-' }}
        </div>
    </div>

    <table class="grid">
        <tr>
            <td>
                <div class="box">
                    <p class="box-title">Empresa</p>
                    @if ($companyLogoDataUri)
                        <div style="margin-bottom:8px;"><img src="{{ $companyLogoDataUri }}" alt="Logo" style="max-width:120px;max-height:48px;"></div>
                    @endif
                    <div class="strong">{{ $payment->company?->name ?? '-' }}</div>
                    <div>NIF: {{ $payment->company?->nif ?? '-' }}</div>
                    <div>{{ $payment->company?->email ?? '-' }} | {{ $payment->company?->phone ?? $payment->company?->mobile ?? '-' }}</div>
                    @if ($companyAddress !== '')<div>{{ $companyAddress }}</div>@endif
                    @if ($companyLocation !== '')<div>{{ $companyLocation }}</div>@endif
                </div>
            </td>
            <td>
                <div class="box">
                    <p class="box-title">Fornecedor</p>
                    <div class="strong">{{ $payment->supplier?->name ?? '-' }}</div>
                    <div>NIF: {{ $payment->supplier?->nif ?? '-' }}</div>
                    <div>{{ $payment->supplier?->email ?? '-' }} | {{ $payment->supplier?->phone ?? $payment->supplier?->mobile ?? '-' }}</div>
                    @if ($supplierAddress !== '')<div>{{ $supplierAddress }}</div>@endif
                    @if ($supplierLocation !== '')<div>{{ $supplierLocation }}</div>@endif
                </div>
            </td>
        </tr>
    </table>

    <table class="summary">
        <thead>
            <tr>
                <th>Campo</th>
                <th>Valor</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Modo de pagamento</td>
                <td>{{ $payment->paymentMethod?->name ?? '-' }}</td>
            </tr>
            <tr>
                <td>Documento de Compra</td>
                <td>{{ $payment->purchaseDocument?->number ?? '-' }}</td>
            </tr>
            <tr>
                <td>Total documento</td>
                <td>{{ number_format((float) ($payment->purchaseDocument?->grand_total ?? 0), 2, ',', '.') }} {{ $payment->purchaseDocument?->currency ?? 'EUR' }}</td>
            </tr>
            <tr class="final">
                <td>Valor pago</td>
                <td>{{ number_format((float) $payment->amount, 2, ',', '.') }} {{ $payment->purchaseDocument?->currency ?? 'EUR' }}</td>
            </tr>
        </tbody>
    </table>

    <div class="notes">
        <strong>Notas:</strong> {{ $payment->notes ?: '-' }}
    </div>
</body>
</html>
