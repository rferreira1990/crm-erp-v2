@extends('layouts.admin')

@section('title', 'Encomendas a fornecedor')
@section('page_title', 'Encomendas a fornecedor')
@section('page_subtitle', 'Gestao de encomendas manuais e geradas por RFQ')

@section('page_actions')
    @can('company.purchase_orders.create')
        <a href="{{ route('admin.purchase-orders.create') }}" class="btn btn-primary btn-sm">Nova encomenda</a>
    @endcan
@endsection

@section('breadcrumbs')
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li>
        <li class="breadcrumb-item active" aria-current="page">Encomendas a fornecedor</li>
    </ol>
@endsection

@section('content')
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.purchase-orders.index') }}" class="row g-3 align-items-end" data-live-table-form data-live-table-target="#purchase-orders-live-table">
                <div class="col-12 col-md-6 col-xl-4">
                    <label for="q" class="form-label">Pesquisar</label>
                    <input type="text" id="q" name="q" class="form-control" value="{{ $filters['q'] }}" placeholder="Numero ou fornecedor">
                </div>
                <div class="col-12 col-md-3 col-xl-2">
                    <label for="status" class="form-label">Estado</label>
                    <select id="status" name="status" class="form-select">
                        <option value="">Todos</option>
                        @foreach ($statusLabels as $value => $label)
                            <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-3 col-xl-2">
                    <label for="source_type" class="form-label">Origem</label>
                    <select id="source_type" name="source_type" class="form-select">
                        <option value="">Todas</option>
                        @foreach ($sourceTypeLabels as $value => $label)
                            <option value="{{ $value }}" @selected($filters['source_type'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-6 col-xl-4">
                    <label for="supplier_id" class="form-label">Fornecedor</label>
                    <select id="supplier_id" name="supplier_id" class="form-select">
                        <option value="">Todos</option>
                        @foreach ($suppliers as $supplier)
                            <option value="{{ $supplier->id }}" @selected((string) $filters['supplier_id'] === (string) $supplier->id)>{{ $supplier->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-3 col-xl-2">
                    <label for="issue_date_from" class="form-label">Data de</label>
                    <input type="date" id="issue_date_from" name="issue_date_from" value="{{ $filters['issue_date_from'] }}" class="form-control">
                </div>
                <div class="col-12 col-md-3 col-xl-2">
                    <label for="issue_date_to" class="form-label">Data ate</label>
                    <input type="date" id="issue_date_to" name="issue_date_to" value="{{ $filters['issue_date_to'] }}" class="form-control">
                </div>
                <div class="col-12 col-md-6 col-xl-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-fill">Filtrar</button>
                    <a href="{{ route('admin.purchase-orders.index') }}" class="btn btn-phoenix-secondary flex-fill">Limpar</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card" id="purchase-orders-live-table">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm fs-9 mb-0">
                    <thead class="bg-body-tertiary">
                        <tr>
                            <th class="ps-3">Numero</th>
                            <th>Data</th>
                            <th>Fornecedor</th>
                            <th>Origem</th>
                            <th>RFQ origem</th>
                            <th>Linhas</th>
                            <th>Total</th>
                            <th>Estado</th>
                            <th class="text-end pe-3">Acoes</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($purchaseOrders as $purchaseOrder)
                            <tr>
                                <td class="ps-3 fw-semibold">{{ $purchaseOrder->number }}</td>
                                <td>{{ optional($purchaseOrder->issue_date)->format('Y-m-d') ?? '-' }}</td>
                                <td>{{ $purchaseOrder->supplier_name_snapshot }}</td>
                                <td>
                                    <span class="badge badge-phoenix {{ $purchaseOrder->isManualOrigin() ? 'badge-phoenix-info' : 'badge-phoenix-secondary' }}">
                                        {{ $purchaseOrder->originLabel() }}
                                    </span>
                                </td>
                                <td>
                                    @if ($purchaseOrder->rfq)
                                        <a href="{{ route('admin.rfqs.show', $purchaseOrder->rfq->id) }}">{{ $purchaseOrder->rfq->number }}</a>
                                    @else
                                        -
                                    @endif
                                </td>
                                <td>{{ $purchaseOrder->items_count }}</td>
                                <td>{{ number_format((float) $purchaseOrder->grand_total, 2, ',', '.') }} {{ $purchaseOrder->currency }}</td>
                                <td>
                                    <span class="badge badge-phoenix {{ $purchaseOrder->statusBadgeClass() }}">
                                        {{ $statusLabels[$purchaseOrder->status] ?? $purchaseOrder->status }}
                                    </span>
                                </td>
                                <td class="text-end pe-3">
                                    @can('company.purchase_orders.update')
                                        @if ($purchaseOrder->isEditableManualDraft())
                                            <a href="{{ route('admin.purchase-orders.edit', $purchaseOrder->id) }}" class="btn btn-phoenix-secondary btn-sm">Editar</a>
                                        @endif
                                    @endcan
                                    <a href="{{ route('admin.purchase-orders.show', $purchaseOrder->id) }}" class="btn btn-phoenix-secondary btn-sm">Ficha</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center py-4 text-body-tertiary">Sem encomendas registadas.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if ($purchaseOrders->hasPages())
            <div class="card-footer">
                {{ $purchaseOrders->links() }}
            </div>
        @endif
    </div>
@endsection
