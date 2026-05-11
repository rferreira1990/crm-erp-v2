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
                    <td style="padding:16px 24px;border-bottom:1px solid #e5e7eb;">
                        <div style="font-size:18px;font-weight:700;color:#111827;">{{ $companyName }}</div>
                        <div style="margin-top:4px;font-size:12px;color:#6b7280;">Mensagem enviada pelo ERP</div>
                    </td>
                </tr>
                <tr>
                    <td style="padding:24px;">
                        <div style="margin:0 0 12px;font-size:14px;line-height:1.8;color:#1f2937;white-space:normal;">{!! nl2br(e($bodyText)) !!}</div>
                        @if (! empty($signatureHtml))
                            <div style="margin-top:14px;padding-top:14px;border-top:1px solid #e5e7eb;font-size:14px;line-height:1.7;color:#1f2937;">
                                {!! $signatureHtml !!}
                            </div>
                        @endif
                    </td>
                </tr>
                <tr>
                    <td style="padding:14px 24px;background:#f9fafb;border-top:1px solid #e5e7eb;font-size:12px;line-height:1.6;color:#6b7280;">
                        <strong style="color:#374151;">{{ $companyName }}</strong><br />
                        @if (! empty($company->email))
                            Email: {{ $company->email }}<br />
                        @endif
                        @if (! empty($company->phone))
                            Telefone: {{ $company->phone }}<br />
                        @endif
                        @if (! empty($company->website))
                            Website: {{ $company->website }}<br />
                        @endif
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
