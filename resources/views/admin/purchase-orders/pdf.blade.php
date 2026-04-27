<!doctype html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <title>{{ $purchaseOrder->number }}</title>
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
        .lines { width: 100%; border-collapse: collapse; margin-top: 8px; }
        .lines th, .lines td { border: 1px solid #cbd5e1; padding: 6px; vertical-align: top; }
        .lines th { background: #e2e8f0; font-size: 10px; text-transform: uppercase; }
        .text-right { text-align: right; }
        .totals { width: 42%; margin-left: auto; margin-top: 10px; border-collapse: collapse; }
        .totals td { border: 1px solid #cbd5e1; padding: 6px 8px; }
        .totals .final td { background: #e2e8f0; font-weight: bold; font-size: 12px; }
        .notes { margin-top: 14px; }
        .notes h4 { margin: 0 0 4px 0; font-size: 11px; }
        .notes p { margin: 0 0 8px 0; line-height: 1.4; }
    </style>
</head>
<body>
    @php
        $companyAddress = trim((string) ($purchaseOrder->company?->address ?? ''));
        $companyLocation = trim(implode(' ', array_filter([
            $purchaseOrder->company?->postal_code,
            $purchaseOrder->company?->locality,
            $purchaseOrder->company?->city,
        ], fn ($value) => trim((string) $value) !== '')));
        $rfqReference = trim((string) ($purchaseOrder->rfq?->number ?? ''));
        $supplierDocumentNumber = trim((string) ($purchaseOrder->items
            ->map(fn ($item) => $item->sourceSupplierQuoteItem?->supplierQuote?->supplier_document_number)
            ->first(fn ($value) => trim((string) $value) !== '') ?? ''));
    @endphp

    <div class="header">
        <table class="header-table">
            <tr>
                <td class="header-logo-cell">
                    @if ($companyLogoDataUri)
                        <img src="{{ $companyLogoDataUri }}" alt="{{ $purchaseOrder->company?->name ?? 'Empresa' }}" class="header-logo">
                    @else
                        <div class="strong">{{ $purchaseOrder->company?->name ?? '-' }}</div>
                    @endif
                </td>
                <td class="header-doc-cell">
                    <p class="doc-title">Encomenda a Fornecedor</p>
                    <div class="doc-meta">
                        <span class="strong">{{ $purchaseOrder->number }}</span>
                        | Emissao: {{ optional($purchaseOrder->issue_date)->format('Y-m-d') }}
                        | Estado: {{ $purchaseOrder->statusLabel() }}
                        | Origem: {{ $purchaseOrder->originLabel() }}
                    </div>
                    <div class="doc-meta" style="margin-top:4px;">
                        Ref. RFQ: {{ $rfqReference !== '' ? $rfqReference : '-' }}
                        | Doc. fornecedor: {{ $supplierDocumentNumber !== '' ? $supplierDocumentNumber : '-' }}
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <div class="row">
        <div class="col-50">
            <div class="card">
                <p class="card-title">Empresa</p>
                <div class="strong">{{ $purchaseOrder->company?->name ?? '-' }}</div>
                <div>NIF: {{ $purchaseOrder->company?->nif ?? '-' }}</div>
                <div>{{ $purchaseOrder->company?->email ?? '-' }} | {{ $purchaseOrder->company?->phone ?? $purchaseOrder->company?->mobile ?? '-' }}</div>
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
                <p class="card-title">Fornecedor</p>
                <div class="strong">{{ $purchaseOrder->supplier_name_snapshot }}</div>
                <div>{{ $purchaseOrder->supplier_email_snapshot ?: '-' }} | {{ $purchaseOrder->supplier_phone_snapshot ?: '-' }}</div>
                <div>{{ $purchaseOrder->supplier_address_snapshot ?: '-' }}</div>
            </div>
        </div>
    </div>

    <table class="lines">
        <thead>
            <tr>
                <th style="width:5%;">#</th>
                <th style="width:33%;">Descricao</th>
                <th style="width:8%;" class="text-right">Qtd</th>
                <th style="width:8%;">Unid.</th>
                <th style="width:12%;" class="text-right">P. Unit.</th>
                <th style="width:9%;" class="text-right">Desc. %</th>
                <th style="width:8%;" class="text-right">IVA %</th>
                <th style="width:17%;" class="text-right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($purchaseOrder->items as $item)
                <tr>
                    <td>{{ $item->line_order }}</td>
                    <td>{{ $item->description }}</td>
                    <td class="text-right">{{ number_format((float) $item->quantity, 3, ',', '.') }}</td>
                    <td>{{ $item->unit_name ?: '-' }}</td>
                    <td class="text-right">{{ number_format((float) $item->unit_price, 4, ',', '.') }}</td>
                    <td class="text-right">{{ number_format((float) $item->discount_percent, 2, ',', '.') }}</td>
                    <td class="text-right">{{ number_format((float) $item->vat_percent, 2, ',', '.') }}</td>
                    <td class="text-right">{{ number_format((float) $item->line_total, 2, ',', '.') }} {{ $purchaseOrder->currency }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td>Subtotal</td>
            <td class="text-right">{{ number_format((float) $purchaseOrder->subtotal, 2, ',', '.') }} {{ $purchaseOrder->currency }}</td>
        </tr>
        <tr>
            <td>Desconto</td>
            <td class="text-right">{{ number_format((float) $purchaseOrder->discount_total, 2, ',', '.') }} {{ $purchaseOrder->currency }}</td>
        </tr>
        <tr>
            <td>Portes</td>
            <td class="text-right">{{ number_format((float) $purchaseOrder->shipping_total, 2, ',', '.') }} {{ $purchaseOrder->currency }}</td>
        </tr>
        <tr>
            <td>IVA</td>
            <td class="text-right">{{ number_format((float) $purchaseOrder->tax_total, 2, ',', '.') }} {{ $purchaseOrder->currency }}</td>
        </tr>
        <tr class="final">
            <td>Total</td>
            <td class="text-right">{{ number_format((float) $purchaseOrder->grand_total, 2, ',', '.') }} {{ $purchaseOrder->currency }}</td>
        </tr>
    </table>

    @if ($purchaseOrder->supplier_notes || $purchaseOrder->internal_notes)
        <div class="notes">
            @if ($purchaseOrder->supplier_notes)
                <h4>Notas para fornecedor</h4>
                <p>{!! nl2br(e($purchaseOrder->supplier_notes)) !!}</p>
            @endif
            @if ($purchaseOrder->internal_notes)
                <h4>Notas internas</h4>
                <p>{!! nl2br(e($purchaseOrder->internal_notes)) !!}</p>
            @endif
        </div>
    @endif
</body>
</html>
