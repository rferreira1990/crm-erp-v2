@extends('layouts.admin')

@section('title', 'Documentos de Compra')
@section('page_title', 'Documentos de Compra')
@section('page_subtitle', 'Gestao de documentos recebidos de fornecedor')

@section('page_actions')
    @can('company.purchase_documents.create')
        <a href="{{ route('admin.purchase-documents.create') }}" class="btn btn-primary btn-sm">Novo documento</a>
    @endcan
@endsection

@section('breadcrumbs')
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li>
        <li class="breadcrumb-item active" aria-current="page">Documentos de Compra</li>
    </ol>
@endsection

@section('content')
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.purchase-documents.index') }}" class="row g-3 align-items-end">
                <div class="col-12 col-md-4">
                    <label for="q" class="form-label">Pesquisar</label>
                    <input type="text" id="q" name="q" class="form-control" value="{{ $filters['q'] }}" placeholder="Numero ou fornecedor">
                </div>
                <div class="col-12 col-md-2">
                    <label for="status" class="form-label">Estado</label>
                    <select id="status" name="status" class="form-select">
                        <option value="">Todos</option>
                        @foreach ($statusLabels as $value => $label)
                            <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-2">
                    <label for="payment_status" class="form-label">Pagamento</label>
                    <select id="payment_status" name="payment_status" class="form-select">
                        <option value="">Todos</option>
                        @foreach ($paymentStatusLabels as $value => $label)
                            <option value="{{ $value }}" @selected($filters['payment_status'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-4">
                    <label for="supplier_id" class="form-label">Fornecedor</label>
                    <select id="supplier_id" name="supplier_id" class="form-select">
                        <option value="">Todos</option>
                        @foreach ($suppliers as $supplier)
                            <option value="{{ $supplier->id }}" @selected((string) $filters['supplier_id'] === (string) $supplier->id)>{{ $supplier->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-fill">Filtrar</button>
                    <a href="{{ route('admin.purchase-documents.index') }}" class="btn btn-phoenix-secondary flex-fill">Limpar</a>
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
                            <th>Doc. fornecedor</th>
                            <th>Origem</th>
                            <th>Linhas</th>
                            <th>Total</th>
                            <th>Pagamento</th>
                            <th>Estado</th>
                            <th class="text-end pe-3">Acoes</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($documents as $document)
                            <tr>
                                <td class="ps-3 fw-semibold">{{ $document->number }}</td>
                                <td>{{ optional($document->issue_date)->format('Y-m-d') ?? '-' }}</td>
                                <td>{{ $document->supplier?->name ?? '-' }}</td>
                                <td>{{ $document->supplier_document_number ?: '-' }}</td>
                                <td>
                                    @if ($document->purchaseOrder)
                                        <span class="badge badge-phoenix badge-phoenix-info">PO {{ $document->purchaseOrder->number }}</span>
                                    @else
                                        <span class="badge badge-phoenix badge-phoenix-secondary">Manual</span>
                                    @endif
                                </td>
                                <td>{{ $document->items_count }}</td>
                                <td>{{ number_format((float) $document->grand_total, 2, ',', '.') }} {{ $document->currency }}</td>
                                <td>
                                    <span class="badge badge-phoenix {{ $document->paymentStatusBadgeClass() }}">
                                        {{ $paymentStatusLabels[$document->payment_status] ?? $document->paymentStatusLabel() }}
                                    </span>
                                </td>
                                <td>
                                    <span class="badge badge-phoenix {{ $document->statusBadgeClass() }}">
                                        {{ $statusLabels[$document->status] ?? $document->status }}
                                    </span>
                                </td>
                                <td class="text-end pe-3">
                                    @can('company.purchase_documents.update')
                                        @if ($document->isEditableDraft())
                                            <a href="{{ route('admin.purchase-documents.edit', $document->id) }}" class="btn btn-phoenix-secondary btn-sm">Editar</a>
                                        @endif
                                    @endcan
                                    <a href="{{ route('admin.purchase-documents.show', $document->id) }}" class="btn btn-phoenix-secondary btn-sm">Ficha</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="text-center py-4 text-body-tertiary">Sem documentos registados.</td>
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
