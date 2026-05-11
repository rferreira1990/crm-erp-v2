@extends('layouts.admin')

@section('title', 'Ficha do fornecedor')
@section('page_title', 'Ficha do fornecedor')
@section('page_subtitle', 'Visao comercial e financeira')

@section('page_actions')
    <a href="{{ route('admin.suppliers.index') }}" class="btn btn-phoenix-secondary btn-sm">Voltar</a>
    @can('company.supplier_statement.view')
        <a href="{{ route('admin.suppliers.statement.show', [$supplier->id, 'statement_view' => 'open']) }}" class="btn btn-phoenix-secondary btn-sm">Conta corrente</a>
    @endcan
    @can('company.supplier_payments.view')
        <a href="{{ route('admin.supplier-payments.index', ['supplier_id' => $supplier->id]) }}" class="btn btn-phoenix-secondary btn-sm">Pagamentos</a>
    @endcan
    <a href="{{ route('admin.suppliers.edit', $supplier->id) }}" class="btn btn-primary btn-sm">Editar fornecedor</a>
@endsection

@section('breadcrumbs')
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.suppliers.index') }}">Fornecedores</a></li>
        <li class="breadcrumb-item active" aria-current="page">{{ $supplier->name }}</li>
    </ol>
@endsection

@section('content')
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="card mb-4">
        <div class="card-body">
            <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="flex-shrink-0 d-flex align-items-center justify-content-center rounded-circle bg-body-highlight" style="width: 76px; height: 76px;">
                        @if ($supplier->logo_path)
                            <img
                                src="{{ route('admin.suppliers.logo.show', $supplier->id) }}"
                                alt="{{ $supplier->name }}"
                                class="mw-100 mh-100 rounded-circle"
                                style="object-fit: cover;"
                            >
                        @else
                            <span class="fw-bold fs-5">{{ mb_substr($supplier->name, 0, 1) }}</span>
                        @endif
                    </div>
                    <div>
                        <h3 class="mb-1">{{ $supplier->name }}</h3>
                        <div class="d-flex flex-wrap gap-2 align-items-center">
                            <span class="text-body-secondary">NIF: {{ $supplier->nif ?: '-' }}</span>
                            @if ($supplier->is_active)
                                <span class="badge badge-phoenix badge-phoenix-success">Ativo</span>
                            @else
                                <span class="badge badge-phoenix badge-phoenix-secondary">Inativo</span>
                            @endif
                            <span class="badge badge-phoenix badge-phoenix-info">{{ $supplier->supplierTypeLabel() }}</span>
                        </div>
                        <div class="text-body-secondary mt-1">
                            {{ $supplier->email ?: '-' }} · {{ $supplier->phone ?: ($supplier->mobile ?: '-') }}
                        </div>
                    </div>
                </div>

                <div class="d-flex flex-wrap gap-2">
                    @can('company.purchase_orders.create')
                        <a href="{{ route('admin.purchase-orders.create') }}" class="btn btn-phoenix-secondary btn-sm">Nova encomenda</a>
                    @endcan
                    @can('company.purchase_documents.create')
                        <a href="{{ route('admin.purchase-documents.create') }}" class="btn btn-phoenix-secondary btn-sm">Novo Documento de Compra</a>
                    @endcan
                    <a href="{{ route('admin.suppliers.contacts.create', $supplier->id) }}" class="btn btn-primary btn-sm">Adicionar contacto</a>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-12 col-md-6 col-xl-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-body-tertiary fs-9">Saldo atual</div>
                    <div class="h4 mb-0 {{ $statementSummary['balance'] > 0 ? 'text-danger' : ($statementSummary['balance'] < 0 ? 'text-success' : '') }}">
                        {{ number_format((float) $statementSummary['balance'], 2, ',', '.') }} &euro;
                    </div>
                    <div class="text-body-secondary fs-10 mt-2">Positivo = valor em divida ao fornecedor</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-body-tertiary fs-9">Documentos em falta</div>
                    <div class="h4 mb-0">{{ $kpis['open_docs_count'] }}</div>
                    <div class="text-body-secondary fs-10 mt-2">Docs confirmados por pagar (parcial/nao pago)</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-body-tertiary fs-9">Vencido por pagar</div>
                    <div class="h4 mb-0 text-danger">{{ number_format((float) $kpis['overdue_open_amount'], 2, ',', '.') }} &euro;</div>
                    <div class="text-body-secondary fs-10 mt-2">Com due date ultrapassada</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-body-tertiary fs-9">Encomendas por receber</div>
                    <div class="h4 mb-0">{{ $kpis['pending_orders_count'] }}</div>
                    <div class="text-body-secondary fs-10 mt-2">{{ number_format((float) $kpis['pending_orders_value'], 2, ',', '.') }} &euro;</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-12 col-xxl-8">
            <div class="card mb-4">
                <div class="card-header bg-body-tertiary d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Documentos em falta de pagamento</h5>
                    <div class="d-flex flex-wrap gap-2">
                        @can('company.supplier_statement.view')
                            <a href="{{ route('admin.suppliers.statement.show', [$supplier->id, 'statement_view' => 'open']) }}" class="btn btn-phoenix-secondary btn-sm">Em aberto</a>
                            <a href="{{ route('admin.suppliers.statement.show', [$supplier->id, 'statement_view' => 'overdue']) }}" class="btn btn-phoenix-secondary btn-sm">Vencidas</a>
                            <a href="{{ route('admin.suppliers.statement.show', [$supplier->id, 'statement_view' => 'settled']) }}" class="btn btn-phoenix-secondary btn-sm">Liquidadas</a>
                        @endcan
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm fs-9 mb-0">
                            <thead class="bg-body-tertiary">
                                <tr>
                                    <th class="ps-3">Documento</th>
                                    <th>Emissao</th>
                                    <th>Vencimento</th>
                                    <th>Total</th>
                                    <th>Em aberto</th>
                                    <th>Estado pagamento</th>
                                    <th class="text-end pe-3">Acoes</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($openPurchaseDocuments as $document)
                                    <tr>
                                        <td class="ps-3 fw-semibold">
                                            <a href="{{ route('admin.purchase-documents.show', $document->id) }}">{{ $document->number }}</a>
                                        </td>
                                        <td>{{ optional($document->issue_date)->format('Y-m-d') ?? '-' }}</td>
                                        <td>
                                            @if ($document->due_date)
                                                <span class="{{ $document->is_overdue ? 'text-danger fw-semibold' : '' }}">{{ $document->due_date->format('Y-m-d') }}</span>
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td>{{ number_format((float) $document->grand_total, 2, ',', '.') }} {{ $document->currency }}</td>
                                        <td class="fw-semibold {{ $document->is_overdue ? 'text-danger' : '' }}">
                                            {{ number_format((float) $document->open_amount, 2, ',', '.') }} {{ $document->currency }}
                                        </td>
                                        <td>
                                            <span class="badge badge-phoenix {{ $document->paymentStatusBadgeClass() }}">{{ $document->paymentStatusLabel() }}</span>
                                        </td>
                                        <td class="text-end pe-3">
                                            <div class="d-inline-flex gap-1">
                                                <a href="{{ route('admin.purchase-documents.show', $document->id) }}" class="btn btn-phoenix-secondary btn-sm">Ficha</a>
                                                @can('company.supplier_payments.create')
                                                    <a href="{{ route('admin.supplier-payments.create', $document->id) }}" class="btn btn-primary btn-sm">Registar pagamento</a>
                                                @endcan
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center py-4 text-body-tertiary">Sem documentos pendentes.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header bg-body-tertiary d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Encomendas por receber</h5>
                    @can('company.purchase_orders.view')
                        <a href="{{ route('admin.purchase-orders.index', ['supplier_id' => $supplier->id]) }}" class="btn btn-phoenix-secondary btn-sm">Ver encomendas</a>
                    @endcan
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm fs-9 mb-0">
                            <thead class="bg-body-tertiary">
                                <tr>
                                    <th class="ps-3">Encomenda</th>
                                    <th>Data</th>
                                    <th>Entrega prevista</th>
                                    <th>Total</th>
                                    <th>Linhas</th>
                                    <th class="text-end pe-3">Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($pendingPurchaseOrders as $purchaseOrder)
                                    <tr>
                                        <td class="ps-3 fw-semibold">
                                            <a href="{{ route('admin.purchase-orders.show', $purchaseOrder->id) }}">{{ $purchaseOrder->number }}</a>
                                        </td>
                                        <td>{{ optional($purchaseOrder->issue_date)->format('Y-m-d') ?? '-' }}</td>
                                        <td>{{ optional($purchaseOrder->expected_delivery_date)->format('Y-m-d') ?? '-' }}</td>
                                        <td>{{ number_format((float) $purchaseOrder->grand_total, 2, ',', '.') }} {{ $purchaseOrder->currency }}</td>
                                        <td>{{ $purchaseOrder->items_count }}</td>
                                        <td class="text-end pe-3">
                                            <span class="badge badge-phoenix {{ $purchaseOrder->statusBadgeClass() }}">{{ $purchaseOrder->statusLabel() }}</span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center py-4 text-body-tertiary">Sem encomendas pendentes de rececao.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-xxl-4">
            <div class="card mb-4">
                <div class="card-header bg-body-tertiary">
                    <h5 class="mb-0">Resumo financeiro</h5>
                </div>
                <div class="card-body">
                    <div class="mb-2"><span class="text-body-tertiary">Valor a pagar:</span> <span class="fw-semibold">{{ number_format((float) $statementSummary['total_payable'], 2, ',', '.') }} &euro;</span></div>
                    <div class="mb-2"><span class="text-body-tertiary">Valor pago:</span> <span class="fw-semibold">{{ number_format((float) $statementSummary['total_paid'], 2, ',', '.') }} &euro;</span></div>
                    <div class="mb-0"><span class="text-body-tertiary">Saldo atual:</span> <span class="fw-bold {{ $statementSummary['balance'] > 0 ? 'text-danger' : ($statementSummary['balance'] < 0 ? 'text-success' : '') }}">{{ number_format((float) $statementSummary['balance'], 2, ',', '.') }} &euro;</span></div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header bg-body-tertiary">
                    <h5 class="mb-0">Performance de cotacoes</h5>
                </div>
                <div class="card-body">
                    <div class="mb-2"><span class="text-body-tertiary">Convites RFQ:</span> <span class="fw-semibold">{{ $kpis['rfq_invites_total'] }}</span></div>
                    <div class="mb-2"><span class="text-body-tertiary">Respondidas:</span> <span class="fw-semibold">{{ $kpis['rfq_responded_count'] }}</span></div>
                    <div class="mb-2"><span class="text-body-tertiary">Sem resposta / pendentes:</span> <span class="fw-semibold">{{ $kpis['rfq_awaiting_count'] }}</span></div>
                    <div class="mb-2"><span class="text-body-tertiary">Recusadas:</span> <span class="fw-semibold">{{ $kpis['rfq_declined_count'] }}</span></div>
                    <div class="mb-2"><span class="text-body-tertiary">Ganhas (com encomenda):</span> <span class="fw-semibold">{{ $kpis['rfq_awarded_count'] }}</span></div>
                    <div class="mb-2"><span class="text-body-tertiary">Taxa resposta:</span> <span class="fw-semibold">{{ number_format((float) $kpis['rfq_response_rate'], 1, ',', '.') }}%</span></div>
                    <div class="mb-0"><span class="text-body-tertiary">Taxa conversao:</span> <span class="fw-semibold">{{ number_format((float) $kpis['rfq_conversion_rate'], 1, ',', '.') }}%</span></div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header bg-body-tertiary">
                    <h5 class="mb-0">Condicoes financeiras</h5>
                </div>
                <div class="card-body">
                    <div class="mb-2"><span class="text-body-tertiary">Condicao pagamento:</span> <span class="fw-semibold">{{ $supplier->paymentTerm?->name ?: '-' }}</span></div>
                    <div class="mb-2"><span class="text-body-tertiary">Modo pagamento:</span> <span class="fw-semibold">{{ $supplier->defaultPaymentMethod?->name ?: '-' }}</span></div>
                    <div class="mb-2"><span class="text-body-tertiary">IVA habitual:</span> <span class="fw-semibold">{{ $supplier->defaultVatRate ? $supplier->defaultVatRate->name.' ('.number_format((float) $supplier->defaultVatRate->rate, 2).'%)' : '-' }}</span></div>
                    <div class="mb-2"><span class="text-body-tertiary">Banco:</span> <span class="fw-semibold">{{ $supplier->bank_name ?: '-' }}</span></div>
                    <div class="mb-2"><span class="text-body-tertiary">BIC / SWIFT:</span> <span class="fw-semibold">{{ $supplier->bic_swift ?: '-' }}</span></div>
                    <div class="mb-0"><span class="text-body-tertiary">IBAN:</span> <span class="fw-semibold">{{ $supplier->iban ?: '-' }}</span></div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header bg-body-tertiary">
                    <h5 class="mb-0">Morada</h5>
                </div>
                <div class="card-body">
                    <div>{{ $supplier->address ?: '-' }}</div>
                    <div>{{ $supplier->postal_code ?: '-' }}</div>
                    <div>{{ $supplier->locality ?: '-' }}{{ $supplier->city ? ' / '.$supplier->city : '' }}</div>
                    <div>{{ $supplier->country?->name ?: '-' }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-12 col-xl-6">
            <div class="card h-100">
                <div class="card-header bg-body-tertiary d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Ultimos Documentos de Compra</h5>
                    @can('company.purchase_documents.view')
                        <a href="{{ route('admin.purchase-documents.index', ['supplier_id' => $supplier->id]) }}" class="btn btn-phoenix-secondary btn-sm">Ver todos</a>
                    @endcan
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm fs-9 mb-0">
                            <thead class="bg-body-tertiary">
                                <tr>
                                    <th class="ps-3">Numero</th>
                                    <th>Data</th>
                                    <th>Total</th>
                                    <th class="pe-3 text-end">Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($recentPurchaseDocuments as $purchaseDocument)
                                    <tr>
                                        <td class="ps-3"><a href="{{ route('admin.purchase-documents.show', $purchaseDocument->id) }}">{{ $purchaseDocument->number }}</a></td>
                                        <td>{{ optional($purchaseDocument->issue_date)->format('Y-m-d') ?? '-' }}</td>
                                        <td>{{ number_format((float) $purchaseDocument->grand_total, 2, ',', '.') }} {{ $purchaseDocument->currency }}</td>
                                        <td class="pe-3 text-end"><span class="badge badge-phoenix {{ $purchaseDocument->statusBadgeClass() }}">{{ $purchaseDocument->statusLabel() }}</span></td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center py-3 text-body-tertiary">Sem documentos.</td>
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
                    <h5 class="mb-0">Ultimos Pagamentos</h5>
                    @can('company.supplier_payments.view')
                        <a href="{{ route('admin.supplier-payments.index', ['supplier_id' => $supplier->id]) }}" class="btn btn-phoenix-secondary btn-sm">Ver todos</a>
                    @endcan
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm fs-9 mb-0">
                            <thead class="bg-body-tertiary">
                                <tr>
                                    <th class="ps-3">Numero</th>
                                    <th>Data</th>
                                    <th>Valor</th>
                                    <th class="pe-3 text-end">Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($recentSupplierPayments as $supplierPayment)
                                    <tr>
                                        <td class="ps-3"><a href="{{ route('admin.supplier-payments.show', $supplierPayment->id) }}">{{ $supplierPayment->number }}</a></td>
                                        <td>{{ optional($supplierPayment->payment_date)->format('Y-m-d') ?? '-' }}</td>
                                        <td>{{ number_format((float) $supplierPayment->amount, 2, ',', '.') }} &euro;</td>
                                        <td class="pe-3 text-end"><span class="badge badge-phoenix {{ $supplierPayment->statusBadgeClass() }}">{{ $supplierPayment->statusLabel() }}</span></td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center py-3 text-body-tertiary">Sem pagamentos.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-12 col-xl-6">
            <div class="card h-100">
                <div class="card-header bg-body-tertiary">
                    <h5 class="mb-0">Atividade RFQ / Cotacoes</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm fs-9 mb-0">
                            <thead class="bg-body-tertiary">
                                <tr>
                                    <th class="ps-3">RFQ</th>
                                    <th>Estado convite</th>
                                    <th>Cotacao</th>
                                    <th class="pe-3 text-end">Acoes</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($recentRfqActivity as $rfqInvite)
                                    <tr>
                                        <td class="ps-3">
                                            @if ($rfqInvite->supplierQuoteRequest)
                                                <a href="{{ route('admin.rfqs.show', $rfqInvite->supplierQuoteRequest->id) }}">{{ $rfqInvite->supplierQuoteRequest->number }}</a>
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td>{{ $rfqInvite->status }}</td>
                                        <td>
                                            @if ($rfqInvite->supplierQuote)
                                                {{ number_format((float) $rfqInvite->supplierQuote->grand_total, 2, ',', '.') }} &euro;
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td class="text-end pe-3">
                                            @if ($rfqInvite->supplierQuoteRequest)
                                                <a href="{{ route('admin.rfqs.show', $rfqInvite->supplierQuoteRequest->id) }}" class="btn btn-phoenix-secondary btn-sm">Ver RFQ</a>
                                            @else
                                                -
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center py-3 text-body-tertiary">Sem atividade recente de RFQ.</td>
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
                <div class="card-header bg-body-tertiary">
                    <h5 class="mb-0">Notas</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <div class="text-body-tertiary fs-9">Notas gerais</div>
                        <p class="mb-0">{{ $supplier->notes ?: 'Sem notas.' }}</p>
                    </div>
                    <div>
                        <div class="text-body-tertiary fs-9">Notas de pagamento</div>
                        <p class="mb-0">{{ $supplier->payment_notes ?: 'Sem notas.' }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header bg-body-tertiary d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Contactos do fornecedor</h5>
            <a href="{{ route('admin.suppliers.contacts.create', $supplier->id) }}" class="btn btn-primary btn-sm">Adicionar contacto</a>
        </div>
        <div class="card-body p-0">
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
                        @forelse ($supplier->contacts as $contact)
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
                                    <a href="{{ route('admin.suppliers.contacts.edit', [$supplier->id, $contact->id]) }}" class="btn btn-phoenix-secondary btn-sm">Editar</a>
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
        </div>
    </div>
@endsection
