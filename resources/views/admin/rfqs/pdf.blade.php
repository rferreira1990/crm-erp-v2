<!doctype html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <title>{{ $rfq->number }}</title>
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 11px; color: #0f172a; margin: 24px; }
        .header { border-bottom: 2px solid #1e293b; padding-bottom: 10px; margin-bottom: 16px; }
        .header-table { width: 100%; border-collapse: collapse; }
        .header-logo-cell { width: 140px; vertical-align: top; }
        .header-logo { max-width: 120px; max-height: 54px; }
        .header-title-cell { text-align: right; vertical-align: top; }
        .doc-title { margin: 0 0 4px 0; font-size: 22px; font-weight: bold; letter-spacing: .3px; }
        .doc-meta { font-size: 10px; color: #475569; }
        .cards { width: 100%; margin-top: 8px; }
        .card { border: 1px solid #cbd5e1; border-radius: 4px; padding: 9px; min-height: 95px; }
        .card-title { margin: 0 0 6px 0; font-size: 10px; text-transform: uppercase; color: #64748b; }
        .lines { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .lines th, .lines td { border: 1px solid #cbd5e1; padding: 6px; vertical-align: top; }
        .lines th { background: #e2e8f0; font-size: 10px; text-transform: uppercase; }
        .section td { background: #f1f5f9; font-weight: bold; }
        .note td { background: #f8fafc; font-style: italic; }
        .footer { margin-top: 14px; color: #64748b; font-size: 10px; border-top: 1px solid #cbd5e1; padding-top: 8px; }
    </style>
</head>
<body>
    @php
        $companyAddress = trim((string) ($rfq->company?->address ?? ''));
        $companyLocation = trim(implode(' ', array_filter([
            $rfq->company?->postal_code,
            $rfq->company?->locality,
            $rfq->company?->city,
        ], fn ($value) => trim((string) $value) !== '')));
    @endphp

    <div class="header">
        <table class="header-table">
            <tr>
                <td class="header-logo-cell">
                    @if (! empty($companyLogoDataUri))
                        <img src="{{ $companyLogoDataUri }}" alt="{{ $rfq->company?->name ?? 'Empresa' }}" class="header-logo">
                    @endif
                </td>
                <td class="header-title-cell">
                    <p class="doc-title">Pedido de Cotacao</p>
                    <div class="doc-meta">
                        <strong>{{ $rfq->number }}</strong>
                        | Emissao: {{ optional($rfq->issue_date)->format('Y-m-d') }}
                        | Prazo resposta: {{ optional($rfq->response_deadline)->format('Y-m-d') ?? '-' }}
                        | Estado: {{ $rfq->statusLabel() }}
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <table class="cards">
        <tr>
            <td style="width:49%;vertical-align:top;">
                <div class="card">
                    <p class="card-title">Empresa</p>
                    <div><strong>{{ $rfq->company?->name ?? '-' }}</strong></div>
                    <div>NIF: {{ $rfq->company?->nif ?? '-' }}</div>
                    <div>{{ $rfq->company?->email ?? '-' }} | {{ $rfq->company?->phone ?? $rfq->company?->mobile ?? '-' }}</div>
                    @if ($companyAddress !== '')
                        <div>{{ $companyAddress }}</div>
                    @endif
                    @if ($companyLocation !== '')
                        <div>{{ $companyLocation }}</div>
                    @endif
                </div>
            </td>
            <td style="width:2%;"></td>
            <td style="width:49%;vertical-align:top;">
                <div class="card">
                    <p class="card-title">Pedido</p>
                    <div><strong>{{ $rfq->title ?: 'Consulta de precos' }}</strong></div>
                    <div>Responsavel: {{ $rfq->assignedUser?->name ?? '-' }}</div>
                    <div>Criado por: {{ $rfq->creator?->name ?? '-' }}</div>
                    <div>Total estimado: {{ $rfq->estimated_total !== null ? number_format((float) $rfq->estimated_total, 2, ',', '.').' EUR' : '-' }}</div>
                </div>
            </td>
        </tr>
    </table>

    <table class="lines">
        <thead>
            <tr>
                <th style="width:6%;">#</th>
                <th style="width:12%;">Tipo</th>
                <th style="width:14%;">Codigo</th>
                <th style="width:38%;">Descricao</th>
                <th style="width:10%;">Unidade</th>
                <th style="width:10%;">Qtd.</th>
                <th style="width:10%;">Notas</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rfq->items as $item)
                @php
                    $rowClass = $item->line_type === \App\Models\SupplierQuoteRequestItem::TYPE_SECTION
                        ? 'section'
                        : ($item->line_type === \App\Models\SupplierQuoteRequestItem::TYPE_NOTE ? 'note' : '');
                @endphp
                <tr class="{{ $rowClass }}">
                    <td>{{ $item->line_order }}</td>
                    <td>{{ \App\Models\SupplierQuoteRequestItem::lineTypeLabels()[$item->line_type] ?? $item->line_type }}</td>
                    <td>{{ $item->article_code ?: '-' }}</td>
                    <td>{{ $item->description }}</td>
                    <td>{{ $item->unit_name ?: '-' }}</td>
                    <td>{{ number_format((float) $item->quantity, 3, ',', '.') }}</td>
                    <td>{{ $item->internal_notes ?: '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" style="text-align:center;">Sem linhas no pedido.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    @if ($rfq->supplier_notes)
        <div style="margin-top: 12px;">
            <strong>Notas para fornecedor</strong><br>
            {!! nl2br(e($rfq->supplier_notes)) !!}
        </div>
    @endif

    <div class="footer">
        Documento gerado em {{ now()->format('Y-m-d H:i') }}.
    </div>
</body>
</html>

