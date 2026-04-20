<p>Teste de ligacao SMTP realizado com sucesso.</p>

<p><strong>Empresa:</strong> {{ $companyName }}</p>
<p><strong>Modo:</strong> {{ $mailMode }}</p>
<p><strong>Host:</strong> {{ $mailHost ?: '-' }}</p>
<p><strong>Porta:</strong> {{ $mailPort ?: '-' }}</p>
<p><strong>Encriptacao:</strong> {{ $mailEncryption ?: 'none' }}</p>

<p>Este email confirma que a configuracao atual de envio esta funcional.</p>

