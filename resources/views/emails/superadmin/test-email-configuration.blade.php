<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="utf-8">
    <title>Teste de configuracao de email</title>
</head>
<body>
    <h2>Configuracao de email valida</h2>
    <p>Este email confirma que a configuracao base de envio esta funcional.</p>

    <ul>
        <li><strong>Aplicacao:</strong> {{ $appName }}</li>
        <li><strong>From:</strong> {{ $fromName }} &lt;{{ $fromAddress }}&gt;</li>
    </ul>
</body>
</html>
