{{ $subjectLine }}

Exmos. Senhores {{ $purchaseOrder->supplier_name_snapshot ?: 'Fornecedor' }},

Enviamos em anexo a nossa encomenda para processamento.

Documento: {{ $summary['number'] }}
Referencia RFQ: {{ $summary['rfq_number'] ?? '-' }}
N. documento fornecedor: {{ $summary['supplier_document_number'] ?? '-' }}
Data emissao: {{ $summary['issue_date'] }}
Numero de linhas: {{ $summary['items_count'] }}
Total: {{ $summary['total'] }}
@if (! empty($summary['assigned_user']))
Responsavel: {{ $summary['assigned_user'] }}
@endif

@if (! empty($messageBody))
Mensagem:
{{ $messageBody }}

@endif
Solicitamos confirmacao de rececao e prazo de entrega.

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
