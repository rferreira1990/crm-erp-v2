<!doctype html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <title>{{ $receipt->number }}</title>
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 11px; color: #0f172a; margin: 26px; }
        .header { border-bottom: 2px solid #1e293b; padding-bottom: 10px; margin-bottom: 16px; }
        .header-table { width: 100%; border-collapse: collapse; }
        .header-table td { vertical-align: top; }
        .header-logo-cell { width: 140px; }
        .header-logo { max-width: 120px; max-height: 54px; }
        .header-doc-cell { text-align: right; }
        .doc-title { margin: 0 0 4px 0; font-size: 22px; font-weight: bold; }
        .doc-meta { font-size: 10px; color: #475569; }
        .row { width: 100%; margin-bottom: 12px; }
        .col-50 { width: 49%; display: inline-block; vertical-align: top; }
        .card { border: 1px solid #cbd5e1; border-radius: 4px; padding: 10px; min-height: 94px; }
        .card-title { margin: 0 0 6px 0; font-size: 10px; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b; }
        .strong { font-weight: bold; }
        .totals { width: 45%; margin-left: auto; margin-top: 10px; border-collapse: collapse; }
        .totals td { border: 1px solid #cbd5e1; padding: 6px 8px; }
        .totals .final td { background: #e2e8f0; font-weight: bold; font-size: 12px; }
        .notes { margin-top: 14px; }
        .notes h4 { margin: 0 0 4px 0; font-size: 11px; }
        .notes p { margin: 0; line-height: 1.4; }
    </style>
</head>
<body>
    @php
        $companyAddress = trim((string) ($receipt->company?->address ?? ''));
        $companyLocation = trim(implode(' ', array_filter([
            $receipt->company?->postal_code,
            $receipt->company?->locality,
            $receipt->company?->city,
        ], fn ($value) => trim((string) $value) !== '')));

        $customerAddress = trim((string) ($receipt->customer?->address ?? ''));
        $customerLocation = trim(implode(' ', array_filter([
            $receipt->customer?->postal_code,
            $receipt->customer?->locality,
            $receipt->customer?->city,
        ], fn ($value) => trim((string) $value) !== '')));
    @endphp

    <div class="header">
        <table class="header-table">
            <tr>
                <td class="header-logo-cell">
                    @if ($companyLogoDataUri)
                        <img src="{{ $companyLogoDataUri }}" alt="{{ $receipt->company?->name ?? 'Empresa' }}" class="header-logo">
                    @else
                        <div class="strong">{{ $receipt->company?->name ?? '-' }}</div>
                    @endif
                </td>
                <td class="header-doc-cell">
                    <p class="doc-title">Recibo</p>
                    <div class="doc-meta">
                        <span class="strong">{{ $receipt->number }}</span>
                        | Data: {{ optional($receipt->receipt_date)->format('Y-m-d') }}
                        | Estado: {{ $receipt->statusLabel() }}
                    </div>
                    <div class="doc-meta" style="margin-top:4px;">
                        Documento de Venda: {{ $receipt->salesDocument?->number ?? '-' }}
                        | Emitido em: {{ optional($receipt->issued_at)->format('Y-m-d H:i') ?? '-' }}
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <div class="row">
        <div class="col-50">
            <div class="card">
                <p class="card-title">Empresa</p>
                <div class="strong">{{ $receipt->company?->name ?? '-' }}</div>
                <div>NIF: {{ $receipt->company?->nif ?? '-' }}</div>
                <div>{{ $receipt->company?->email ?? '-' }} | {{ $receipt->company?->phone ?? $receipt->company?->mobile ?? '-' }}</div>
                @if ($companyAddress !== '')
                    <div>{{ $companyAddress }}</div>
                @endif
                @if ($companyLocation !== '')
                    <div>{{ $companyLocation }}</div>
                @endif
            </div>
        </div>
        <div class="col-50" style="float:right;">
            <div class="card">
                <p class="card-title">Cliente</p>
                <div class="strong">{{ $receipt->customer?->name ?? '-' }}</div>
                <div>NIF: {{ $receipt->customer?->nif ?? '-' }}</div>
                <div>{{ $receipt->customer?->email ?? '-' }} | {{ $receipt->customer?->phone ?? $receipt->customer?->mobile ?? '-' }}</div>
                @if ($customerAddress !== '')
                    <div>{{ $customerAddress }}</div>
                @endif
                @if ($customerLocation !== '')
                    <div>{{ $customerLocation }}</div>
                @endif
            </div>
        </div>
    </div>

    <table class="totals">
        <tr>
            <td>Modo de pagamento</td>
            <td>{{ $receipt->paymentMethod?->name ?? '-' }}</td>
        </tr>
        <tr>
            <td>Documento de Venda</td>
            <td>{{ $receipt->salesDocument?->number ?? '-' }}</td>
        </tr>
        <tr>
            <td>Total documento</td>
            <td>{{ number_format((float) ($receipt->salesDocument?->grand_total ?? 0), 2, ',', '.') }} {{ $receipt->salesDocument?->currency ?? 'EUR' }}</td>
        </tr>
        <tr class="final">
            <td>Valor recebido</td>
            <td>{{ number_format((float) $receipt->amount, 2, ',', '.') }} {{ $receipt->salesDocument?->currency ?? 'EUR' }}</td>
        </tr>
    </table>

    <div class="notes">
        <h4>Notas</h4>
        <p>{{ $receipt->notes ?: '-' }}</p>
    </div>
</body>
</html>
