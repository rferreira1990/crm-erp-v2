@extends('layouts.admin')

@section('title', 'Pagamentos a Fornecedor')
@section('page_title', 'Pagamentos a Fornecedor')
@section('page_subtitle', 'Registo e controlo de pagamentos de Documentos de Compra')

@section('breadcrumbs')
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li>
        <li class="breadcrumb-item active" aria-current="page">Pagamentos a Fornecedor</li>
    </ol>
@endsection

@section('content')
    @php
        $pagePayments = collect($payments->items());
        $pageTotal = (float) $pagePayments->sum(fn ($payment) => (float) $payment->amount);
        $issuedCount = $pagePayments->where('status', \App\Models\SupplierPayment::STATUS_ISSUED)->count();
        $cancelledCount = $pagePayments->where('status', \App\Models\SupplierPayment::STATUS_CANCELLED)->count();
    @endphp

    <div class="card mb-4">
        <div class="card-body">
            <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
                <div>
                    <h4 class="mb-1">Visao financeira de pagamentos</h4>
                    <div class="text-body-secondary">Pagina {{ $payments->currentPage() }} · {{ $payments->total() }} registos no total</div>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <a href="{{ route('admin.purchase-documents.index') }}" class="btn btn-phoenix-secondary btn-sm">Documentos de Compra</a>
                    <a href="{{ route('admin.suppliers.index') }}" class="btn btn-phoenix-secondary btn-sm">Fornecedores</a>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-12 col-md-6 col-xl-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-body-tertiary fs-9">Registos na pagina</div>
                    <div class="h4 mb-0">{{ $pagePayments->count() }}</div>
                    <div class="text-body-secondary fs-10 mt-2">Resultados apos filtros</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-body-tertiary fs-9">Valor total (pagina)</div>
                    <div class="h4 mb-0">{{ number_format($pageTotal, 2, ',', '.') }} &euro;</div>
                    <div class="text-body-secondary fs-10 mt-2">Soma dos pagamentos listados</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-body-tertiary fs-9">Emitidos</div>
                    <div class="h4 mb-0 text-success">{{ $issuedCount }}</div>
                    <div class="text-body-secondary fs-10 mt-2">Prontos para rastreio financeiro</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-body-tertiary fs-9">Cancelados</div>
                    <div class="h4 mb-0 text-warning">{{ $cancelledCount }}</div>
                    <div class="text-body-secondary fs-10 mt-2">Sem impacto no saldo</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.supplier-payments.index') }}" class="row g-3 align-items-end">
                <div class="col-12 col-md-4">
                    <label for="q" class="form-label">Pesquisar</label>
                    <input type="text" id="q" name="q" class="form-control" value="{{ $filters['q'] }}" placeholder="Numero, documento ou fornecedor">
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
                <div class="col-12 col-md-3">
                    <label for="supplier_id" class="form-label">Fornecedor</label>
                    <select id="supplier_id" name="supplier_id" class="form-select">
                        <option value="">Todos</option>
                        @foreach ($suppliers as $supplier)
                            <option value="{{ $supplier->id }}" @selected((string) $filters['supplier_id'] === (string) $supplier->id)>{{ $supplier->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-3">
                    <label for="date_from" class="form-label">Data inicio</label>
                    <input type="date" id="date_from" name="date_from" class="form-control" value="{{ $filters['date_from'] ?? '' }}">
                </div>
                <div class="col-12 col-md-3">
                    <label for="date_to" class="form-label">Data fim</label>
                    <input type="date" id="date_to" name="date_to" class="form-control" value="{{ $filters['date_to'] ?? '' }}">
                </div>
                <div class="col-12 col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-fill">Filtrar</button>
                    <a href="{{ route('admin.supplier-payments.index') }}" class="btn btn-phoenix-secondary flex-fill">Limpar</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header bg-body-tertiary d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Lista de pagamentos</h5>
            <span class="text-body-secondary fs-9">{{ $payments->total() }} registos</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm fs-9 mb-0">
                    <thead class="bg-body-tertiary">
                        <tr>
                            <th class="ps-3">Numero</th>
                            <th>Data</th>
                            <th>Fornecedor</th>
                            <th>Documento</th>
                            <th>Modo pagamento</th>
                            <th>Valor</th>
                            <th>Estado</th>
                            <th class="text-end pe-3">Acoes</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($payments as $payment)
                            <tr>
                                <td class="ps-3 fw-semibold">{{ $payment->number }}</td>
                                <td>{{ optional($payment->payment_date)->format('Y-m-d') ?? '-' }}</td>
                                <td>{{ $payment->supplier?->name ?? '-' }}</td>
                                <td>{{ $payment->purchaseDocument?->number ?? '-' }}</td>
                                <td>{{ $payment->paymentMethod?->name ?? '-' }}</td>
                                <td>{{ number_format((float) $payment->amount, 2, ',', '.') }} &euro;</td>
                                <td><span class="badge badge-phoenix {{ $payment->statusBadgeClass() }}">{{ $statusLabels[$payment->status] ?? $payment->status }}</span></td>
                                <td class="text-end pe-3">
                                    <a href="{{ route('admin.supplier-payments.show', $payment->id) }}" class="btn btn-phoenix-secondary btn-sm">Ficha</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-4 text-body-tertiary">Sem pagamentos registados.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if ($payments->hasPages())
            <div class="card-footer">{{ $payments->links() }}</div>
        @endif
    </div>
@endsection
