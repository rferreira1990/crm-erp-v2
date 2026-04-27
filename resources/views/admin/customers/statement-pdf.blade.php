<!doctype html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <title>Extrato {{ $customer->name }}</title>
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 11px; color: #0f172a; margin: 22px; }
        .header { border-bottom: 2px solid #1e293b; padding-bottom: 10px; margin-bottom: 16px; }
        .header-table { width: 100%; border-collapse: collapse; }
        .header-table td { vertical-align: top; }
        .header-logo-cell { width: 140px; }
        .header-logo { max-width: 120px; max-height: 54px; }
        .header-doc-cell { text-align: right; }
        .doc-title { margin: 0 0 4px 0; font-size: 20px; font-weight: bold; }
        .doc-meta { font-size: 10px; color: #475569; }
        .summary { margin: 10px 0 12px; }
        .summary-table { width: 100%; border-collapse: collapse; }
        .summary-table td { border: 1px solid #cbd5e1; padding: 6px 8px; }
        .lines { width: 100%; border-collapse: collapse; margin-top: 8px; }
        .lines th, .lines td { border: 1px solid #cbd5e1; padding: 6px; vertical-align: top; }
        .lines th { background: #e2e8f0; font-size: 10px; text-transform: uppercase; }
        .text-right { text-align: right; }
        .footer { margin-top: 12px; width: 40%; margin-left: auto; border-collapse: collapse; }
        .footer td { border: 1px solid #cbd5e1; padding: 6px 8px; }
        .footer .final td { background: #e2e8f0; font-weight: bold; }
    </style>
</head>
<body>
    <div class="header">
        <table class="header-table">
            <tr>
                <td class="header-logo-cell">
                    @if ($companyLogoDataUri)
                        <img src="{{ $companyLogoDataUri }}" alt="{{ $customer->company?->name ?? 'Empresa' }}" class="header-logo">
                    @else
                        <strong>{{ $customer->company?->name ?? '-' }}</strong>
                    @endif
                </td>
                <td class="header-doc-cell">
                    <p class="doc-title">Extrato de Conta Corrente</p>
                    <div class="doc-meta">Empresa: {{ $customer->company?->name ?? '-' }}</div>
                    <div class="doc-meta">Cliente: {{ $customer->name }}</div>
                    <div class="doc-meta">Emitido em: {{ $generatedAt->format('Y-m-d H:i') }}</div>
                    <div class="doc-meta">{{ $periodLabel }}</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="summary">
        <table class="summary-table">
            <tr>
                <td><strong>Debitos</strong>: {{ number_format((float) $totalDebit, 2, ',', '.') }} €</td>
                <td><strong>Creditos</strong>: {{ number_format((float) $totalCredit, 2, ',', '.') }} €</td>
                <td><strong>Saldo</strong>: {{ number_format((float) $balance, 2, ',', '.') }} €</td>
            </tr>
        </table>
    </div>

    <table class="lines">
        <thead>
            <tr>
                <th style="width: 11%;">Data</th>
                <th style="width: 16%;">Tipo</th>
                <th style="width: 18%;">Documento</th>
                <th style="width: 23%;">Descricao</th>
                <th style="width: 10%;" class="text-right">Debito</th>
                <th style="width: 10%;" class="text-right">Credito</th>
                <th style="width: 12%;" class="text-right">Saldo</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($movements as $movement)
                <tr>
                    <td>{{ optional($movement['date'])->format('Y-m-d') ?? '-' }}</td>
                    <td>{{ $movement['type'] === 'sales_document' ? 'Documento de Venda' : ($movement['status'] === \App\Models\SalesDocumentReceipt::STATUS_ISSUED ? 'Recibo' : 'Recibo cancelado') }}</td>
                    <td>{{ $movement['number'] }}</td>
                    <td>{{ $movement['description'] }}</td>
                    <td class="text-right">{{ number_format((float) $movement['debit'], 2, ',', '.') }} €</td>
                    <td class="text-right">{{ number_format((float) $movement['credit'], 2, ',', '.') }} €</td>
                    <td class="text-right">{{ number_format((float) $movement['balance'], 2, ',', '.') }} €</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" style="text-align:center;color:#64748b;">Sem movimentos no periodo selecionado.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <table class="footer">
        <tr class="final">
            <td>Saldo final</td>
            <td class="text-right">{{ number_format((float) $balance, 2, ',', '.') }} €</td>
        </tr>
    </table>
</body>
</html>
