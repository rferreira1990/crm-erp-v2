<!doctype html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <title>{{ $document->number }}</title>
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
        .muted { color: #64748b; font-size: 10px; margin-top: 2px; }
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
        $companyAddress = trim((string) ($document->company?->address ?? ''));
        $companyLocation = trim(implode(' ', array_filter([
            $document->company?->postal_code,
            $document->company?->locality,
            $document->company?->city,
        ], fn ($value) => trim((string) $value) !== '')));
    @endphp

    <div class="header">
        <table class="header-table">
            <tr>
                <td class="header-logo-cell">
                    @if ($companyLogoDataUri)
                        <img src="{{ $companyLogoDataUri }}" alt="{{ $document->company?->name ?? 'Empresa' }}" class="header-logo">
                    @else
                        <div class="strong">{{ $document->company?->name ?? '-' }}</div>
                    @endif
                </td>
                <td class="header-doc-cell">
                    <p class="doc-title">Documento de Venda</p>
                    <div class="doc-meta">
                        <span class="strong">{{ $document->number }}</span>
                        | Emissao: {{ optional($document->issue_date)->format('Y-m-d') }}
                        | Estado: {{ $document->statusLabel() }}
                        | Origem: {{ $document->sourceLabel() }}
                    </div>
                    <div class="doc-meta" style="margin-top:4px;">
                        Orcamento: {{ $document->quote?->number ?? '-' }}
                        | Obra: {{ $document->constructionSite?->code ?? '-' }}
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <div class="row">
        <div class="col-50">
            <div class="card">
                <p class="card-title">Empresa</p>
                <div class="strong">{{ $document->company?->name ?? '-' }}</div>
                <div>NIF: {{ $document->company?->nif ?? '-' }}</div>
                <div>{{ $document->company?->email ?? '-' }} | {{ $document->company?->phone ?? $document->company?->mobile ?? '-' }}</div>
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
                <div class="strong">{{ $document->customer_name_snapshot ?: ($document->customer?->name ?? '-') }}</div>
                <div>{{ $document->customer_email_snapshot ?: '-' }} | {{ $document->customer_phone_snapshot ?: '-' }}</div>
                <div>{{ $document->customer_address_snapshot ?: '-' }}</div>
                <div>Contacto: {{ $document->customer_contact_name_snapshot ?: '-' }}</div>
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
                <th style="width:8%;" class="text-right">Taxa %</th>
                <th style="width:17%;" class="text-right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($document->items as $item)
                <tr>
                    <td>{{ $item->line_order }}</td>
                    <td>
                        {{ $item->description }}
                        @if (filled($item->article?->print_notes))
                            <div class="muted">Nota: {{ $item->article->print_notes }}</div>
                        @endif
                    </td>
                    <td class="text-right">{{ number_format((float) $item->quantity, 3, ',', '.') }}</td>
                    <td>{{ $item->unit_name_snapshot ?: '-' }}</td>
                    <td class="text-right">{{ number_format((float) $item->unit_price, 4, ',', '.') }}</td>
                    <td class="text-right">{{ number_format((float) $item->discount_percent, 2, ',', '.') }}</td>
                    <td class="text-right">{{ number_format((float) $item->tax_rate, 2, ',', '.') }}</td>
                    <td class="text-right">{{ number_format((float) $item->line_total, 2, ',', '.') }} {{ $document->currency }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td>Subtotal</td>
            <td class="text-right">{{ number_format((float) $document->subtotal, 2, ',', '.') }} {{ $document->currency }}</td>
        </tr>
        <tr>
            <td>Desconto</td>
            <td class="text-right">{{ number_format((float) $document->discount_total, 2, ',', '.') }} {{ $document->currency }}</td>
        </tr>
        <tr>
            <td>Impostos</td>
            <td class="text-right">{{ number_format((float) $document->tax_total, 2, ',', '.') }} {{ $document->currency }}</td>
        </tr>
        <tr class="final">
            <td>Total</td>
            <td class="text-right">{{ number_format((float) $document->grand_total, 2, ',', '.') }} {{ $document->currency }}</td>
        </tr>
    </table>

    @if ($document->notes)
        <div class="notes">
            <h4>Notas</h4>
            <p>{!! nl2br(e($document->notes)) !!}</p>
        </div>
    @endif
</body>
</html>
