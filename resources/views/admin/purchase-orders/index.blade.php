@extends('layouts.admin')

@section('title', 'Encomendas a fornecedor')
@section('page_title', 'Encomendas a fornecedor')
@section('page_subtitle', 'Gestao de encomendas geradas por adjudicacao')

@section('breadcrumbs')
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li>
        <li class="breadcrumb-item active" aria-current="page">Encomendas a fornecedor</li>
    </ol>
@endsection

@section('content')
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.purchase-orders.index') }}" class="row g-3 align-items-end">
                <div class="col-12 col-md-6 col-xl-5">
                    <label for="q" class="form-label">Pesquisar</label>
                    <input type="text" id="q" name="q" class="form-control" value="{{ $filters['q'] }}" placeholder="Numero ou fornecedor">
                </div>
                <div class="col-12 col-md-4 col-xl-3">
                    <label for="status" class="form-label">Estado</label>
                    <select id="status" name="status" class="form-select">
                        <option value="">Todos</option>
                        @foreach ($statusLabels as $value => $label)
                            <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-2 col-xl-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-fill">Filtrar</button>
                    <a href="{{ route('admin.purchase-orders.index') }}" class="btn btn-phoenix-secondary flex-fill">Limpar</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm fs-9 mb-0">
                    <thead class="bg-body-tertiary">
                        <tr>
                            <th class="ps-3">Numero</th>
                            <th>Data</th>
                            <th>Fornecedor</th>
                            <th>Origem RFQ</th>
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
                                    <a href="{{ route('admin.purchase-orders.show', $purchaseOrder->id) }}" class="btn btn-phoenix-secondary btn-sm">Ficha</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-4 text-body-tertiary">Sem encomendas registadas.</td>
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
