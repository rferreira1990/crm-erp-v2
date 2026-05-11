<!doctype html>
<html lang="pt-PT">
<head>
    <meta charset="UTF-8">
    <title>{{ $quote->number }}</title>
    <style>
        @page { margin: 0; }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: DejaVu Sans, Arial, Helvetica, sans-serif;
            color: #10213f;
            background: #ffffff;
            font-size: 12px;
        }

        .page {
            margin: 0 auto;
            background: #fff;
            position: relative;
            overflow: hidden;
            padding: 22mm 18mm 28mm;
        }

        .top-line {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 12px;
            background: #062b5f;
        }

        .corner {
            position: absolute;
            top: 0;
            right: 0;
            width: 120px;
            height: 70px;
            background: #1768b8;
        }

        .header-table,
        .title-table,
        .info-table,
        .notes-table {
            width: 100%;
            border-collapse: collapse;
        }

        .header-table {
            margin-bottom: 24px;
        }

        .header-table td {
            vertical-align: top;
        }

        .brand-table {
            border-collapse: collapse;
        }

        .brand-table td {
            vertical-align: middle;
        }

        .logo-mark {
            width: auto;
            height: auto;
            color: #062b5f;
            text-align: center;
            line-height: 1;
            font-size: 46px;
            font-weight: 800;
            letter-spacing: -2px;
            background: transparent;
            border-radius: 0;
            overflow: visible;
        }

        .logo-mark img {
            max-width: 150px;
            max-height: 70px;
            display: block;
        }

        .brand-text {
            padding-left: 10px;
        }

        .brand-text h1 {
            margin: 0;
            line-height: 1;
            color: #062b5f;
            font-weight: 700;
            text-transform: uppercase;
            white-space: normal;
            word-break: break-word;
        }

        .company-info {
            border-left: 1px solid #dce6f2;
            padding-left: 20px;
            font-size: 12px;
            line-height: 1.45;
        }

        .title-table {
            margin-bottom: 18px;
        }

        .doc-title h2 {
            margin: 0;
            font-size: 34px;
            letter-spacing: 1px;
            color: #062b5f;
        }

        .small-bar {
            width: 56px;
            height: 3px;
            margin-top: 8px;
            background: #1768b8;
            border-radius: 10px;
        }

        .budget-box {
            background: #f3f7fb;
            border: 1px solid #dce6f2;
            border-radius: 12px;
            padding: 10px 12px;
            font-size: 12px;
        }

        .budget-inner {
            width: 100%;
            border-collapse: collapse;
        }

        .budget-inner td {
            padding: 6px 0;
            border-bottom: 1px solid #dce6f2;
        }

        .budget-inner tr:last-child td {
            border-bottom: 0;
        }

        .budget-inner .left {
            color: #062b5f;
            font-weight: 700;
            text-transform: uppercase;
        }

        .budget-inner .right {
            text-align: right;
            color: #0b4a93;
            font-weight: 700;
        }

        .info-table {
            margin-bottom: 24px;
        }

        .info-table td {
            vertical-align: top;
        }

        .section-title {
            margin: 0 0 10px;
            color: #1768b8;
            font-size: 14px;
            letter-spacing: 1px;
            text-transform: uppercase;
            font-weight: 700;
        }

        .card {
            border-left: 4px solid #1768b8;
            padding: 4px 0 4px 14px;
            font-size: 14px;
            line-height: 1.5;
            color: #10213f;
        }

        .card strong {
            color: #062b5f;
        }

        .lines {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            font-size: 13px;
        }

        .lines thead th {
            background: #062b5f;
            color: #fff;
            padding: 11px 10px;
            text-align: left;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .5px;
        }

        .lines tbody td {
            border: 1px solid #dce6f2;
            padding: 12px 10px;
            vertical-align: top;
        }

        .lines tbody tr:nth-child(even) {
            background: #fbfdff;
        }

        .right { text-align: right; white-space: nowrap; }
        .center { text-align: center; }

        .desc strong {
            display: block;
            color: #062b5f;
            margin-bottom: 4px;
        }

        .desc .print-note {
            color: #35629a;
            margin-top: 4px;
        }

        .summary {
            width: 310px;
            margin-left: auto;
            margin-top: 0;
            border: 1px solid #dce6f2;
            border-top: 0;
            font-size: 14px;
        }

        .summary-row {
            width: 100%;
            border-collapse: collapse;
        }

        .summary-row td {
            border-bottom: 1px solid #dce6f2;
            padding: 12px 18px;
        }

        .summary-row tr:last-child td {
            border-bottom: 0;
        }

        .summary-row .total td {
            background: #062b5f;
            color: #fff;
            font-size: 18px;
            font-weight: 800;
        }

        .notes-table {
            margin-top: 30px;
            font-size: 12px;
            line-height: 1.55;
        }

        .notes-table td {
            width: 50%;
            vertical-align: top;
            padding-right: 12px;
        }

        .notes-table td:last-child {
            padding-right: 0;
        }

        .note-box {
            background: #f7faff;
            border: 1px solid #dce6f2;
            border-radius: 10px;
            padding: 14px;
            min-height: 130px;
        }

        .note-box h4 {
            margin: 0 0 8px;
            color: #1768b8;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .7px;
        }

        .footer {
            position: absolute;
            left: 0;
            right: 0;
            bottom: 0;
            background: #062b5f;
            color: #fff;
            padding: 14px 18mm;
        }

        .footer-table {
            width: 100%;
            border-collapse: collapse;
        }

        .footer-table td {
            vertical-align: middle;
        }

        .mini-logo {
            max-width: 52px;
            max-height: 34px;
            display: block;
        }

        .mini-logo-fallback {
            color: #ffffff;
            font-weight: 800;
            font-size: 24px;
            line-height: 1;
        }

        .footer strong {
            display: block;
            font-size: 15px;
            margin-bottom: 3px;
        }
    </style>
