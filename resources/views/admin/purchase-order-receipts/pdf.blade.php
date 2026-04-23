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
        .lines { width: 100%; border-collapse: collapse; margin-top: 8px; }
        .lines th, .lines td { border: 1px solid #cbd5e1; padding: 6px; vertical-align: top; }
        .lines th { background: #e2e8f0; font-size: 10px; text-transform: uppercase; }
        .text-right { text-align: right; }
        .notes { margin-top: 14px; }
        .notes h4 { margin: 0 0 4px 0; font-size: 11px; }
        .notes p { margin: 0 0 8px 0; line-height: 1.4; }
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
        $poNumber = trim((string) ($receipt->purchaseOrder?->number ?? ''));
        $rfqNumber = trim((string) ($receipt->purchaseOrder?->rfq?->number ?? ''));
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
                    <p class="doc-title">Rececao de Material</p>
                    <div class="doc-meta">
                        <span class="strong">{{ $receipt->number }}</span>
                        | Data: {{ optional($receipt->receipt_date)->format('Y-m-d') ?? '-' }}
                        | Estado: {{ $receipt->statusLabel() }}
                    </div>
                    <div class="doc-meta" style="margin-top:4px;">
                        Ref. encomenda: {{ $poNumber !== '' ? $poNumber : '-' }}
                        | Ref. RFQ: {{ $rfqNumber !== '' ? $rfqNumber : '-' }}
                        | Doc. fornecedor: {{ $receipt->supplier_document_number ?: '-' }}
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
                <p class="card-title">Fornecedor</p>
                <div class="strong">{{ $receipt->purchaseOrder?->supplier_name_snapshot ?? '-' }}</div>
                <div>{{ $receipt->purchaseOrder?->supplier_email_snapshot ?: '-' }} | {{ $receipt->purchaseOrder?->supplier_phone_snapshot ?: '-' }}</div>
                <div>{{ $receipt->purchaseOrder?->supplier_address_snapshot ?: '-' }}</div>
                <div style="margin-top: 6px;">Recebido por: {{ $receipt->receiver?->name ?? '-' }}</div>
            </div>
        </div>
    </div>

    <table class="lines">
        <thead>
            <tr>
                <th style="width:5%;">#</th>
                <th style="width:33%;">Descricao</th>
                <th style="width:8%;" class="text-right">Qtd enc.</th>
                <th style="width:8%;" class="text-right">Qtd ant.</th>
                <th style="width:8%;" class="text-right">Qtd rec.</th>
                <th style="width:8%;" class="text-right">Qtd falta</th>
                <th style="width:8%;">Unid.</th>
                <th style="width:22%;">Notas</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($receipt->items as $item)
                @php
                    $remaining = max(0, (float) $item->ordered_quantity - ((float) $item->previously_received_quantity + (float) $item->received_quantity));
                @endphp
                <tr>
                    <td>{{ $item->line_order }}</td>
                    <td>{{ $item->description }}</td>
                    <td class="text-right">{{ number_format((float) $item->ordered_quantity, 3, ',', '.') }}</td>
                    <td class="text-right">{{ number_format((float) $item->previously_received_quantity, 3, ',', '.') }}</td>
                    <td class="text-right">{{ number_format((float) $item->received_quantity, 3, ',', '.') }}</td>
                    <td class="text-right">{{ number_format($remaining, 3, ',', '.') }}</td>
                    <td>{{ $item->unit_name ?: '-' }}</td>
                    <td>{{ $item->notes ?: '-' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if ($receipt->notes || $receipt->internal_notes)
        <div class="notes">
            @if ($receipt->notes)
                <h4>Notas</h4>
                <p>{!! nl2br(e($receipt->notes)) !!}</p>
            @endif
            @if ($receipt->internal_notes)
                <h4>Notas internas</h4>
                <p>{!! nl2br(e($receipt->internal_notes)) !!}</p>
            @endif
        </div>
    @endif
</body>
</html>
