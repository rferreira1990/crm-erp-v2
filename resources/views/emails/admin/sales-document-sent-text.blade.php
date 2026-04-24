{{ $subjectLine }}

Exmos. Senhores {{ $document->customer_contact_name_snapshot ?: $document->customer_name_snapshot ?: 'Cliente' }},

Enviamos em anexo o Documento de Venda para vosso registo.

Documento: {{ $summary['number'] }}
Origem: {{ $summary['source'] }}
Orcamento: {{ $summary['quote_number'] ?? '-' }}
Obra: {{ $summary['construction_site_code'] ?? '-' }}
Data emissao: {{ $summary['issue_date'] }}
Numero de linhas: {{ $summary['items_count'] }}
Total: {{ $summary['total'] }}

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

