@extends('layouts.admin')

@section('title', 'Documentos de Venda')
@section('page_title', 'Documentos de Venda')
@section('page_subtitle', 'Gestao de documentos manuais, de orcamento e de obra')

@section('page_actions')
    @can('company.sales_documents.create')
        <div class="btn-group">
            <button type="button" class="btn btn-primary btn-sm dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                Novo documento
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="{{ route('admin.sales-documents.create', ['source' => \App\Models\SalesDocument::SOURCE_MANUAL]) }}">Manual</a></li>
                <li><a class="dropdown-item" href="{{ route('admin.sales-documents.create', ['source' => \App\Models\SalesDocument::SOURCE_QUOTE]) }}">A partir de orcamento</a></li>
                <li><a class="dropdown-item" href="{{ route('admin.sales-documents.create', ['source' => \App\Models\SalesDocument::SOURCE_CONSTRUCTION_SITE]) }}">A partir de obra</a></li>
            </ul>
        </div>
    @endcan
@endsection

@section('breadcrumbs')
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li>
        <li class="breadcrumb-item active" aria-current="page">Documentos de Venda</li>
    </ol>
@endsection

@section('content')
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.sales-documents.index') }}" class="row g-3 align-items-end">
                <div class="col-12 col-md-6 col-xl-4">
                    <label for="q" class="form-label">Pesquisar</label>
                    <input type="text" id="q" name="q" class="form-control" value="{{ $filters['q'] }}" placeholder="Numero ou cliente">
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
                        @foreach ($sourceLabels as $value => $label)
                            <option value="{{ $value }}" @selected($filters['source_type'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-6 col-xl-4">
                    <label for="customer_id" class="form-label">Cliente</label>
                    <select id="customer_id" name="customer_id" class="form-select">
                        <option value="">Todos</option>
                        @foreach ($customers as $customer)
                            <option value="{{ $customer->id }}" @selected((string) $filters['customer_id'] === (string) $customer->id)>{{ $customer->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-6 col-xl-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-fill">Filtrar</button>
                    <a href="{{ route('admin.sales-documents.index') }}" class="btn btn-phoenix-secondary flex-fill">Limpar</a>
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
                            <th>Origem</th>
                            <th>Cliente</th>
                            <th>Linhas</th>
                            <th>Total</th>
                            <th>Stock</th>
                            <th>Estado</th>
                            <th class="text-end pe-3">Acoes</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($documents as $document)
                            <tr>
                                <td class="ps-3 fw-semibold">{{ $document->number }}</td>
                                <td>{{ optional($document->issue_date)->format('Y-m-d') ?? '-' }}</td>
                                <td>{{ $sourceLabels[$document->source_type] ?? $document->source_type }}</td>
                                <td>{{ $document->customer_name_snapshot ?: ($document->customer?->name ?? '-') }}</td>
                                <td>{{ $document->items_count }}</td>
                                <td>{{ number_format((float) $document->grand_total, 2, ',', '.') }} {{ $document->currency }}</td>
                                <td>
                                    @if ($document->shouldMoveStock())
                                        <span class="badge badge-phoenix badge-phoenix-warning">Movimenta</span>
                                    @else
                                        <span class="badge badge-phoenix badge-phoenix-secondary">Nao movimenta</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge badge-phoenix {{ $document->statusBadgeClass() }}">
                                        {{ $statusLabels[$document->status] ?? $document->status }}
                                    </span>
                                </td>
                                <td class="text-end pe-3">
                                    @can('company.sales_documents.update')
                                        @if ($document->isEditableDraft())
                                            <a href="{{ route('admin.sales-documents.edit', $document->id) }}" class="btn btn-phoenix-secondary btn-sm">Editar</a>
                                        @endif
                                    @endcan
                                    <a href="{{ route('admin.sales-documents.show', $document->id) }}" class="btn btn-phoenix-secondary btn-sm">Ficha</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center py-4 text-body-tertiary">Sem documentos registados.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if ($documents->hasPages())
            <div class="card-footer">
                {{ $documents->links() }}
            </div>
        @endif
    </div>
@endsection

