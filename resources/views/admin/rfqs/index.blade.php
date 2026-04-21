@extends('layouts.admin')

@section('title', 'Pedidos de cotacao')
@section('page_title', 'Pedidos de cotacao')
@section('page_subtitle', 'Gestao de consultas de precos a fornecedores')

@section('page_actions')
    <a href="{{ route('admin.rfqs.create') }}" class="btn btn-primary btn-sm">Novo pedido</a>
@endsection

@section('breadcrumbs')
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li>
        <li class="breadcrumb-item active" aria-current="page">Pedidos de cotacao</li>
    </ol>
@endsection

@section('content')
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
    @endif

    <div class="card">
        <div class="card-header bg-body-tertiary">
            <h5 class="mb-0">Lista de pedidos</h5>
        </div>
        <div class="card-body border-bottom border-translucent">
            <form method="GET" action="{{ route('admin.rfqs.index') }}" class="row g-3 align-items-end">
                <div class="col-12 col-md-5">
                    <label for="q" class="form-label">Pesquisar</label>
                    <input
                        type="text"
                        id="q"
                        name="q"
                        value="{{ $filters['q'] ?? '' }}"
                        class="form-control"
                        placeholder="Numero ou titulo"
                    >
                </div>
                <div class="col-12 col-md-3">
                    <label for="status" class="form-label">Estado</label>
                    <select id="status" name="status" class="form-select">
                        <option value="">Todos</option>
                        @foreach ($statusLabels as $statusKey => $statusLabel)
                            <option value="{{ $statusKey }}" @selected(($filters['status'] ?? '') === $statusKey)>{{ $statusLabel }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-fill">Filtrar</button>
                    <a href="{{ route('admin.rfqs.index') }}" class="btn btn-phoenix-secondary flex-fill">Limpar</a>
                </div>
            </form>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm fs-9 mb-0">
                    <thead class="bg-body-tertiary">
                        <tr>
                            <th class="ps-3">Numero</th>
                            <th>Titulo</th>
                            <th>Data</th>
                            <th>Prazo resposta</th>
                            <th>Estado</th>
                            <th>Fornecedores</th>
                            <th>Respostas</th>
                            <th>Total estimado</th>
                            <th class="text-end pe-3">Acoes</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rfqs as $rfq)
                            @php
                                $supplierCount = $rfq->invitedSuppliers->count();
                                $responsesCount = $rfq->invitedSuppliers->where('status', \App\Models\SupplierQuoteRequestSupplier::STATUS_RESPONDED)->count();
                            @endphp
                            <tr>
                                <td class="ps-3 fw-semibold">{{ $rfq->number }}</td>
                                <td>{{ $rfq->title ?: '-' }}</td>
                                <td>{{ optional($rfq->issue_date)->format('Y-m-d') }}</td>
                                <td>{{ optional($rfq->response_deadline)->format('Y-m-d') ?? '-' }}</td>
                                <td>
                                    <span class="badge badge-phoenix {{ $rfq->statusBadgeClass() }}">
                                        {{ $statusLabels[$rfq->status] ?? $rfq->status }}
                                    </span>
                                </td>
                                <td>{{ $supplierCount }}</td>
                                <td>{{ $responsesCount }}</td>
                                <td>{{ $rfq->estimated_total !== null ? number_format((float) $rfq->estimated_total, 2, ',', '.').' EUR' : '-' }}</td>
                                <td class="text-end pe-3">
                                    <div class="d-inline-flex gap-2">
                                        <a href="{{ route('admin.rfqs.show', $rfq->id) }}" class="btn btn-phoenix-secondary btn-sm">Ficha</a>
                                        @if ($rfq->isEditable() && auth()->user()->can('company.rfq.update'))
                                            <a href="{{ route('admin.rfqs.edit', $rfq->id) }}" class="btn btn-phoenix-secondary btn-sm">Editar</a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center py-4 text-body-tertiary">Sem pedidos de cotacao registados.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if ($rfqs->hasPages())
            <div class="card-footer">{{ $rfqs->links() }}</div>
        @endif
    </div>
@endsection

