@extends('layouts.admin')

@section('title', 'Ficha do Documento de Compra')
@section('page_title', 'Ficha do Documento de Compra')
@section('page_subtitle', $document->number)

@section('page_actions')
    <a href="{{ route('admin.purchase-documents.index') }}" class="btn btn-phoenix-secondary btn-sm">Voltar</a>
    @can('company.purchase_documents.update')
        @if ($document->isEditableDraft())
            <a href="{{ route('admin.purchase-documents.edit', $document->id) }}" class="btn btn-phoenix-secondary btn-sm">Editar</a>
        @endif
    @endcan
    @can('company.purchase_documents.confirm')
        @if ($document->isDraft())
            <form method="POST" action="{{ route('admin.purchase-documents.confirm', $document->id) }}" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-primary btn-sm">Confirmar</button>
            </form>
        @endif
    @endcan
    @can('company.purchase_documents.cancel')
        @if (in_array($document->status, [\App\Models\PurchaseDocument::STATUS_DRAFT, \App\Models\PurchaseDocument::STATUS_CONFIRMED], true) && (int) $stockMovementsCount === 0)
            <form method="POST" action="{{ route('admin.purchase-documents.cancel', $document->id) }}" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-phoenix-danger btn-sm">Cancelar</button>
            </form>
        @endif
    @endcan
    @can('company.supplier_payments.create')
        @if ($document->canReceivePayments() && $openAmount > 0)
            <a href="{{ route('admin.supplier-payments.create', $document->id) }}" class="btn btn-primary btn-sm">Registar pagamento</a>
        @endif
    @endcan
@endsection

@section('breadcrumbs')
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.purchase-documents.index') }}">Documentos de Compra</a></li>
        <li class="breadcrumb-item active" aria-current="page">{{ $document->number }}</li>
    </ol>
@endsection

