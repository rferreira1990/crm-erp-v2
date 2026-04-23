@extends('layouts.admin')

@section('title', 'Ficha da encomenda')
@section('page_title', 'Ficha da encomenda')
@section('page_subtitle', $purchaseOrder->number)

@section('page_actions')
    <a href="{{ route('admin.purchase-orders.index') }}" class="btn btn-phoenix-secondary btn-sm">Voltar</a>
    @if ($purchaseOrder->canReceiveMaterial() && auth()->user()->can('company.purchase_order_receipts.create'))
        <a href="{{ route('admin.purchase-order-receipts.create', $purchaseOrder->id) }}" class="btn btn-primary btn-sm">Registar rececao</a>
    @endif
    <form method="POST" action="{{ route('admin.purchase-orders.pdf.generate', $purchaseOrder->id) }}" class="d-inline">
        @csrf
        <button type="submit" class="btn btn-phoenix-secondary btn-sm">Gerar PDF</button>
    </form>
    @if ($purchaseOrder->pdf_path)
        <a href="{{ route('admin.purchase-orders.pdf.download', $purchaseOrder->id) }}" class="btn btn-phoenix-secondary btn-sm">Download PDF</a>
    @endif
@endsection

@section('breadcrumbs')
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.purchase-orders.index') }}">Encomendas a fornecedor</a></li>
        <li class="breadcrumb-item active" aria-current="page">{{ $purchaseOrder->number }}</li>
    </ol>
@endsection

