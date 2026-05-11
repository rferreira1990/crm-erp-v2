{{ $subjectLine }}

Exmos. Senhores {{ $payment->supplier?->name ?: 'Fornecedor' }},

Enviamos em anexo o Documento de Pagamento para vosso registo.

Pagamento: {{ $summary['payment_number'] }}
Data: {{ $summary['payment_date'] }}
Documento de Compra: {{ $summary['purchase_document_number'] ?? '-' }}
Modo de pagamento: {{ $summary['payment_method'] ?? '-' }}
Valor: {{ $summary['amount'] }}

@if (! empty($messageBody))
Mensagem:
{{ $messageBody }}

@endif
Qualquer esclarecimento, estamos ao dispor.

@if (! empty($signatureText))
{{ $signatureText }}
@else
{{ $companyName }}
@endif
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