@section('content')
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
    @endif

    @php
        $isOverdue = $document->due_date && $openAmount > 0 && $document->due_date->isPast();
        $issuedPaymentsCount = $document->payments->where('status', \App\Models\SupplierPayment::STATUS_ISSUED)->count();
    @endphp

    <div class="card mb-4">
        <div class="card-body">
            <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="flex-shrink-0 d-flex align-items-center justify-content-center rounded-circle bg-body-highlight" style="width: 72px; height: 72px;">
                        <span class="fw-bold fs-4">{{ mb_substr((string) ($document->supplier?->name ?? 'F'), 0, 1) }}</span>
                    </div>
                    <div>
                        <h3 class="mb-1">{{ $document->number }}</h3>
                        <div class="d-flex flex-wrap gap-2 align-items-center">
                            <span class="badge badge-phoenix {{ $document->statusBadgeClass() }}">{{ $statusLabels[$document->status] ?? $document->status }}</span>
                            <span class="badge badge-phoenix {{ $document->paymentStatusBadgeClass() }}">{{ $paymentStatusLabels[$document->payment_status] ?? $document->paymentStatusLabel() }}</span>
                            @if ($isOverdue)
                                <span class="badge badge-phoenix badge-phoenix-danger">Vencido</span>
                            @endif
                        </div>
                        <div class="text-body-secondary mt-1">
                            {{ $document->supplier?->name ?? '-' }} · {{ $document->supplier?->email ?? '-' }}
                        </div>
                    </div>
                </div>

                <div class="d-flex flex-wrap gap-2">
                    @can('company.supplier_payments.create')
                        @if ($document->canReceivePayments() && $openAmount > 0)
                            <a href="{{ route('admin.supplier-payments.create', $document->id) }}" class="btn btn-primary btn-sm">Registar pagamento</a>
                        @endif
                    @endcan
                    @if ($document->purchaseOrder)
                        <a href="{{ route('admin.purchase-orders.show', $document->purchaseOrder->id) }}" class="btn btn-phoenix-secondary btn-sm">Ver encomenda</a>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-12 col-md-6 col-xl-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-body-tertiary fs-9">Total documento</div>
                    <div class="h4 mb-0">{{ number_format((float) $document->grand_total, 2, ',', '.') }} {{ $document->currency }}</div>
                    <div class="text-body-secondary fs-10 mt-2">Valor total confirmado</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-body-tertiary fs-9">Total pago</div>
                    <div class="h4 mb-0 text-success">{{ number_format((float) $totalPaid, 2, ',', '.') }} {{ $document->currency }}</div>
                    <div class="text-body-secondary fs-10 mt-2">{{ $issuedPaymentsCount }} pagamento(s) emitidos</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-body-tertiary fs-9">Valor em aberto</div>
                    <div class="h4 mb-0 {{ $openAmount > 0 ? 'text-danger' : 'text-success' }}">{{ number_format((float) $openAmount, 2, ',', '.') }} {{ $document->currency }}</div>
                    <div class="text-body-secondary fs-10 mt-2">{{ $isOverdue ? 'Documento vencido' : 'Situacao financeira atual' }}</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-body-tertiary fs-9">Movimentos de stock</div>
                    <div class="h4 mb-0">{{ (int) $stockMovementsCount }}</div>
                    <div class="text-body-secondary fs-10 mt-2">{{ $document->isConfirmed() ? 'Gerados na confirmacao' : 'Sem integracao em rascunho' }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-12 col-xxl-8">
            <div class="card mb-4">
                <div class="card-header bg-body-tertiary">
                    <h5 class="mb-0">Linhas do documento</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm fs-9 mb-0">
                            <thead class="bg-body-tertiary">
                                <tr>
                                    <th class="ps-3">#</th>
                                    <th>Artigo</th>
                                    <th>Descricao</th>
                                    <th>Unid.</th>
                                    <th>Qtd.</th>
                                    <th>P. Unit.</th>
                                    <th>Desc. %</th>
                                    <th>Taxa %</th>
                                    <th>Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($document->items as $item)
                                    <tr>
                                        <td class="ps-3">{{ $item->line_order }}</td>
                                        <td>{{ $item->article?->code ?? '-' }}</td>
                                        <td>{{ $item->description }}</td>
                                        <td>{{ $item->unit_name_snapshot ?: '-' }}</td>
                                        <td>{{ number_format((float) $item->quantity, 3, ',', '.') }}</td>
                                        <td>{{ number_format((float) $item->unit_price, 4, ',', '.') }}</td>
                                        <td>{{ number_format((float) ($item->discount_percent ?? 0), 2, ',', '.') }}</td>
                                        <td>{{ number_format((float) ($item->tax_rate ?? 0), 2, ',', '.') }}</td>
                                        <td>{{ number_format((float) $item->line_total, 2, ',', '.') }} {{ $document->currency }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="text-center py-4 text-body-tertiary">Sem linhas.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header bg-body-tertiary d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Pagamentos associados</h5>
                    @can('company.supplier_payments.create')
                        @if ($document->canReceivePayments() && $openAmount > 0)
                            <a href="{{ route('admin.supplier-payments.create', $document->id) }}" class="btn btn-primary btn-sm">Registar pagamento</a>
                        @endif
                    @endcan
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm fs-9 mb-0">
                            <thead class="bg-body-tertiary">
                                <tr>
                                    <th class="ps-3">Pagamento</th>
                                    <th>Data</th>
                                    <th>Modo pagamento</th>
                                    <th>Valor</th>
                                    <th>Estado</th>
                                    <th class="text-end pe-3">Acoes</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($document->payments as $payment)
                                    <tr>
                                        <td class="ps-3 fw-semibold">{{ $payment->number }}</td>
                                        <td>{{ optional($payment->payment_date)->format('Y-m-d') ?? '-' }}</td>
                                        <td>{{ $payment->paymentMethod?->name ?? '-' }}</td>
                                        <td>{{ number_format((float) $payment->amount, 2, ',', '.') }} {{ $document->currency }}</td>
                                        <td>
                                            <span class="badge badge-phoenix {{ $payment->statusBadgeClass() }}">{{ $paymentStatuses[$payment->status] ?? $payment->status }}</span>
                                        </td>
                                        <td class="text-end pe-3">
                                            <a href="{{ route('admin.supplier-payments.show', $payment->id) }}" class="btn btn-phoenix-secondary btn-sm">Ficha</a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center py-4 text-body-tertiary">Sem pagamentos registados.</td>
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
                    <div class="mb-2"><span class="text-body-tertiary">Subtotal:</span> <span class="fw-semibold">{{ number_format((float) $document->subtotal, 2, ',', '.') }} {{ $document->currency }}</span></div>
                    <div class="mb-2"><span class="text-body-tertiary">Desconto:</span> <span class="fw-semibold">{{ number_format((float) $document->discount_total, 2, ',', '.') }} {{ $document->currency }}</span></div>
                    <div class="mb-2"><span class="text-body-tertiary">Impostos:</span> <span class="fw-semibold">{{ number_format((float) $document->tax_total, 2, ',', '.') }} {{ $document->currency }}</span></div>
                    <div class="mb-2"><span class="text-body-tertiary">Total documento:</span> <span class="fw-bold">{{ number_format((float) $document->grand_total, 2, ',', '.') }} {{ $document->currency }}</span></div>
                    <div class="mb-2"><span class="text-body-tertiary">Total pago:</span> <span class="fw-semibold">{{ number_format((float) $totalPaid, 2, ',', '.') }} {{ $document->currency }}</span></div>
                    <div class="mb-0"><span class="text-body-tertiary">Valor em aberto:</span> <span class="fw-semibold {{ $openAmount > 0 ? 'text-danger' : 'text-success' }}">{{ number_format((float) $openAmount, 2, ',', '.') }} {{ $document->currency }}</span></div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header bg-body-tertiary">
                    <h5 class="mb-0">Contexto e rastreabilidade</h5>
                </div>
                <div class="card-body">
                    <div class="mb-2"><span class="text-body-tertiary">Fornecedor:</span> <span class="fw-semibold">{{ $document->supplier?->name ?? '-' }}</span></div>
                    <div class="mb-2"><span class="text-body-tertiary">NIF:</span> <span class="fw-semibold">{{ $document->supplier?->nif ?: '-' }}</span></div>
                    <div class="mb-2"><span class="text-body-tertiary">Doc. fornecedor:</span> <span class="fw-semibold">{{ $document->supplier_document_number ?: '-' }}</span></div>
                    <div class="mb-2"><span class="text-body-tertiary">Data emissao:</span> <span class="fw-semibold">{{ optional($document->issue_date)->format('Y-m-d') ?? '-' }}</span></div>
                    <div class="mb-2"><span class="text-body-tertiary">Data vencimento:</span> <span class="fw-semibold">{{ optional($document->due_date)->format('Y-m-d') ?? '-' }}</span></div>
                    <div class="mb-2"><span class="text-body-tertiary">Criado por:</span> <span class="fw-semibold">{{ $document->creator?->name ?? '-' }}</span></div>
                    <div class="mb-2"><span class="text-body-tertiary">Atualizado por:</span> <span class="fw-semibold">{{ $document->updater?->name ?? '-' }}</span></div>
                    <div class="mb-2"><span class="text-body-tertiary">Cancelado por:</span> <span class="fw-semibold">{{ $document->canceller?->name ?? '-' }}</span></div>
                    <div class="mb-0"><span class="text-body-tertiary">Confirmado em:</span> <span class="fw-semibold">{{ optional($document->confirmed_at)->format('Y-m-d H:i') ?? '-' }}</span></div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header bg-body-tertiary">
                    <h5 class="mb-0">Integracao stock</h5>
                </div>
                <div class="card-body">
                    @if ($document->isConfirmed())
                        <div class="mb-2"><span class="badge badge-phoenix badge-phoenix-success">Movimenta stock</span></div>
                        <div class="text-body-secondary">Movimentos gerados na confirmacao: {{ (int) $stockMovementsCount }}</div>
                    @else
                        <div class="mb-2"><span class="badge badge-phoenix badge-phoenix-secondary">Sem movimento em rascunho</span></div>
                        <div class="text-body-secondary">O stock so e integrado quando confirmar o documento.</div>
                    @endif
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header bg-body-tertiary">
                    <h5 class="mb-0">Notas</h5>
                </div>
                <div class="card-body">{{ $document->notes ?: '-' }}</div>
            </div>
        </div>
    </div>
@endsection
