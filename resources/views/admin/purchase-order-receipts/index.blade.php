@extends('layouts.admin')

@section('title', 'Rececoes de encomendas')
@section('page_title', 'Rececoes de encomendas')
@section('page_subtitle', 'Historico de rececao de material')

@section('breadcrumbs')
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li>
        <li class="breadcrumb-item active" aria-current="page">Rececoes de encomendas</li>
    </ol>
@endsection

@section('content')
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.purchase-order-receipts.index') }}" class="row g-3 align-items-end">
                <div class="col-12 col-md-6 col-xl-5">
                    <label for="q" class="form-label">Pesquisar</label>
                    <input type="text" id="q" name="q" class="form-control" value="{{ $filters['q'] }}" placeholder="Numero, encomenda ou fornecedor">
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
                    <a href="{{ route('admin.purchase-order-receipts.index') }}" class="btn btn-phoenix-secondary flex-fill">Limpar</a>
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
                            <th>Encomenda</th>
                            <th>Fornecedor</th>
                            <th>Linhas</th>
                            <th>Estado</th>
                            <th>Recebido por</th>
                            <th class="text-end pe-3">Acoes</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($receipts as $receipt)
                            <tr>
                                <td class="ps-3 fw-semibold">{{ $receipt->number }}</td>
                                <td>{{ optional($receipt->receipt_date)->format('Y-m-d') ?? '-' }}</td>
                                <td>
                                    @if ($receipt->purchaseOrder)
                                        <a href="{{ route('admin.purchase-orders.show', $receipt->purchaseOrder->id) }}">{{ $receipt->purchaseOrder->number }}</a>
                                    @else
                                        -
                                    @endif
                                </td>
                                <td>{{ $receipt->purchaseOrder?->supplier_name_snapshot ?? '-' }}</td>
                                <td>{{ $receipt->items_count }}</td>
                                <td>
                                    <span class="badge badge-phoenix {{ $receipt->statusBadgeClass() }}">
                                        {{ $statusLabels[$receipt->status] ?? $receipt->status }}
                                    </span>
                                </td>
                                <td>{{ $receipt->receiver?->name ?? '-' }}</td>
                                <td class="text-end pe-3">
                                    <a href="{{ route('admin.purchase-order-receipts.show', $receipt->id) }}" class="btn btn-phoenix-secondary btn-sm">Ficha</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-4 text-body-tertiary">Sem rececoes registadas.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if ($receipts->hasPages())
            <div class="card-footer">
                {{ $receipts->links() }}
            </div>
        @endif
    </div>
@endsection
