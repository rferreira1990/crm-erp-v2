<!doctype html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <title>{{ $quote->number }}</title>
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 12px; color: #111827; }
        .header { margin-bottom: 18px; }
        .title { font-size: 20px; font-weight: bold; margin: 0 0 4px 0; }
        .muted { color: #4b5563; }
        .row { width: 100%; margin-bottom: 14px; }
        .col { width: 48%; display: inline-block; vertical-align: top; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #d1d5db; padding: 6px; text-align: left; }
        th { background: #f3f4f6; }
        .right { text-align: right; }
        .totals { margin-top: 12px; width: 42%; margin-left: auto; }
        .totals td { border: 1px solid #d1d5db; padding: 6px; }
        .totals .final { font-weight: bold; background: #f3f4f6; }
        .spacer { height: 10px; }
    </style>
</head>
<body>
    <div class="header">
        <p class="title">Orcamento {{ $quote->number }}</p>
        <p class="muted">Data: {{ optional($quote->issue_date)->format('Y-m-d') }} | Valido ate: {{ optional($quote->valid_until)->format('Y-m-d') ?? '-' }}</p>
    </div>

    <div class="row">
        <div class="col">
            <strong>Empresa</strong><br>
            {{ $quote->company?->name ?? '-' }}<br>
            NIF: {{ $quote->company?->nif ?? '-' }}<br>
            {{ $quote->company?->email ?? '-' }}<br>
            {{ $quote->company?->phone ?? '-' }}
        </div>
        <div class="col" style="float:right;">
            <strong>Cliente</strong><br>
            {{ $quote->customer?->name ?? '-' }}<br>
            NIF: {{ $quote->customer?->nif ?? '-' }}<br>
            {{ $quote->customer?->email ?? '-' }}<br>
            {{ $quote->customer?->phone ?? $quote->customer?->mobile ?? '-' }}
        </div>
    </div>

    <div class="spacer"></div>

    @if ($quote->header_notes)
        <p><strong>Notas de cabecalho:</strong> {{ $quote->header_notes }}</p>
    @endif

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Descricao</th>
                <th class="right">Qtd</th>
                <th class="right">P. unit.</th>
                <th class="right">Desc.</th>
                <th class="right">IVA</th>
                <th class="right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($quote->items as $item)
                <tr>
                    <td>{{ $item->sort_order }}</td>
                    <td>{{ $item->description }}</td>
                    <td class="right">{{ number_format((float) $item->quantity, 3, ',', '.') }}</td>
                    <td class="right">{{ number_format((float) $item->unit_price, 4, ',', '.') }}</td>
                    <td class="right">{{ number_format((float) ($item->discount_percent ?? 0), 2, ',', '.') }}%</td>
                    <td class="right">{{ $item->vatRate ? number_format((float) $item->vatRate->rate, 2, ',', '.').'%' : '-' }}</td>
                    <td class="right">{{ number_format((float) $item->total, 2, ',', '.') }} {{ $quote->currency }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tbody>
            <tr>
                <td>Subtotal</td>
                <td class="right">{{ number_format((float) $quote->subtotal, 2, ',', '.') }} {{ $quote->currency }}</td>
            </tr>
            <tr>
                <td>Total desconto</td>
                <td class="right">{{ number_format((float) $quote->discount_total, 2, ',', '.') }} {{ $quote->currency }}</td>
            </tr>
            <tr>
                <td>Total IVA</td>
                <td class="right">{{ number_format((float) $quote->tax_total, 2, ',', '.') }} {{ $quote->currency }}</td>
            </tr>
            <tr class="final">
                <td>Total final</td>
                <td class="right">{{ number_format((float) $quote->grand_total, 2, ',', '.') }} {{ $quote->currency }}</td>
            </tr>
        </tbody>
    </table>

    <div class="spacer"></div>

    @if ($quote->footer_notes)
        <p><strong>Notas de rodape:</strong> {{ $quote->footer_notes }}</p>
    @endif
    @if ($quote->print_comments)
        <p><strong>Comentarios:</strong> {{ $quote->print_comments }}</p>
    @endif
</body>
</html>