</head>
<body>
@php
    $companyLogoDataUri = $companyLogoDataUri ?? null;
    $isDraft = $quote->status === \App\Models\Quote::STATUS_DRAFT;
    $customerName = $quote->customer_name ?? ($isDraft ? $quote->customer?->name : null);
    $customerNif = $quote->customer_nif ?? ($isDraft ? $quote->customer?->nif : null);
    $customerAddress = $quote->customer_address ?? ($isDraft ? $quote->customer?->address : null);
    $customerPostalCode = $quote->customer_postal_code ?? ($isDraft ? $quote->customer?->postal_code : null);
    $customerLocality = $quote->customer_locality ?? ($isDraft ? $quote->customer?->locality : null);
    $customerCity = $quote->customer_city ?? ($isDraft ? $quote->customer?->city : null);
    $customerEmail = $quote->customer_email ?? ($isDraft ? $quote->customer?->email : null);
    $customerPhone = $quote->customer_phone
        ?? $quote->customer_mobile
        ?? ($isDraft ? ($quote->customer?->phone ?? $quote->customer?->mobile) : null);
    $paymentTermName = $quote->payment_term_name ?? ($isDraft ? $quote->paymentTerm?->name : null);
    $paymentMethodName = $quote->payment_method_name ?? ($isDraft ? $quote->paymentMethod?->name : null);
    $issueDate = optional($quote->issue_date)->format('d/m/Y') ?? '-';
    $validityDays = ($quote->issue_date && $quote->valid_until)
        ? max(0, $quote->issue_date->diffInDays($quote->valid_until))
        : null;
    $companyName = strtoupper(trim((string) ($quote->company?->name ?? 'EMPRESA')));
    $companyNameChars = mb_strlen(str_replace(' ', '', $companyName));
    $companyNameSize = $companyNameChars > 20 ? 20 : 28;
    $companyNameSpacing = $companyNameChars > 20 ? 1 : 3;

    $vatRate = $quote->items
        ->first(fn ($item) => $item->line_type === \App\Models\QuoteItem::TYPE_ARTICLE && $item->vat_rate_percentage !== null)
        ?->vat_rate_percentage;

    $paymentLines = [];
    if ($paymentTermName) {
        foreach (preg_split('/[\r\n]+/', (string) $paymentTermName) as $line) {
            $line = trim($line);
            if ($line !== '') {
                $paymentLines[] = $line;
            }
        }
    }
    if ($paymentLines === []) {
        $paymentLines[] = 'Conforme condições acordadas.';
    }
@endphp

