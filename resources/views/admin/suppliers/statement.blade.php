@extends('layouts.admin')

@section('title', 'Conta Corrente do Fornecedor')
@section('page_title', 'Conta Corrente do Fornecedor')
@section('page_subtitle', $supplier->name)

@section('page_actions')
    <a href="{{ route('admin.suppliers.show', $supplier->id) }}" class="btn btn-phoenix-secondary btn-sm">Voltar ao fornecedor</a>
@endsection

@section('breadcrumbs')
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.suppliers.index') }}">Fornecedores</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.suppliers.show', $supplier->id) }}">{{ $supplier->name }}</a></li>
        <li class="breadcrumb-item active" aria-current="page">Conta Corrente</li>
    </ol>
@endsection

@section('content')
    @php
        $movementsCollection = $movements instanceof \Illuminate\Contracts\Pagination\Paginator
            ? collect($movements->items())
            : collect($movements);
        $documentsCount = $movementsCollection->where('type', 'purchase_document')->count();
        $paymentsCount = $movementsCollection->where('type', 'supplier_payment')->count();
    @endphp

    <div class="card mb-4">
        <div class="card-body">
            <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="flex-shrink-0 d-flex align-items-center justify-content-center rounded-circle bg-body-highlight" style="width: 72px; height: 72px;">
                        <span class="fw-bold fs-4">{{ mb_substr((string) $supplier->name, 0, 1) }}</span>
                    </div>
                    <div>
                        <h3 class="mb-1">{{ $supplier->name }}</h3>
                        <div class="text-body-secondary">{{ $periodLabel }}</div>
                    </div>
                </div>

                <div class="d-flex flex-wrap gap-2">
                    <a href="{{ route('admin.suppliers.statement.show', [$supplier->id, 'statement_view' => 'open']) }}" class="btn btn-phoenix-secondary btn-sm">Em aberto</a>
                    <a href="{{ route('admin.suppliers.statement.show', [$supplier->id, 'statement_view' => 'overdue']) }}" class="btn btn-phoenix-secondary btn-sm">Vencidas</a>
                    <a href="{{ route('admin.suppliers.statement.show', [$supplier->id, 'statement_view' => 'settled']) }}" class="btn btn-phoenix-secondary btn-sm">Liquidadas</a>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.suppliers.statement.show', $supplier->id) }}" class="row g-3 align-items-end">
                <div class="col-12 col-md-3">
                    <label for="date_from" class="form-label">Data inicio</label>
                    <input type="date" id="date_from" name="date_from" class="form-control" value="{{ $filters['date_from'] ?? '' }}">
                </div>
                <div class="col-12 col-md-3">
                    <label for="date_to" class="form-label">Data fim</label>
                    <input type="date" id="date_to" name="date_to" class="form-control" value="{{ $filters['date_to'] ?? '' }}">
                </div>
                <div class="col-12 col-md-3">
                    <label for="statement_view" class="form-label">Extrato</label>
                    <select id="statement_view" name="statement_view" class="form-select">
                        @foreach ($statementViewLabels as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['statement_view'] ?? 'all') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-fill">Filtrar</button>
                    <a href="{{ route('admin.suppliers.statement.show', $supplier->id) }}" class="btn btn-phoenix-secondary flex-fill">Limpar</a>
                </div>
            </form>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-12 col-md-6 col-xl-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-body-tertiary fs-9">Valor a pagar</div>
                    <div class="h4 mb-0 text-danger">{{ number_format((float) $totalDebit, 2, ',', '.') }} &euro;</div>
                    <div class="text-body-secondary fs-10 mt-2">Debitos de Documentos de Compra</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-body-tertiary fs-9">Valor pago</div>
                    <div class="h4 mb-0 text-success">{{ number_format((float) $totalCredit, 2, ',', '.') }} &euro;</div>
                    <div class="text-body-secondary fs-10 mt-2">Creditos por pagamentos emitidos</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-body-tertiary fs-9">Saldo atual</div>
                    <div class="h4 mb-0 {{ $balance > 0 ? 'text-danger' : ($balance < 0 ? 'text-success' : '') }}">{{ number_format((float) $balance, 2, ',', '.') }} &euro;</div>
                    <div class="text-body-secondary fs-10 mt-2">Positivo = divida ao fornecedor</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-body-tertiary fs-9">Movimentos no extrato</div>
                    <div class="h4 mb-0">{{ $movementsCollection->count() }}</div>
                    <div class="text-body-secondary fs-10 mt-2">{{ $documentsCount }} docs · {{ $paymentsCount }} pagamentos</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header bg-body-tertiary d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Movimentos</h5>
            <span class="text-body-secondary fs-9">Saldo acumulado por data</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm fs-9 mb-0">
                    <thead class="bg-body-tertiary">
                        <tr>
                            <th class="ps-3">Data</th>
                            <th>Tipo</th>
                            <th>Numero</th>
                            <th>Descricao</th>
                            <th>Valor a pagar</th>
                            <th>Valor pago</th>
                            <th class="pe-3">Saldo acumulado</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($movements as $movement)
                            <tr>
                                <td class="ps-3">{{ optional($movement['date'])->format('Y-m-d') ?? '-' }}</td>
                                <td>
                                    @if ($movement['type'] === 'purchase_document')
                                        <span class="badge badge-phoenix badge-phoenix-warning">Documento de Compra</span>
                                    @else
                                        @if ($movement['status'] === \App\Models\SupplierPayment::STATUS_ISSUED)
                                            <span class="badge badge-phoenix badge-phoenix-success">Pagamento</span>
                                        @else
                                            <span class="badge badge-phoenix badge-phoenix-secondary">Pagamento cancelado</span>
                                        @endif
                                    @endif
                                </td>
                                <td><a href="{{ $movement['route'] }}">{{ $movement['number'] }}</a></td>
                                <td>{{ $movement['description'] }}</td>
                                <td>{{ number_format((float) $movement['debit'], 2, ',', '.') }} &euro;</td>
                                <td>{{ number_format((float) $movement['credit'], 2, ',', '.') }} &euro;</td>
                                <td class="pe-3 fw-semibold {{ (float) $movement['balance'] > 0 ? 'text-danger' : ((float) $movement['balance'] < 0 ? 'text-success' : '') }}">
                                    {{ number_format((float) $movement['balance'], 2, ',', '.') }} &euro;
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-4 text-body-tertiary">Sem movimentos para este fornecedor.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @if ($movements instanceof \Illuminate\Contracts\Pagination\Paginator && $movements->hasPages())
        <div class="mt-3">
            {{ $movements->links() }}
        </div>
    @endif
@endsection
