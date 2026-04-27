@extends('layouts.admin')

@section('title', 'Ficha do cliente')
@section('page_title', 'Ficha do cliente')
@section('page_subtitle', 'Detalhe comercial e financeiro do cliente')

@section('page_actions')
    <a href="{{ route('admin.customers.index') }}" class="btn btn-phoenix-secondary btn-sm">Voltar</a>
    <a href="{{ route('admin.customers.edit', $customer->id) }}" class="btn btn-primary btn-sm">Editar</a>
    @can('company.quotes.create')
        <a href="{{ route('admin.quotes.create') }}" class="btn btn-phoenix-secondary btn-sm">Novo orcamento</a>
    @endcan
    @can('company.sales_documents.create')
        <a href="{{ route('admin.sales-documents.create', ['source' => \App\Models\SalesDocument::SOURCE_MANUAL]) }}" class="btn btn-phoenix-secondary btn-sm">Novo documento de venda</a>
    @endcan
    @can('company.customer_statement.view')
        <a href="{{ route('admin.customers.statement.show', $customer->id) }}" class="btn btn-phoenix-secondary btn-sm">Conta corrente</a>
    @endcan
    @if ($customer->email)
        <a href="mailto:{{ $customer->email }}" class="btn btn-phoenix-secondary btn-sm">Email</a>
    @endif
@endsection

@section('breadcrumbs')
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.customers.index') }}">Clientes</a></li>
        <li class="breadcrumb-item active" aria-current="page">{{ $customer->name }}</li>
    </ol>
@endsection

