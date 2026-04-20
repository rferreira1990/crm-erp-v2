@extends('layouts.admin')

@section('title', 'Orcamentos')
@section('page_title', 'Orcamentos')
@section('page_subtitle', 'Gestao de propostas comerciais da empresa')

@section('page_actions')
    <a href="{{ route('admin.quotes.dashboard') }}" class="btn btn-phoenix-secondary btn-sm">Dashboard</a>
    <a href="{{ route('admin.quotes.create') }}" class="btn btn-primary btn-sm">Novo orcamento</a>
@endsection

@section('breadcrumbs')
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li>
        <li class="breadcrumb-item active" aria-current="page">Orcamentos</li>
    </ol>
@endsection

@section('content')
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="card">
        <div class="card-header bg-body-tertiary">
            <h5 class="mb-0">Lista de orcamentos</h5>
        </div>

        <div class="card-body border-bottom border-translucent">
            <form method="GET" action="{{ route('admin.quotes.index') }}" class="row g-3 align-items-end">
                <div class="col-12 col-md-5">
                    <label for="q" class="form-label">Pesquisar</label>
                    <input
                        type="text"
                        id="q"
                        name="q"
                        value="{{ $filters['q'] ?? '' }}"
                        class="form-control"
                        placeholder="Numero, assunto ou cliente"
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
                    <a href="{{ route('admin.quotes.index') }}" class="btn btn-phoenix-secondary flex-fill">Limpar</a>
                </div>
            </form>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm fs-9 mb-0">
                    <thead class="bg-body-tertiary">
                        <tr>
                            <th class="ps-3">Numero</th>
                            <th>Cliente</th>
                            <th>Contacto</th>
                            <th>Data</th>
                            <th>Validade</th>
                            <th>Estado</th>
                            <th>Total</th>
                            <th>Responsavel</th>
                            <th>Follow-up</th>
                            <th class="text-end pe-3">Acoes</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($quotes as $quote)
                            <tr>
                                <td class="ps-3 fw-semibold">{{ $quote->number }}</td>
                                <td>{{ $quote->customer?->name ?? '-' }}</td>
                                <td>{{ $quote->customerContact?->name ?? '-' }}</td>
                                <td>{{ optional($quote->issue_date)->format('Y-m-d') }}</td>
                                <td>{{ optional($quote->valid_until)->format('Y-m-d') ?? '-' }}</td>
                                <td>
                                    <span class="badge badge-phoenix {{ $quote->statusBadgeClass() }}">
                                        {{ $statusLabels[$quote->status] ?? $quote->status }}
                                    </span>
                                </td>
                                <td>{{ number_format((float) $quote->grand_total, 2, ',', '.') }} {{ $quote->currency }}</td>
                                <td>{{ $quote->assignedUser?->name ?? '-' }}</td>
                                <td>{{ optional($quote->follow_up_date)->format('Y-m-d') ?? '-' }}</td>
                                <td class="text-end pe-3">
                                    <div class="d-inline-flex gap-2">
                                        <a href="{{ route('admin.quotes.show', $quote->id) }}" class="btn btn-phoenix-secondary btn-sm">Ficha</a>
                                        @if ($quote->isEditable() && auth()->user()->can('company.quotes.update'))
                                            <a href="{{ route('admin.quotes.edit', $quote->id) }}" class="btn btn-phoenix-secondary btn-sm">Editar</a>
                                        @endif
                                        @if ($quote->status === \App\Models\Quote::STATUS_DRAFT && auth()->user()->can('company.quotes.delete'))
                                            <form method="POST" action="{{ route('admin.quotes.destroy', $quote->id) }}" onsubmit="return confirm('Eliminar este orcamento em rascunho?');" class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-phoenix-danger btn-sm">Apagar</button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="text-center py-4 text-body-tertiary">Sem orcamentos registados.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if ($quotes->hasPages())
            <div class="card-footer">
                {{ $quotes->links() }}
            </div>
        @endif
    </div>
@endsection
