{{ $subjectLine }}

Exmos. Senhores {{ $customer->name }},

Enviamos em anexo o Extrato de Conta Corrente.
{{ $periodLabel }}

Debitos: {{ number_format((float) $totalDebit, 2, ',', '.') }} €
Creditos: {{ number_format((float) $totalCredit, 2, ',', '.') }} €
Saldo: {{ number_format((float) $balance, 2, ',', '.') }} €

@if (! empty($messageBody))
Mensagem:
{{ $messageBody }}

@endif
Qualquer esclarecimento, estamos ao dispor.

{{ $company->name }}
