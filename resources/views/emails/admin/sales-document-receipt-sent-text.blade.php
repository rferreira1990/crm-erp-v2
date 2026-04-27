{{ $subjectLine }}

Exmos. Senhores {{ $receipt->customer?->name ?: 'Cliente' }},

Enviamos em anexo o Recibo para vosso registo.

Recibo: {{ $summary['receipt_number'] }}
Data: {{ $summary['receipt_date'] }}
Documento de Venda: {{ $summary['sales_document_number'] ?? '-' }}
Modo de pagamento: {{ $summary['payment_method'] ?? '-' }}
Valor: {{ $summary['amount'] }}

@if (! empty($messageBody))
Mensagem:
{{ $messageBody }}

@endif
Qualquer esclarecimento, estamos ao dispor.

{{ $companyName }}
@if (! empty($contact['phone']))
Telefone: {{ $contact['phone'] }}
@endif
@if (! empty($contact['mobile']))
Telemovel: {{ $contact['mobile'] }}
@endif
@if (! empty($contact['email']))
Email: {{ $contact['email'] }}
@endif
@if (! empty($contact['website']))
Website: {{ $contact['website'] }}
@endif
@if (! empty($contact['nif']))
NIF: {{ $contact['nif'] }}
@endif
@if (! empty($contact['address']))
Morada: {{ $contact['address'] }}
@endif
@if (! empty($contact['location']))
{{ $contact['location'] }}
@endif
