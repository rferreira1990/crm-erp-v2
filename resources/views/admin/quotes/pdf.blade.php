<!doctype html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <title>{{ $quote->number }}</title>
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 11px; color: #0f172a; margin: 26px; }
        .header { border-bottom: 2px solid #1e293b; padding-bottom: 10px; margin-bottom: 16px; }
        .header-table { width: 100%; border-collapse: collapse; }
        .header-table td { vertical-align: top; }
        .header-logo-cell { width: 140px; }
        .header-logo { max-width: 120px; max-height: 54px; }
        .header-doc-cell { text-align: right; }
        .doc-title { margin: 0 0 4px 0; font-size: 22px; font-weight: bold; letter-spacing: 0.3px; }
        .doc-meta { font-size: 10px; color: #475569; }
        .company-small { font-size: 10px; line-height: 1.35; color: #334155; }
        .row { width: 100%; margin-bottom: 12px; }
        .col-50 { width: 49%; display: inline-block; vertical-align: top; }
        .card { border: 1px solid #cbd5e1; border-radius: 4px; padding: 10px; min-height: 94px; }
        .card-title { margin: 0 0 6px 0; font-size: 10px; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b; }
        .strong { font-weight: bold; }
        .muted { color: #475569; }
        .conditions { border: 1px solid #cbd5e1; border-radius: 4px; padding: 8px 10px; margin-bottom: 12px; }
        .conditions td { padding: 2px 8px 2px 0; }
        .lines { width: 100%; border-collapse: collapse; margin-top: 8px; }
        .lines th, .lines td { border: 1px solid #cbd5e1; padding: 6px; vertical-align: top; }
        .lines th { background: #e2e8f0; font-size: 10px; text-transform: uppercase; letter-spacing: 0.3px; }
        .lines .section td { background: #f1f5f9; font-weight: bold; }
        .lines .note td { background: #f8fafc; color: #334155; font-style: italic; }
        .text-right { text-align: right; }
        .totals { width: 42%; margin-left: auto; margin-top: 10px; border-collapse: collapse; }
        .totals td { border: 1px solid #cbd5e1; padding: 6px 8px; }
        .totals .final td { background: #e2e8f0; font-weight: bold; font-size: 12px; }
        .notes { margin-top: 14px; }
        .notes h4 { margin: 0 0 4px 0; font-size: 11px; }
        .notes p { margin: 0 0 8px 0; line-height: 1.4; }
        .footer { margin-top: 16px; color: #64748b; font-size: 10px; border-top: 1px solid #cbd5e1; padding-top: 8px; }
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
        $contactName = $quote->customer_contact_name ?? ($isDraft ? $quote->customerContact?->name : null);
        $contactJobTitle = $quote->customer_contact_job_title ?? ($isDraft ? $quote->customerContact?->job_title : null);
        $contactEmail = $quote->customer_contact_email ?? ($isDraft ? $quote->customerContact?->email : null);
        $paymentTermName = $quote->payment_term_name ?? ($isDraft ? $quote->paymentTerm?->name : null);
        $paymentMethodName = $quote->payment_method_name ?? ($isDraft ? $quote->paymentMethod?->name : null);
        $companyAddress = trim((string) ($quote->company?->address ?? ''));
        $companyLocation = trim(implode(' ', array_filter([
            $quote->company?->postal_code,
            $quote->company?->locality,
            $quote->company?->city,
        ], fn ($value) => trim((string) $value) !== '')));
        $hasBankDetails = trim((string) ($quote->company?->bank_name ?? '')) !== ''
            || trim((string) ($quote->company?->iban ?? '')) !== ''
            || trim((string) ($quote->company?->bic_swift ?? '')) !== '';
    @endphp

    <div class="header">
        <table class="header-table">
            <tr>
                <td class="header-logo-cell">
                    @if ($companyLogoDataUri)
                        <img src="{{ $companyLogoDataUri }}" alt="{{ $quote->company?->name ?? 'Empresa' }}" class="header-logo">
                    @else
                        <div class="strong">{{ $quote->company?->name ?? '-' }}</div>
                    @endif
                </td>
                <td>
                    <div class="company-small">
                        <div class="strong">{{ $quote->company?->name ?? '-' }}</div>
                        @if ($companyAddress !== '')
                            <div>{{ $companyAddress }}</div>
                        @endif
                        @if ($companyLocation !== '')
                            <div>{{ $companyLocation }}</div>
                        @endif
                        <div>
                            @if ($quote->company?->email)
                                {{ $quote->company->email }}
                            @endif
                            @if ($quote->company?->phone || $quote->company?->mobile)
                                @if ($quote->company?->email)
                                    |
                                @endif
                                {{ $quote->company?->phone ?? $quote->company?->mobile }}
                            @endif
                        </div>
                    </div>
                </td>
                <td class="header-doc-cell">
                    <p class="doc-title">Proposta Comercial</p>
                    <div class="doc-meta">
                        <span class="strong">{{ $quote->number }}</span>
                        | Emissao: {{ optional($quote->issue_date)->format('Y-m-d') }}
                        | Validade: {{ optional($quote->valid_until)->format('Y-m-d') ?? '-' }}
                        | Estado: {{ $quote->statusLabel() }}
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <div class="row">
        <div class="col-50">
            <div class="card">
                <p class="card-title">Empresa</p>
                <div class="strong">{{ $quote->company?->name ?? '-' }}</div>
                <div>NIF: {{ $quote->company?->nif ?? '-' }}</div>
                <div>{{ $quote->company?->email ?? '-' }} | {{ $quote->company?->phone ?? $quote->company?->mobile ?? '-' }}</div>
                @if ($companyAddress !== '')
                    <div>{{ $companyAddress }}</div>
                @endif
                @if ($companyLocation !== '')
                    <div>{{ $companyLocation }}</div>
                @endif
                @if ($quote->company?->website)
                    <div>{{ $quote->company->website }}</div>
                @endif
            </div>
        </div>
        <div class="col-50" style="float:right;">
            <div class="card">
                <p class="card-title">Cliente</p>
                <div class="strong">{{ $customerName ?? '-' }}</div>
                <div>NIF: {{ $customerNif ?? '-' }}</div>
                <div>{{ $customerAddress ?? '-' }}</div>
                <div>{{ $customerPostalCode ?? '' }} {{ $customerLocality ?? '' }} {{ $customerCity ?? '' }}</div>
                <div>{{ $customerEmail ?? '-' }} | {{ $customerPhone ?? '-' }}</div>
                @if ($contactName)
                    <div class="muted" style="margin-top: 4px;">
                        Contacto: {{ $contactName }}
                        @if ($contactJobTitle)
                            ({{ $contactJobTitle }})
                        @endif
                        @if ($contactEmail)
                            | {{ $contactEmail }}
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </div>

    <table class="conditions">
        <tr>
            <td><span class="muted">Cond. pagamento:</span> <span class="strong">{{ $paymentTermName ?? '-' }}</span></td>
            <td><span class="muted">Modo pagamento:</span> <span class="strong">{{ $paymentMethodName ?? '-' }}</span></td>
            <td><span class="muted">Moeda:</span> <span class="strong">{{ $quote->currency }}</span></td>
        </tr>
    </table>

    @if ($hasBankDetails)
        <table class="conditions" style="margin-top: 6px;">
            <tr>
                <td><span class="muted">Banco:</span> <span class="strong">{{ $quote->company?->bank_name ?? '-' }}</span></td>
                <td><span class="muted">IBAN:</span> <span class="strong">{{ $quote->company?->iban ?? '-' }}</span></td>
                <td><span class="muted">BIC/SWIFT:</span> <span class="strong">{{ $quote->company?->bic_swift ?? '-' }}</span></td>
            </tr>
        </table>
    @endif

    @if ($quote->header_notes)
        <div class="notes">
            <h4>Observacoes iniciais</h4>
            <p>{!! nl2br(e($quote->header_notes)) !!}</p>
        </div>
    @endif

    <table class="lines">
        <thead>
            <tr>
                <th style="width:5%;">#</th>
                <th style="width:31%;">Descricao</th>
                <th style="width:9%;" class="text-right">Qtd</th>
                <th style="width:8%;">Unid.</th>
                <th style="width:13%;" class="text-right">P. Unit.</th>
                <th style="width:10%;" class="text-right">Desc.</th>
                <th style="width:11%;" class="text-right">IVA</th>
                <th style="width:13%;" class="text-right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($quote->items as $item)
                @if ($item->line_type === \App\Models\QuoteItem::TYPE_SECTION)
                    <tr class="section">
                        <td>{{ $item->sort_order }}</td>
                        <td colspan="7">{{ $item->description }}</td>
                    </tr>
                @elseif ($item->line_type === \App\Models\QuoteItem::TYPE_NOTE)
                    <tr class="note">
                        <td>{{ $item->sort_order }}</td>
                        <td colspan="7">{{ $item->description }}</td>
                    </tr>
                @else
                    @php
                        $articleCode = $item->article_code ?? ($isDraft ? $item->article?->code : null);
                        $unitCode = $item->unit_code ?? ($isDraft ? $item->unit?->code : null);
                        $vatRatePercentage = $item->vat_rate_percentage ?? ($isDraft ? $item->vatRate?->rate : null);
                        $exemptionCode = $item->vat_exemption_reason_code ?? ($isDraft ? $item->vatExemptionReason?->code : null);
                    @endphp
                    <tr>
                        <td>{{ $item->sort_order }}</td>
                        <td>
                            {{ $item->description }}
                            @if ($articleCode)
                                <div class="muted">{{ $articleCode }}</div>
                            @endif
                        </td>
                        <td class="text-right">{{ number_format((float) $item->quantity, 3, ',', '.') }}</td>
                        <td>{{ $unitCode ?? '-' }}</td>
                        <td class="text-right">{{ number_format((float) $item->unit_price, 4, ',', '.') }}</td>
                        <td class="text-right">{{ number_format((float) ($item->discount_percent ?? 0), 2, ',', '.') }}%</td>
                        <td class="text-right">
                            @if ($item->vat_rate_name || $vatRatePercentage !== null)
                                {{ number_format((float) ($vatRatePercentage ?? 0), 2, ',', '.') }}%
                                @if ($exemptionCode)
                                    <div class="muted">{{ $exemptionCode }}</div>
                                @endif
                            @else
                                -
                            @endif
                        </td>
                        <td class="text-right strong">{{ number_format((float) $item->total, 2, ',', '.') }} {{ $quote->currency }}</td>
                    </tr>
                @endif
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tbody>
            <tr>
                <td>Subtotal</td>
                <td class="text-right">{{ number_format((float) $quote->subtotal, 2, ',', '.') }} {{ $quote->currency }}</td>
            </tr>
            <tr>
                <td>Total desconto</td>
                <td class="text-right">{{ number_format((float) $quote->discount_total, 2, ',', '.') }} {{ $quote->currency }}</td>
            </tr>
            <tr>
                <td>Total IVA</td>
                <td class="text-right">{{ number_format((float) $quote->tax_total, 2, ',', '.') }} {{ $quote->currency }}</td>
            </tr>
            <tr class="final">
                <td>Total final</td>
                <td class="text-right">{{ number_format((float) $quote->grand_total, 2, ',', '.') }} {{ $quote->currency }}</td>
            </tr>
        </tbody>
    </table>

    <div class="notes">
        @if ($quote->customer_message)
            <h4>Mensagem ao cliente</h4>
            <p>{!! nl2br(e($quote->customer_message)) !!}</p>
        @endif

        @if ($quote->footer_notes)
            <h4>Notas finais</h4>
            <p>{!! nl2br(e($quote->footer_notes)) !!}</p>
        @endif

        @if ($quote->print_comments)
            <h4>Comentarios para impressao</h4>
            <p>{!! nl2br(e($quote->print_comments)) !!}</p>
        @endif
    </div>

    <div class="footer">
        Documento gerado em {{ now()->format('Y-m-d H:i') }}.
        @if ($quote->company?->email || $quote->company?->phone || $quote->company?->website)
            | Contactos empresa:
            {{ $quote->company?->email ?? '-' }}
            @if ($quote->company?->phone || $quote->company?->mobile)
                / {{ $quote->company?->phone ?? $quote->company?->mobile }}
            @endif
            @if ($quote->company?->website)
                / {{ $quote->company->website }}
            @endif
        @endif
    </div>
</body>
</html>