<main class="page">
    <div class="top-line"></div>
    <div class="corner"></div>

    <table class="header-table">
        <tr>
            <td style="width:58%;">
                <table class="brand-table">
                    <tr>
                        <td>
                            @if ($companyLogoDataUri)
                                <div class="logo-mark">
                                    <img src="{{ $companyLogoDataUri }}" alt="{{ $quote->company?->name ?? 'Empresa' }}">
                                </div>
                            @else
                                <div class="logo-mark">F</div>
                            @endif
                        </td>
                        <td class="brand-text">
                            <h1 style="font-size: {{ $companyNameSize }}px; letter-spacing: {{ $companyNameSpacing }}px;">{{ $companyName }}</h1>
                        </td>
                    </tr>
                </table>
            </td>
            <td style="width:42%;">
                <div class="company-info">
                    {{ $quote->company?->address ?? '-' }}<br>
                    {{ trim(implode(' ', array_filter([$quote->company?->postal_code, $quote->company?->locality, $quote->company?->city]))) ?: '-' }}<br>
                    {{ $quote->company?->phone ?? $quote->company?->mobile ?? '-' }}<br>
                    {{ $quote->company?->email ?? '-' }}<br>
                    {{ $quote->company?->website ?? '-' }}
                </div>
            </td>
        </tr>
    </table>

    <table class="title-table">
        <tr>
            <td style="width:58%; vertical-align:bottom;">
                <div class="doc-title">
                    <h2>ORÇAMENTO</h2>
                    <div class="small-bar"></div>
                </div>
            </td>
            <td style="width:42%;">
                <div class="budget-box">
                    <table class="budget-inner">
                        <tr><td class="left">Nº Orçamento</td><td class="right">{{ $quote->number }}</td></tr>
                        <tr><td class="left">Data</td><td class="right">{{ $issueDate }}</td></tr>
                        <tr><td class="left">Validade</td><td class="right">{{ $validityDays !== null ? $validityDays.' dias' : '-' }}</td></tr>
                    </table>
                </div>
            </td>
        </tr>
    </table>

    <table class="info-table">
        <tr>
            <td style="width:100%;">
                <h3 class="section-title">Cliente</h3>
                <div class="card">
                    <strong>{{ $customerName ?? '-' }}</strong><br>
                    @if ($customerNif)NIF: {{ $customerNif }}<br>@endif
                    {{ $customerAddress ?: '-' }}<br>
                    {{ trim(implode(' ', array_filter([$customerPostalCode, $customerLocality, $customerCity]))) ?: '-' }}<br>
                    {{ $customerEmail ?? '-' }}<br>
                    {{ $customerPhone ?? '-' }}
                </div>
            </td>
        </tr>
    </table>

    <table class="lines">
        <thead>
            <tr>
                <th>Descrição</th>
                <th class="center">Qtd.</th>
                <th class="center">Un.</th>
                <th class="right">Preço Unit.</th>
                <th class="right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($quote->items as $item)
                @if ($item->line_type === \App\Models\QuoteItem::TYPE_ARTICLE)
                    @php
                        $unitCode = $item->unit_code ?? ($isDraft ? $item->unit?->code : null);
                    @endphp
                    <tr>
                        <td class="desc">
                            <strong>{{ $item->description }}</strong>
                            @if (filled($item->article?->print_notes))
                                <div class="print-note">Nota: {{ $item->article->print_notes }}</div>
                            @endif
                        </td>
                        <td class="center">{{ number_format((float) $item->quantity, 2, ',', '.') }}</td>
                        <td class="center">{{ $unitCode ?? '-' }}</td>
                        <td class="right">€ {{ number_format((float) $item->unit_price, 2, ',', '.') }}</td>
                        <td class="right"><strong>€ {{ number_format((float) $item->total, 2, ',', '.') }}</strong></td>
                    </tr>
                @elseif (in_array($item->line_type, [\App\Models\QuoteItem::TYPE_SECTION, \App\Models\QuoteItem::TYPE_NOTE, \App\Models\QuoteItem::TYPE_TEXT], true))
                    <tr>
                        <td colspan="5" style="background:#f7fbff;">
                            <strong>{{ $item->description }}</strong>
                        </td>
                    </tr>
                @endif
            @endforeach
        </tbody>
    </table>

    <section class="summary">
        <table class="summary-row">
            <tr><td><strong>Subtotal</strong></td><td class="right">€ {{ number_format((float) $quote->subtotal, 2, ',', '.') }}</td></tr>
            <tr><td><strong>IVA{{ $vatRate !== null ? ' ('.number_format((float) $vatRate, 0, ',', '.').'%)' : '' }}</strong></td><td class="right">€ {{ number_format((float) $quote->tax_total, 2, ',', '.') }}</td></tr>
            <tr class="total"><td>Total</td><td class="right">€ {{ number_format((float) $quote->grand_total, 2, ',', '.') }}</td></tr>
        </table>
    </section>

    <table class="notes-table">
        <tr>
            <td>
                <div class="note-box">
                    <h4>Condições de pagamento</h4>
                    @foreach ($paymentLines as $paymentLine)
                        ✓ {{ $paymentLine }}<br>
                    @endforeach
                    @if ($paymentMethodName)
                        ✓ Modo: {{ $paymentMethodName }}
                    @endif
                </div>
            </td>
            <td>
                <div class="note-box">
                    <h4>Condições gerais</h4>
                    {{ $quote->footer_notes ?: 'Este orçamento é válido pelo período indicado e pode ser ajustado após análise técnica no local.' }}
                </div>
            </td>
        </tr>
    </table>

    <div class="footer">
        <table class="footer-table">
            <tr>
                <td style="width:56px;">
                    @if ($companyLogoDataUri)
                        <img src="{{ $companyLogoDataUri }}" alt="{{ $quote->company?->name ?? 'Empresa' }}" class="mini-logo">
                    @else
                        <span class="mini-logo-fallback">F</span>
                    @endif
                </td>
                <td>
                    <strong>Valorizamos o que é feito para durar.</strong>
                    Qualidade, rigor e paixão em cada detalhe.
                </td>
            </tr>
        </table>
    </div>
</main>
</body>
</html>