@section('content')
    @php
        $initial = mb_strtoupper(mb_substr(trim((string) $customer->name), 0, 1));
        $createdDate = $customer->created_at?->format('Y-m-d H:i') ?? '-';
        $lastSaleDate = !empty($kpis['last_sale_date']) ? \Illuminate\Support\Carbon::parse((string) $kpis['last_sale_date'])->format('Y-m-d') : '-';
    @endphp

    @if ($errors->any())
        <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
    @endif

    <div class="card mb-4 border-0 shadow-sm">
        <div class="card-body">
            <div class="d-flex flex-column flex-lg-row align-items-start align-items-lg-center justify-content-between gap-4">
                <div class="d-flex align-items-center gap-3">
                    <div class="avatar avatar-5xl">
                        @if ($customer->logo_path)
                            <img class="rounded-circle border border-translucent" src="{{ route('admin.customers.logo.show', $customer->id) }}" alt="{{ $customer->name }}">
                        @else
                            <div class="avatar-name rounded-circle bg-primary-subtle text-primary fw-bold">
                                <span>{{ $initial !== '' ? $initial : 'C' }}</span>
                            </div>
                        @endif
                    </div>
                    <div>
                        <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                            <h2 class="mb-0">{{ $customer->name }}</h2>
                            @if ($customer->is_active)
                                <span class="badge badge-phoenix badge-phoenix-success">Ativo</span>
                            @else
                                <span class="badge badge-phoenix badge-phoenix-secondary">Inativo</span>
                            @endif
                        </div>
                        <div class="text-body-tertiary fs-9 mb-2">
                            Ref: #{{ $customer->id }} &middot; Criado em {{ $createdDate }}
                        </div>
                        <div class="d-flex flex-wrap gap-3 fs-9">
                            <span><span class="text-body-tertiary">Email:</span> <span class="fw-semibold">{{ $customer->email ?: '-' }}</span></span>
                            <span><span class="text-body-tertiary">Telefone:</span> <span class="fw-semibold">{{ $customer->phone ?: ($customer->mobile ?: '-') }}</span></span>
                            <span><span class="text-body-tertiary">Tipo:</span> <span class="fw-semibold">{{ $customerTypeLabels[$customer->customer_type] ?? $customer->customer_type }}</span></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-2">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body">
                    <div class="text-body-tertiary fs-10">Total orcamentos</div>
                    <div class="h4 mb-0">{{ number_format((int) ($kpis['total_quotes'] ?? 0), 0, ',', '.') }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-2">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body">
                    <div class="text-body-tertiary fs-10">Total vendas</div>
                    <div class="h4 mb-0">{{ number_format((float) ($kpis['total_issued_sales'] ?? 0), 2, ',', '.') }} &euro;</div>
                    <div class="text-body-tertiary fs-10">{{ number_format((int) ($kpis['total_issued_documents'] ?? 0), 0, ',', '.') }} docs emitidos</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-2">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body">
                    <div class="text-body-tertiary fs-10">Valor em aberto</div>
                    <div class="h4 mb-0 text-danger">{{ number_format((float) ($kpis['open_amount'] ?? 0), 2, ',', '.') }} &euro;</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-2">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body">
                    <div class="text-body-tertiary fs-10">Total recebido</div>
                    <div class="h4 mb-0 text-success">{{ number_format((float) ($kpis['total_received'] ?? 0), 2, ',', '.') }} &euro;</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-2">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body">
                    <div class="text-body-tertiary fs-10">Ultima venda</div>
                    <div class="h5 mb-0">{{ $lastSaleDate }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-2">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body">
                    <div class="text-body-tertiary fs-10">N.o contactos</div>
                    <div class="h4 mb-0">{{ number_format((int) ($kpis['contacts_count'] ?? 0), 0, ',', '.') }}</div>
                </div>
            </div>
        </div>
    </div>

    <ul class="nav nav-tabs mb-3" id="customerDetailsTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="tab-summary" data-bs-toggle="tab" data-bs-target="#pane-summary" type="button" role="tab" aria-controls="pane-summary" aria-selected="true">Resumo</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-contacts" data-bs-toggle="tab" data-bs-target="#pane-contacts" type="button" role="tab" aria-controls="pane-contacts" aria-selected="false">Contactos</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-commercial" data-bs-toggle="tab" data-bs-target="#pane-commercial" type="button" role="tab" aria-controls="pane-commercial" aria-selected="false">Comercial</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-financial" data-bs-toggle="tab" data-bs-target="#pane-financial" type="button" role="tab" aria-controls="pane-financial" aria-selected="false">Financeiro</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-activity" data-bs-toggle="tab" data-bs-target="#pane-activity" type="button" role="tab" aria-controls="pane-activity" aria-selected="false">Atividade</button>
        </li>
    </ul>

    <div class="tab-content" id="customerDetailsTabsContent">
        <div class="tab-pane fade show active" id="pane-summary" role="tabpanel" aria-labelledby="tab-summary" tabindex="0">
            <div class="row g-4">
                <div class="col-12 col-xl-6">
                    <div class="card h-100">
                        <div class="card-header bg-body-tertiary">
                            <h5 class="mb-0">Dados principais</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-2"><span class="text-body-tertiary">NIF:</span> <span class="fw-semibold">{{ $customer->nif ?: '-' }}</span></div>
                            <div class="mb-2"><span class="text-body-tertiary">Email:</span> <span class="fw-semibold">{{ $customer->email ?: '-' }}</span></div>
                            <div class="mb-2"><span class="text-body-tertiary">Telefone:</span> <span class="fw-semibold">{{ $customer->phone ?: '-' }}</span></div>
                            <div class="mb-2"><span class="text-body-tertiary">Telemovel:</span> <span class="fw-semibold">{{ $customer->mobile ?: '-' }}</span></div>
                            <div class="mb-2"><span class="text-body-tertiary">Website:</span> <span class="fw-semibold">{{ $customer->website ?: '-' }}</span></div>
                            <div class="mb-2"><span class="text-body-tertiary">Condicao pagamento:</span> <span class="fw-semibold">{{ $customer->paymentTerm?->name ?: '-' }}</span></div>
                            <div class="mb-2"><span class="text-body-tertiary">Escalao preco:</span> <span class="fw-semibold">{{ $customer->priceTier?->name ?: 'Normal / default' }}</span></div>
                            <div><span class="text-body-tertiary">IVA habitual:</span> <span class="fw-semibold">{{ $customer->defaultVatRate ? $customer->defaultVatRate->name.' ('.number_format((float) $customer->defaultVatRate->rate, 2).'%)' : '-' }}</span></div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-xl-6">
                    <div class="card h-100">
                        <div class="card-header bg-body-tertiary">
                            <h5 class="mb-0">Morada</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-2">{{ $customer->address ?: '-' }}</div>
                            <div class="mb-2">{{ $customer->postal_code ?: '-' }}</div>
                            <div class="mb-2">{{ trim(($customer->locality ?? '').' / '.($customer->city ?? ''), ' /') ?: '-' }}</div>
                            <div>{{ $customer->country?->name ?: '-' }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-xl-6">
                    <div class="card h-100 border-warning-subtle">
                        <div class="card-header bg-body-tertiary d-flex align-items-center gap-2">
                            <span class="fas fa-note-sticky text-warning"></span>
                            <h5 class="mb-0">Notas internas</h5>
                        </div>
                        <div class="card-body">
                            @if ($customer->internal_notes)
                                <textarea class="form-control" rows="6" readonly>{{ $customer->internal_notes }}</textarea>
                            @else
                                <div class="text-body-tertiary">Sem notas internas.</div>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="col-12 col-xl-6">
                    <div class="card h-100">
                        <div class="card-header bg-body-tertiary">
                            <h5 class="mb-0">Notas comerciais e impressao</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <div class="text-body-tertiary fs-9 mb-1">Notas</div>
                                <div>{{ $customer->notes ?: 'Sem notas.' }}</div>
                            </div>
                            <div>
                                <div class="text-body-tertiary fs-9 mb-1">Comentarios de impressao</div>
                                <div>{{ $customer->print_comments ?: 'Sem comentarios.' }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="pane-contacts" role="tabpanel" aria-labelledby="tab-contacts" tabindex="0">
            <div class="card">
                <div class="card-header bg-body-tertiary d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Contactos do cliente</h5>
                    @if ($customer->customer_type === \App\Models\Customer::TYPE_COMPANY)
                        <a href="{{ route('admin.customers.contacts.create', $customer->id) }}" class="btn btn-primary btn-sm">Adicionar contacto</a>
                    @endif
                </div>
                <div class="card-body p-0">
                    @if ($customer->customer_type !== \App\Models\Customer::TYPE_COMPANY)
                        <div class="p-3 text-body-tertiary">Contactos apenas disponiveis para clientes do tipo empresa.</div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-sm fs-9 mb-0">
                                <thead class="bg-body-tertiary">
                                    <tr>
                                        <th class="ps-3">Nome</th>
                                        <th>Email</th>
                                        <th>Telefone</th>
                                        <th>Cargo</th>
                                        <th>Preferencial</th>
                                        <th>Observacoes</th>
                                        <th class="text-end pe-3">Acoes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($contacts as $contact)
                                        <tr>
                                            <td class="ps-3 fw-semibold">{{ $contact->name }}</td>
                                            <td>{{ $contact->email ?: '-' }}</td>
                                            <td>{{ $contact->phone ?: '-' }}</td>
                                            <td>{{ $contact->job_title ?: '-' }}</td>
                                            <td>
                                                @if ($contact->is_primary)
                                                    <span class="badge badge-phoenix badge-phoenix-success">Sim</span>
                                                @else
                                                    <span class="badge badge-phoenix badge-phoenix-secondary">Nao</span>
                                                @endif
                                            </td>
                                            <td>{{ \Illuminate\Support\Str::limit((string) ($contact->notes ?? ''), 60, '...') ?: '-' }}</td>
                                            <td class="text-end pe-3">
                                                <a href="{{ route('admin.customers.contacts.edit', [$customer->id, $contact->id]) }}" class="btn btn-phoenix-secondary btn-sm">Editar</a>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7" class="text-center py-4 text-body-tertiary">Sem contactos registados.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="pane-commercial" role="tabpanel" aria-labelledby="tab-commercial" tabindex="0">
            <div class="row g-4">
                <div class="col-12 col-xl-6">
                    <div class="card h-100">
                        <div class="card-header bg-body-tertiary d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Ultimos orcamentos</h5>
                            @can('company.quotes.view')
                                <a href="{{ route('admin.quotes.index', ['q' => $customer->name]) }}" class="btn btn-phoenix-secondary btn-sm">Ver todos</a>
                            @endcan
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-sm fs-9 mb-0">
                                    <thead class="bg-body-tertiary">
                                        <tr>
                                            <th class="ps-3">Numero</th>
                                            <th>Data</th>
                                            <th>Estado</th>
                                            <th>Total</th>
                                            <th class="text-end pe-3">Acoes</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($recentQuotes as $quote)
                                            <tr>
                                                <td class="ps-3 fw-semibold">{{ $quote->number }}</td>
                                                <td>{{ $quote->issue_date?->format('Y-m-d') ?? '-' }}</td>
                                                <td>
                                                    <span class="badge badge-phoenix {{ $quote->statusBadgeClass() }}">
                                                        {{ $quoteStatusLabels[$quote->status] ?? $quote->status }}
                                                    </span>
                                                </td>
                                                <td>{{ number_format((float) $quote->grand_total, 2, ',', '.') }} {{ $quote->currency }}</td>
                                                <td class="text-end pe-3">
                                                    <a href="{{ route('admin.quotes.show', $quote->id) }}" class="btn btn-phoenix-secondary btn-sm">Ficha</a>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="5" class="text-center py-4 text-body-tertiary">Sem orcamentos recentes.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-xl-6">
                    <div class="card h-100">
                        <div class="card-header bg-body-tertiary d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Ultimos Documentos de Venda</h5>
                            @can('company.sales_documents.view')
                                <a href="{{ route('admin.sales-documents.index', ['customer_id' => $customer->id]) }}" class="btn btn-phoenix-secondary btn-sm">Ver todos</a>
                            @endcan
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-sm fs-9 mb-0">
                                    <thead class="bg-body-tertiary">
                                        <tr>
                                            <th class="ps-3">Numero</th>
                                            <th>Data</th>
                                            <th>Origem</th>
                                            <th>Estado</th>
                                            <th>Total</th>
                                            <th class="text-end pe-3">Acoes</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($recentSalesDocuments as $document)
                                            <tr>
                                                <td class="ps-3 fw-semibold">{{ $document->number }}</td>
                                                <td>{{ $document->issue_date?->format('Y-m-d') ?? '-' }}</td>
                                                <td>{{ $salesDocumentSourceLabels[$document->source_type] ?? $document->source_type }}</td>
                                                <td>
                                                    <span class="badge badge-phoenix {{ $document->statusBadgeClass() }}">
                                                        {{ $salesDocumentStatusLabels[$document->status] ?? $document->status }}
                                                    </span>
                                                </td>
                                                <td>{{ number_format((float) $document->grand_total, 2, ',', '.') }} {{ $document->currency }}</td>
                                                <td class="text-end pe-3">
                                                    <a href="{{ route('admin.sales-documents.show', $document->id) }}" class="btn btn-phoenix-secondary btn-sm">Ficha</a>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="6" class="text-center py-4 text-body-tertiary">Sem Documentos de Venda recentes.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="pane-financial" role="tabpanel" aria-labelledby="tab-financial" tabindex="0">
            <div class="row g-4 mb-4">
                <div class="col-12 col-md-3">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="text-body-tertiary fs-10">Saldo atual</div>
                            <div class="h4 mb-0 {{ (float) $statementSummary['balance'] > 0 ? 'text-danger' : ((float) $statementSummary['balance'] < 0 ? 'text-success' : '') }}">
                                {{ number_format((float) $statementSummary['balance'], 2, ',', '.') }} &euro;
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-md-3">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="text-body-tertiary fs-10">Debitos</div>
                            <div class="h4 mb-0">{{ number_format((float) $statementSummary['total_debit'], 2, ',', '.') }} &euro;</div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-md-3">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="text-body-tertiary fs-10">Creditos</div>
                            <div class="h4 mb-0">{{ number_format((float) $statementSummary['total_credit'], 2, ',', '.') }} &euro;</div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-md-3">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="text-body-tertiary fs-10">Valor em aberto</div>
                            <div class="h4 mb-0 text-danger">{{ number_format((float) $statementSummary['open_amount'], 2, ',', '.') }} &euro;</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header bg-body-tertiary">
                    <h5 class="mb-0">Estado de pagamento dos Documentos de Venda emitidos</h5>
                </div>
                <div class="card-body">
                    <div class="d-flex flex-wrap gap-3">
                        <span class="badge badge-phoenix badge-phoenix-danger">Por pagar: {{ $paymentStatusCounts[\App\Models\SalesDocument::PAYMENT_STATUS_UNPAID] ?? 0 }}</span>
                        <span class="badge badge-phoenix badge-phoenix-warning">Parcial: {{ $paymentStatusCounts[\App\Models\SalesDocument::PAYMENT_STATUS_PARTIAL] ?? 0 }}</span>
                        <span class="badge badge-phoenix badge-phoenix-success">Pago: {{ $paymentStatusCounts[\App\Models\SalesDocument::PAYMENT_STATUS_PAID] ?? 0 }}</span>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header bg-body-tertiary d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Ultimos recibos</h5>
                    @can('company.customer_statement.view')
                        <a href="{{ route('admin.customers.statement.show', $customer->id) }}" class="btn btn-primary btn-sm">Ver conta corrente</a>
                    @endcan
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm fs-9 mb-0">
                            <thead class="bg-body-tertiary">
                                <tr>
                                    <th class="ps-3">Recibo</th>
                                    <th>Data</th>
                                    <th>Documento</th>
                                    <th>Modo pagamento</th>
                                    <th>Estado</th>
                                    <th>Valor</th>
                                    <th class="text-end pe-3">Acoes</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($recentReceipts as $receipt)
                                    <tr>
                                        <td class="ps-3 fw-semibold">{{ $receipt->number }}</td>
                                        <td>{{ $receipt->receipt_date?->format('Y-m-d') ?? '-' }}</td>
                                        <td>{{ $receipt->salesDocument?->number ?? '-' }}</td>
                                        <td>{{ $receipt->paymentMethod?->name ?? '-' }}</td>
                                        <td>
                                            <span class="badge badge-phoenix {{ $receipt->statusBadgeClass() }}">
                                                {{ $receiptStatusLabels[$receipt->status] ?? $receipt->status }}
                                            </span>
                                        </td>
                                        <td>{{ number_format((float) $receipt->amount, 2, ',', '.') }} &euro;</td>
                                        <td class="text-end pe-3">
                                            <a href="{{ route('admin.sales-document-receipts.show', $receipt->id) }}" class="btn btn-phoenix-secondary btn-sm">Ficha</a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center py-4 text-body-tertiary">Sem recibos recentes.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="pane-activity" role="tabpanel" aria-labelledby="tab-activity" tabindex="0">
            <div class="row g-4">
                <div class="col-12 col-xl-5">
                    <div class="card h-100">
                        <div class="card-header bg-body-tertiary">
                            <h5 class="mb-0">Datas relevantes</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-2"><span class="text-body-tertiary">Ultimo orcamento:</span> <span class="fw-semibold">{{ $activity['last_quote_date'] ? \Illuminate\Support\Carbon::parse((string) $activity['last_quote_date'])->format('Y-m-d') : '-' }}</span></div>
                            <div class="mb-2"><span class="text-body-tertiary">Ultimo Documento de Venda:</span> <span class="fw-semibold">{{ $activity['last_sale_date'] ? \Illuminate\Support\Carbon::parse((string) $activity['last_sale_date'])->format('Y-m-d') : '-' }}</span></div>
                            <div class="mb-2"><span class="text-body-tertiary">Ultimo recibo:</span> <span class="fw-semibold">{{ $activity['last_receipt_date'] ? \Illuminate\Support\Carbon::parse((string) $activity['last_receipt_date'])->format('Y-m-d') : '-' }}</span></div>
                            <div class="mb-2"><span class="text-body-tertiary">Criado em:</span> <span class="fw-semibold">{{ $activity['created_at'] ? \Illuminate\Support\Carbon::parse((string) $activity['created_at'])->format('Y-m-d H:i') : '-' }}</span></div>
                            <div><span class="text-body-tertiary">Atualizado em:</span> <span class="fw-semibold">{{ $activity['updated_at'] ? \Illuminate\Support\Carbon::parse((string) $activity['updated_at'])->format('Y-m-d H:i') : '-' }}</span></div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-xl-7">
                    <div class="card h-100">
                        <div class="card-header bg-body-tertiary">
                            <h5 class="mb-0">Acoes rapidas</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-flex flex-wrap gap-2 mb-3">
                                <a href="{{ route('admin.customers.edit', $customer->id) }}" class="btn btn-phoenix-secondary btn-sm">Editar cliente</a>
                                @if ($customer->customer_type === \App\Models\Customer::TYPE_COMPANY)
                                    <a href="{{ route('admin.customers.contacts.create', $customer->id) }}" class="btn btn-phoenix-secondary btn-sm">Adicionar contacto</a>
                                @endif
                                @can('company.quotes.create')
                                    <a href="{{ route('admin.quotes.create') }}" class="btn btn-phoenix-secondary btn-sm">Novo orcamento</a>
                                @endcan
                                @can('company.sales_documents.create')
                                    <a href="{{ route('admin.sales-documents.create', ['source' => \App\Models\SalesDocument::SOURCE_MANUAL]) }}" class="btn btn-phoenix-secondary btn-sm">Novo documento de venda</a>
                                @endcan
                                @can('company.customer_statement.view')
                                    <a href="{{ route('admin.customers.statement.show', $customer->id) }}" class="btn btn-primary btn-sm">Abrir conta corrente</a>
                                @endcan
                            </div>
                            <div class="alert alert-subtle-info mb-0" role="alert">
                                Atividade de emails e auditoria detalhada: a confirmar com as fontes de dados atualmente disponiveis no modulo de clientes.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