@section('content')
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
    @endif

    <div class="row g-4 mb-4">
        <div class="col-12 col-xxl-8">
            <div class="card mb-4">
                <div class="card-header bg-body-tertiary d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">{{ $purchaseOrder->number }}</h5>
                    <span class="badge badge-phoenix {{ $purchaseOrder->statusBadgeClass() }}">{{ $statusLabels[$purchaseOrder->status] ?? $purchaseOrder->status }}</span>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12 col-md-4">
                            <div class="text-body-tertiary fs-9">Fornecedor</div>
                            <div class="fw-semibold">{{ $purchaseOrder->supplier_name_snapshot }}</div>
                        </div>
                        <div class="col-12 col-md-4">
                            <div class="text-body-tertiary fs-9">Email fornecedor</div>
                            <div class="fw-semibold">{{ $purchaseOrder->supplier_email_snapshot ?: '-' }}</div>
                        </div>
                        <div class="col-12 col-md-4">
                            <div class="text-body-tertiary fs-9">Telefone fornecedor</div>
                            <div class="fw-semibold">{{ $purchaseOrder->supplier_phone_snapshot ?: '-' }}</div>
                        </div>
                        <div class="col-12 col-md-6">
                            <div class="text-body-tertiary fs-9">Morada fornecedor</div>
                            <div class="fw-semibold">{{ $purchaseOrder->supplier_address_snapshot ?: '-' }}</div>
                        </div>
                        <div class="col-12 col-md-3">
                            <div class="text-body-tertiary fs-9">Data emissao</div>
                            <div class="fw-semibold">{{ optional($purchaseOrder->issue_date)->format('Y-m-d') ?? '-' }}</div>
                        </div>
                        <div class="col-12 col-md-3">
                            <div class="text-body-tertiary fs-9">Entrega prevista</div>
                            <div class="fw-semibold">{{ optional($purchaseOrder->expected_delivery_date)->format('Y-m-d') ?? '-' }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header bg-body-tertiary">
                    <h5 class="mb-0">Linhas da encomenda</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm fs-9 mb-0">
                            <thead class="bg-body-tertiary">
                                <tr>
                                    <th class="ps-3">#</th>
                                    <th>Codigo</th>
                                    <th>Descricao</th>
                                    <th>Unid.</th>
                                    <th>Qtd.</th>
                                    <th>P. Unit.</th>
                                    <th>Desc. %</th>
                                    <th>IVA %</th>
                                    <th>Total linha</th>
                                    <th>Qtd. recebida</th>
                                    <th>Qtd. em falta</th>
                                    <th class="pe-3">Origem award item</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($purchaseOrder->items as $item)
                                    @php
                                        $totalReceived = $item->totalReceivedQuantity();
                                        $remaining = $item->remainingQuantity();
                                    @endphp
                                    <tr>
                                        <td class="ps-3">{{ $item->line_order }}</td>
                                        <td>{{ $item->article_code ?: '-' }}</td>
                                        <td>{{ $item->description }}</td>
                                        <td>{{ $item->unit_name ?: '-' }}</td>
                                        <td>{{ number_format((float) $item->quantity, 3, ',', '.') }}</td>
                                        <td>{{ number_format((float) $item->unit_price, 4, ',', '.') }}</td>
                                        <td>{{ number_format((float) $item->discount_percent, 2, ',', '.') }}</td>
                                        <td>{{ number_format((float) $item->vat_percent, 2, ',', '.') }}</td>
                                        <td>{{ number_format((float) $item->line_total, 2, ',', '.') }} {{ $purchaseOrder->currency }}</td>
                                        <td>{{ number_format($totalReceived, 3, ',', '.') }}</td>
                                        <td>{{ number_format($remaining, 3, ',', '.') }}</td>
                                        <td class="pe-3">{{ $item->source_award_item_id ?: '-' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="12" class="text-center py-4 text-body-tertiary">Sem linhas.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header bg-body-tertiary">
                    <h5 class="mb-0">Totais</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12 col-md-4"><span class="text-body-tertiary fs-9">Subtotal:</span> <span class="fw-semibold">{{ number_format((float) $purchaseOrder->subtotal, 2, ',', '.') }} {{ $purchaseOrder->currency }}</span></div>
                        <div class="col-12 col-md-4"><span class="text-body-tertiary fs-9">Desconto:</span> <span class="fw-semibold">{{ number_format((float) $purchaseOrder->discount_total, 2, ',', '.') }} {{ $purchaseOrder->currency }}</span></div>
                        <div class="col-12 col-md-4"><span class="text-body-tertiary fs-9">IVA:</span> <span class="fw-semibold">{{ number_format((float) $purchaseOrder->tax_total, 2, ',', '.') }} {{ $purchaseOrder->currency }}</span></div>
                        <div class="col-12 col-md-4"><span class="text-body-tertiary fs-9">Portes:</span> <span class="fw-semibold">{{ number_format((float) $purchaseOrder->shipping_total, 2, ',', '.') }} {{ $purchaseOrder->currency }}</span></div>
                        <div class="col-12 col-md-8"><span class="text-body-tertiary fs-9">Total final:</span> <span class="fw-bold">{{ number_format((float) $purchaseOrder->grand_total, 2, ',', '.') }} {{ $purchaseOrder->currency }}</span></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-xxl-4">
            <div class="card mb-4">
                <div class="card-header bg-body-tertiary">
                    <h5 class="mb-0">Estado da encomenda</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <div class="text-body-tertiary fs-9">Estado atual</div>
                        <div class="fw-semibold">{{ $statusLabels[$purchaseOrder->status] ?? $purchaseOrder->status }}</div>
                    </div>
                    @if ($purchaseOrder->nextAllowedStatuses() !== [] && auth()->user()->can('company.purchase_orders.update'))
                        <div class="d-grid gap-2">
                            @foreach ($purchaseOrder->nextAllowedStatuses() as $nextStatus)
                                <form method="POST" action="{{ route('admin.purchase-orders.status.change', $purchaseOrder->id) }}">
                                    @csrf
                                    <input type="hidden" name="status" value="{{ $nextStatus }}">
                                    <button type="submit" class="btn btn-phoenix-secondary btn-sm w-100">
                                        Marcar como {{ $statusLabels[$nextStatus] ?? $nextStatus }}
                                    </button>
                                </form>
                            @endforeach
                        </div>
                    @else
                        <div class="text-body-tertiary">Sem transicoes disponiveis.</div>
                    @endif
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header bg-body-tertiary">
                    <h5 class="mb-0">Rastreabilidade</h5>
                </div>
                <div class="card-body">
                    <div class="mb-2">
                        <div class="text-body-tertiary fs-9">RFQ origem</div>
                        <div class="fw-semibold">
                            @if ($purchaseOrder->rfq)
                                <a href="{{ route('admin.rfqs.show', $purchaseOrder->rfq->id) }}">{{ $purchaseOrder->rfq->number }}</a>
                            @else
                                -
                            @endif
                        </div>
                    </div>
                    <div class="mb-2">
                        <div class="text-body-tertiary fs-9">Adjudicacao origem</div>
                        <div class="fw-semibold">{{ $purchaseOrder->award?->id ? '#'.$purchaseOrder->award->id : '-' }}</div>
                    </div>
                    <div class="mb-2">
                        <div class="text-body-tertiary fs-9">Criado por</div>
                        <div class="fw-semibold">{{ $purchaseOrder->creator?->name ?? '-' }}</div>
                    </div>
                    <div class="mb-0">
                        <div class="text-body-tertiary fs-9">Responsavel</div>
                        <div class="fw-semibold">{{ $purchaseOrder->assignedUser?->name ?? '-' }}</div>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header bg-body-tertiary d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Rececoes de material</h5>
                    @if ($purchaseOrder->canReceiveMaterial() && auth()->user()->can('company.purchase_order_receipts.create'))
                        <a href="{{ route('admin.purchase-order-receipts.create', $purchaseOrder->id) }}" class="btn btn-primary btn-sm">Registar rececao</a>
                    @endif
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm fs-9 mb-0">
                            <thead class="bg-body-tertiary">
                                <tr>
                                    <th class="ps-3">Numero</th>
                                    <th>Data</th>
                                    <th>Estado</th>
                                    <th>Linhas</th>
                                    <th>Utilizador</th>
                                    <th class="text-end pe-3">Acoes</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($purchaseOrder->receipts as $receipt)
                                    <tr>
                                        <td class="ps-3 fw-semibold">{{ $receipt->number }}</td>
                                        <td>{{ optional($receipt->receipt_date)->format('Y-m-d') ?? '-' }}</td>
                                        <td>
                                            <span class="badge badge-phoenix {{ $receipt->statusBadgeClass() }}">
                                                {{ $receiptStatusLabels[$receipt->status] ?? $receipt->status }}
                                            </span>
                                        </td>
                                        <td>{{ $receipt->items_count }}</td>
                                        <td>{{ $receipt->receiver?->name ?? '-' }}</td>
                                        <td class="text-end pe-3">
                                            <a href="{{ route('admin.purchase-order-receipts.show', $receipt->id) }}" class="btn btn-phoenix-secondary btn-sm">Ficha</a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center py-4 text-body-tertiary">Sem rececoes registadas.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header bg-body-tertiary">
                    <h5 class="mb-0">Enviar encomenda por email</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.purchase-orders.email.send', $purchaseOrder->id) }}" class="row g-3">
                        @csrf
                        <div class="col-12">
                            <label for="to" class="form-label">Para</label>
                            <input type="email" id="to" name="to" value="{{ old('to', $purchaseOrder->supplier_email_snapshot) }}" class="form-control form-control-sm @error('to') is-invalid @enderror" required>
                            @error('to')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <label for="cc" class="form-label">CC (opcional)</label>
                            <input type="text" id="cc" name="cc" value="{{ old('cc') }}" class="form-control form-control-sm @error('cc') is-invalid @enderror" placeholder="email1@empresa.pt, email2@empresa.pt">
                            @error('cc')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <label for="subject" class="form-label">Assunto</label>
                            <input type="text" id="subject" name="subject" value="{{ old('subject', \App\Mail\Admin\PurchaseOrderSentMail::defaultSubjectForPurchaseOrder($purchaseOrder)) }}" class="form-control form-control-sm @error('subject') is-invalid @enderror" required>
                            @error('subject')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <label for="message" class="form-label">Mensagem</label>
                            <textarea id="message" name="message" rows="4" class="form-control form-control-sm @error('message') is-invalid @enderror">{{ old('message') }}</textarea>
                            @error('message')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary w-100">Enviar encomenda</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header bg-body-tertiary">
                    <h5 class="mb-0">Notas</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <div class="text-body-tertiary fs-9">Notas para fornecedor</div>
                        <div>{{ $purchaseOrder->supplier_notes ?: '-' }}</div>
                    </div>
                    <div class="mb-0">
                        <div class="text-body-tertiary fs-9">Notas internas</div>
                        <div>{{ $purchaseOrder->internal_notes ?: '-' }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
