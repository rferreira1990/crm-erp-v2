{{ $subjectLine }}

Exmos. Senhores {{ $rfqSupplier->supplier_name ?: 'Fornecedor' }},

Enviamos em anexo o nosso pedido de cotacao para analise.

Documento: {{ $summary['number'] }}
Titulo: {{ $summary['title'] }}
Data emissao: {{ $summary['issue_date'] }}
Prazo resposta: {{ $summary['response_deadline'] }}
Numero de linhas: {{ $summary['items_count'] }}
@if (! empty($summary['assigned_user']))
Responsavel: {{ $summary['assigned_user'] }}
@endif

@if (! empty($messageBody))
Mensagem:
{{ $messageBody }}

@endif
Aguardamos o vosso melhor preco e prazo de entrega.

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

