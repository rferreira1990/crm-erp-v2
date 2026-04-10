<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="utf-8">
    <title>Convite de acesso</title>
</head>
<body>
    <h2>Convite para acesso a empresa</h2>
    <p>Recebeu um convite para entrar na plataforma <strong>{{ $appName }}</strong>.</p>

    <ul>
        <li><strong>Empresa:</strong> {{ $companyName }}</li>
        <li><strong>Perfil:</strong> {{ $role }}</li>
        @if ($expiresAt)
            <li><strong>Expira em:</strong> {{ $expiresAt }}</li>
        @endif
    </ul>

    <p>Use o link abaixo para aceitar o convite:</p>
    <p><a href="{{ $invitationUrl }}">{{ $invitationUrl }}</a></p>

    <p>Se nao reconhece este convite, pode ignorar este email.</p>
</body>
</html>
