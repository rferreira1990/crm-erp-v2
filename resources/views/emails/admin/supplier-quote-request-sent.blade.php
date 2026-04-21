@php
    $supplierName = $rfqSupplier->supplier_name ?: 'Fornecedor';
    $hasCustomMessage = ! empty($messageBody);
    $companyInitial = strtoupper(mb_substr((string) $companyName, 0, 1));
@endphp
<!doctype html>
<html lang="pt">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>{{ $subjectLine }}</title>
</head>
<body style="margin:0;padding:0;background-color:#f5f7fb;font-family:Arial,Helvetica,sans-serif;color:#111827;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#f5f7fb;padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="640" cellspacing="0" cellpadding="0" border="0" style="width:100%;max-width:640px;background-color:#ffffff;border:1px solid #e5e7eb;border-radius:10px;overflow:hidden;">
                    <tr>
                        <td style="padding:24px 28px;background-color:#ffffff;border-bottom:1px solid #e5e7eb;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                                <tr>
                                    <td style="vertical-align:middle;">
                                        @if ($brandLogoUrl)
                                            <img src="{{ $brandLogoUrl }}" alt="{{ $companyName }}" style="max-height:44px;max-width:180px;display:block;" />
                                        @else
                                            <div style="width:44px;height:44px;border-radius:8px;background-color:#1D4ED8;color:#ffffff;font-size:18px;line-height:44px;text-align:center;font-weight:700;">{{ $companyInitial }}</div>
                                        @endif
                                    </td>
                                    <td align="right" style="vertical-align:middle;">
                                        <div style="font-size:12px;line-height:1.4;color:#6b7280;">{{ $companyName }}</div>
                                        <div style="margin-top:3px;font-size:20px;line-height:1.2;font-weight:700;color:#111827;">Pedido de Cotacao</div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px;">
                            <p style="margin:0 0 16px;font-size:15px;line-height:1.6;color:#111827;">Exmos. Senhores {{ $supplierName }},</p>
                            <p style="margin:0 0 14px;font-size:15px;line-height:1.6;color:#111827;">
                                Enviamos em anexo o nosso pedido de cotacao para analise.
                            </p>
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin:16px 0 20px;border:1px solid #e5e7eb;border-radius:8px;">
                                <tr>
                                    <td style="padding:14px 16px;">
                                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                                            <tr>
                                                <td style="padding:4px 0;font-size:13px;color:#6b7280;width:180px;">Documento</td>
                                                <td style="padding:4px 0;font-size:14px;color:#111827;font-weight:700;">{{ $summary['number'] }}</td>
                                            </tr>
                                            <tr>
                                                <td style="padding:4px 0;font-size:13px;color:#6b7280;">Titulo</td>
                                                <td style="padding:4px 0;font-size:14px;color:#111827;">{{ $summary['title'] }}</td>
                                            </tr>
                                            <tr>
                                                <td style="padding:4px 0;font-size:13px;color:#6b7280;">Data emissao</td>
                                                <td style="padding:4px 0;font-size:14px;color:#111827;">{{ $summary['issue_date'] }}</td>
                                            </tr>
                                            <tr>
                                                <td style="padding:4px 0;font-size:13px;color:#6b7280;">Prazo resposta</td>
                                                <td style="padding:4px 0;font-size:14px;color:#111827;">{{ $summary['response_deadline'] }}</td>
                                            </tr>
                                            <tr>
                                                <td style="padding:4px 0;font-size:13px;color:#6b7280;">Numero de linhas</td>
                                                <td style="padding:4px 0;font-size:14px;color:#111827;">{{ $summary['items_count'] }}</td>
                                            </tr>
                                            @if (! empty($summary['assigned_user']))
                                                <tr>
                                                    <td style="padding:4px 0;font-size:13px;color:#6b7280;">Responsavel</td>
                                                    <td style="padding:4px 0;font-size:14px;color:#111827;">{{ $summary['assigned_user'] }}</td>
                                                </tr>
                                            @endif
                                        </table>
                                    </td>
                                </tr>
                            </table>
                            @if ($hasCustomMessage)
                                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin:0 0 18px;background-color:#f8fafc;border-left:4px solid #1D4ED8;">
                                    <tr>
                                        <td style="padding:14px 16px;">
                                            <div style="margin:0 0 6px;font-size:12px;font-weight:700;letter-spacing:.3px;color:#6b7280;text-transform:uppercase;">Mensagem</div>
                                            <div style="font-size:14px;line-height:1.65;color:#1f2937;">{!! nl2br(e($messageBody)) !!}</div>
                                        </td>
                                    </tr>
                                </table>
                            @endif
                            <p style="margin:0;font-size:15px;line-height:1.6;color:#111827;">
                                Aguardamos o vosso melhor preco e prazo de entrega.<br />
                                <strong>{{ $companyName }}</strong>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:18px 28px;background-color:#f9fafb;border-top:1px solid #e5e7eb;">
                            <div style="font-size:12px;line-height:1.65;color:#6b7280;">
                                <strong style="color:#374151;">{{ $companyName }}</strong><br />
                                @if (! empty($contact['phone']))
                                    Telefone: {{ $contact['phone'] }}<br />
                                @endif
                                @if (! empty($contact['mobile']))
                                    Telemovel: {{ $contact['mobile'] }}<br />
                                @endif
                                @if (! empty($contact['email']))
                                    Email: <a href="mailto:{{ $contact['email'] }}" style="color:#1D4ED8;text-decoration:none;">{{ $contact['email'] }}</a><br />
                                @endif
                                @if (! empty($contact['website']))
                                    Website: <a href="{{ $contact['website'] }}" style="color:#1D4ED8;text-decoration:none;">{{ $contact['website'] }}</a><br />
                                @endif
                                @if (! empty($contact['nif']))
                                    NIF: {{ $contact['nif'] }}<br />
                                @endif
                                @if (! empty($contact['address']))
                                    Morada: {{ $contact['address'] }}<br />
                                @endif
                                @if (! empty($contact['location']))
                                    {{ $contact['location'] }}<br />
                                @endif
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>

