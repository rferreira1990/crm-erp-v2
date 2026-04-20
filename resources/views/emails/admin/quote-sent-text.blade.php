{{ $subjectLine }}

{{ $customerDisplayName !== '' ? 'Exmo.(a) Sr.(a) '.$customerDisplayName.',' : 'Caro(a) Cliente,' }}

E com satisfacao que enviamos em anexo a nossa proposta comercial referente ao pedido solicitado.

Documento: {{ $summary['number'] }}
Cliente: {{ $summary['customer'] }}
Data de emissao: {{ $summary['issue_date'] }}
Validade: {{ $summary['valid_until'] }}
Valor total: {{ $summary['total'] }}
@if (! empty($summary['assigned_user']))
Responsavel comercial: {{ $summary['assigned_user'] }}
@endif

@if (! empty($messageBody))
Mensagem:
{{ $messageBody }}

@endif
Estamos disponiveis para qualquer esclarecimento adicional.

Com os melhores cumprimentos,
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
