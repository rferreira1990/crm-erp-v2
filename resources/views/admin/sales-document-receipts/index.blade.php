@extends('layouts.admin')

@section('title', 'Recibos')
@section('page_title', 'Recibos')
@section('page_subtitle', 'Recibos emitidos para Documentos de Venda')

@section('breadcrumbs')
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li>
        <li class="breadcrumb-item active" aria-current="page">Recibos</li>
    </ol>
@endsection

@section('content')
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.sales-document-receipts.index') }}" class="row g-3 align-items-end" data-live-table-form data-live-table-target="#sales-document-receipts-live-table">
                <div class="col-12 col-md-6 col-xl-3">
                    <label for="q" class="form-label">Pesquisar</label>
                    <input type="text" id="q" name="q" class="form-control" value="{{ $filters['q'] }}" placeholder="Recibo, documento ou cliente">
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
                <div class="col-12 col-md-3 col-xl-3">
                    <label for="customer_id" class="form-label">Cliente</label>
                    <select id="customer_id" name="customer_id" class="form-select">
                        <option value="">Todos</option>
                        @foreach ($customers as $customer)
                            <option value="{{ $customer->id }}" @selected((string) $filters['customer_id'] === (string) $customer->id)>{{ $customer->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-6 col-xl-2">
                    <label for="date_from" class="form-label">Data inicio</label>
                    <input type="date" id="date_from" name="date_from" class="form-control" value="{{ $filters['date_from'] }}">
                </div>
                <div class="col-12 col-md-6 col-xl-2">
                    <label for="date_to" class="form-label">Data fim</label>
                    <input type="date" id="date_to" name="date_to" class="form-control" value="{{ $filters['date_to'] }}">
                </div>
                <div class="col-12 col-md-6 col-xl-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-fill">Filtrar</button>
                    <a href="{{ route('admin.sales-document-receipts.index') }}" class="btn btn-phoenix-secondary flex-fill">Limpar</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card" id="sales-document-receipts-live-table">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm fs-9 mb-0">
                    <thead class="bg-body-tertiary">
                        <tr>
                            <th class="ps-3">Recibo</th>
                            <th>Data</th>
                            <th>Documento</th>
                            <th>Cliente</th>
                            <th>Modo pagamento</th>
                            <th>Valor</th>
                            <th>Estado</th>
                            <th class="text-end pe-3">Acoes</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($receipts as $receipt)
                            <tr>
                                <td class="ps-3 fw-semibold">{{ $receipt->number }}</td>
                                <td>{{ optional($receipt->receipt_date)->format('Y-m-d') ?? '-' }}</td>
                                <td>
                                    @if ($receipt->salesDocument)
                                        <a href="{{ route('admin.sales-documents.show', $receipt->salesDocument->id) }}">{{ $receipt->salesDocument->number }}</a>
                                    @else
                                        -
                                    @endif
                                </td>
                                <td>{{ $receipt->customer?->name ?? '-' }}</td>
                                <td>{{ $receipt->paymentMethod?->name ?? '-' }}</td>
                                <td>{{ number_format((float) $receipt->amount, 2, ',', '.') }}</td>
                                <td>
                                    <span class="badge badge-phoenix {{ $receipt->statusBadgeClass() }}">
                                        {{ $statusLabels[$receipt->status] ?? $receipt->status }}
                                    </span>
                                </td>
                                <td class="text-end pe-3">
                                    <a href="{{ route('admin.sales-document-receipts.show', $receipt->id) }}" class="btn btn-phoenix-secondary btn-sm">Ficha</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-4 text-body-tertiary">Sem recibos registados.</td>
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
