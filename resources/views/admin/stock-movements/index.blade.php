@extends('layouts.admin')

@section('title', 'Movimentos de stock')
@section('page_title', 'Movimentos de stock')
@section('page_subtitle', 'Historico de entradas, saidas e ajustes')

@section('page_actions')
    @can('company.stock_movements.create')
        <a href="{{ route('admin.stock-movements.create') }}" class="btn btn-primary btn-sm">Novo movimento manual</a>
    @endcan
@endsection

@section('breadcrumbs')
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li>
        <li class="breadcrumb-item active" aria-current="page">Movimentos de stock</li>
    </ol>
@endsection

@section('content')
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.stock-movements.index') }}" class="row g-3 align-items-end" data-live-table-form data-live-table-target="#stock-movements-live-table">
                <div class="col-12 col-md-6 col-xl-4">
                    <label class="form-label" for="article_id">Artigo</label>
                    <select id="article_id" name="article_id" class="form-select">
                        <option value="">Todos</option>
                        @foreach ($articleOptions as $article)
                            <option value="{{ $article->id }}" @selected((int) ($filters['article_id'] ?? 0) === (int) $article->id)>
                                {{ $article->code }} - {{ $article->designation }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-3 col-xl-2">
                    <label class="form-label" for="type">Tipo</label>
                    <select id="type" name="type" class="form-select">
                        <option value="">Todos</option>
                        @foreach ($typeLabels as $value => $label)
                            <option value="{{ $value }}" @selected($filters['type'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-3 col-xl-2">
                    <label class="form-label" for="direction">Direcao</label>
                    <select id="direction" name="direction" class="form-select">
                        <option value="">Todas</option>
                        @foreach ($directionLabels as $value => $label)
                            <option value="{{ $value }}" @selected($filters['direction'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-3 col-xl-2">
                    <label class="form-label" for="date_from">De</label>
                    <input type="date" id="date_from" name="date_from" class="form-control" value="{{ $filters['date_from'] }}">
                </div>
                <div class="col-6 col-md-3 col-xl-2">
                    <label class="form-label" for="date_to">Ate</label>
                    <input type="date" id="date_to" name="date_to" class="form-control" value="{{ $filters['date_to'] }}">
                </div>
                <div class="col-12 col-md-6 col-xl-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-fill">Filtrar</button>
                    <a href="{{ route('admin.stock-movements.index') }}" class="btn btn-phoenix-secondary flex-fill">Limpar</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card" id="stock-movements-live-table">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm fs-9 mb-0">
                    <thead class="bg-body-tertiary">
                        <tr>
                            <th class="ps-3">Data</th>
                            <th>Artigo</th>
                            <th>Tipo</th>
                            <th>Direcao</th>
                            <th>Qtd.</th>
                            <th>Motivo</th>
                            <th>Utilizador</th>
                            <th>Origem</th>
                            <th class="pe-3">Notas</th>
                            <th class="text-end pe-3">Acoes</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($movements as $movement)
                            <tr>
                                <td class="ps-3">{{ optional($movement->movement_date)->format('Y-m-d') ?? '-' }}</td>
                                <td>
                                    @if ($movement->article)
                                        <a href="{{ route('admin.articles.show', $movement->article->id) }}">{{ $movement->article->code }}</a>
                                        <div class="text-body-tertiary">{{ $movement->article->designation }}</div>
                                    @else
                                        -
                                    @endif
                                </td>
                                <td>{{ $typeLabels[$movement->type] ?? $movement->type }}</td>
                                <td>
                                    @if ($movement->direction === 'in')
                                        <span class="badge badge-phoenix badge-phoenix-success">{{ $directionLabels[$movement->direction] ?? $movement->direction }}</span>
                                    @else
                                        <span class="badge badge-phoenix badge-phoenix-danger">{{ $directionLabels[$movement->direction] ?? $movement->direction }}</span>
                                    @endif
                                </td>
                                <td>{{ number_format((float) $movement->quantity, 3, ',', '.') }}</td>
                                <td>{{ $movement->reason_code !== null ? ($reasonLabels[$movement->reason_code] ?? $movement->reason_code) : '-' }}</td>
                                <td>{{ $movement->performer?->name ?? '-' }}</td>
                                <td>
                                    @if ($movement->reference_type === 'manual')
                                        Manual
                                    @else
                                        {{ $movement->reference_type }} #{{ $movement->reference_id }}
                                    @endif
                                </td>
                                <td class="pe-3">{{ $movement->notes ?: '-' }}</td>
                                <td class="text-end pe-3">
                                    <a href="{{ route('admin.stock-movements.show', $movement->id) }}" class="btn btn-phoenix-secondary btn-sm">Ficha</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="text-center py-4 text-body-tertiary">Sem movimentos registados.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if ($movements->hasPages())
            <div class="card-footer">
                {{ $movements->links() }}
            </div>
        @endif
    </div>
@endsection
