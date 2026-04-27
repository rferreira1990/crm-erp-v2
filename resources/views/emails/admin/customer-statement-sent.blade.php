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
                        <td style="padding:24px 28px;border-bottom:1px solid #e5e7eb;">
                            <div style="font-size:20px;line-height:1.2;font-weight:700;color:#111827;">Extrato de Conta Corrente</div>
                            <div style="margin-top:6px;font-size:12px;color:#6b7280;">{{ $company->name }} - {{ $customer->name }}</div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:24px 28px;">
                            <p style="margin:0 0 12px;font-size:15px;line-height:1.6;color:#111827;">Exmos. Senhores {{ $customer->name }},</p>
                            <p style="margin:0 0 12px;font-size:15px;line-height:1.6;color:#111827;">Enviamos em anexo o Extrato de Conta Corrente.</p>
                            <p style="margin:0 0 12px;font-size:14px;line-height:1.6;color:#374151;">{{ $periodLabel }}</p>
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin:14px 0 16px;border:1px solid #e5e7eb;border-radius:8px;">
                                <tr>
                                    <td style="padding:12px 14px;">
                                        <div style="font-size:14px;line-height:1.6;color:#111827;">Debitos: <strong>{{ number_format((float) $totalDebit, 2, ',', '.') }} €</strong></div>
                                        <div style="font-size:14px;line-height:1.6;color:#111827;">Creditos: <strong>{{ number_format((float) $totalCredit, 2, ',', '.') }} €</strong></div>
                                        <div style="font-size:14px;line-height:1.6;color:#111827;">Saldo: <strong>{{ number_format((float) $balance, 2, ',', '.') }} €</strong></div>
                                    </td>
                                </tr>
                            </table>
                            @if (! empty($messageBody))
                                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin:0 0 18px;background-color:#f8fafc;border-left:4px solid #1D4ED8;">
                                    <tr>
                                        <td style="padding:14px 16px;">
                                            <div style="margin:0 0 6px;font-size:12px;font-weight:700;letter-spacing:.3px;color:#6b7280;text-transform:uppercase;">Mensagem</div>
                                            <div style="font-size:14px;line-height:1.65;color:#1f2937;">{!! nl2br(e($messageBody)) !!}</div>
                                        </td>
                                    </tr>
                                </table>
                            @endif
                            <p style="margin:0;font-size:15px;line-height:1.6;color:#111827;">Qualquer esclarecimento, estamos ao dispor.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
