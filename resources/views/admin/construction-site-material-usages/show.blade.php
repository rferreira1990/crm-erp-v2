@extends('layouts.admin')

@section('title', 'Ficha de consumo de material')
@section('page_title', 'Ficha de consumo de material')
@section('page_subtitle', $usage->number)

@section('page_actions')
    <a href="{{ route('admin.construction-sites.material-usages.index', $site->id) }}" class="btn btn-phoenix-secondary btn-sm">Voltar</a>
    @if ($usage->isEditable() && auth()->user()->can('company.construction_site_material_usages.update'))
        <a href="{{ route('admin.construction-sites.material-usages.edit', [$site->id, $usage->id]) }}" class="btn btn-phoenix-secondary btn-sm">Editar</a>
    @endif
    @if ($usage->canPost() && auth()->user()->can('company.construction_site_material_usages.post'))
        <form method="POST" action="{{ route('admin.construction-sites.material-usages.post', [$site->id, $usage->id]) }}" class="d-inline">
            @csrf
            <button type="submit" class="btn btn-primary btn-sm">Confirmar consumo</button>
        </form>
    @endif
    @if ($usage->canCancel() && auth()->user()->can('company.construction_site_material_usages.delete'))
        <form method="POST" action="{{ route('admin.construction-sites.material-usages.cancel', [$site->id, $usage->id]) }}" class="d-inline">
            @csrf
            <button type="submit" class="btn btn-phoenix-danger btn-sm">Cancelar rascunho</button>
        </form>
    @endif
@endsection

@section('breadcrumbs')
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.construction-sites.index') }}">Obras</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.construction-sites.show', $site->id) }}">{{ $site->code }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.construction-sites.material-usages.index', $site->id) }}">Consumos</a></li>
        <li class="breadcrumb-item active" aria-current="page">{{ $usage->number }}</li>
    </ol>
@endsection

@section('content')
    @if (session('status'))
        <div class="alert alert-success" role="alert">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
    @endif

    @php
        $linesTotal = $usage->items->sum(fn ($item) => (float) $item->quantity * (float) ($item->unit_cost ?? 0));
    @endphp

    <div class="row g-4 mb-4">
        <div class="col-12 col-xxl-4">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <h4 class="mb-1">{{ $usage->number }}</h4>
                            <p class="mb-0 text-body-secondary">{{ $site->code }} - {{ $site->name }}</p>
                        </div>
                        <span class="badge badge-phoenix {{ $usage->statusBadgeClass() }}">
                            {{ $statusLabels[$usage->status] ?? $usage->status }}
                        </span>
                    </div>

                    <div class="border-top border-dashed pt-3">
                        <div class="mb-2"><span class="text-body-tertiary">Data:</span> <span class="fw-semibold">{{ optional($usage->usage_date)->format('Y-m-d') ?? '-' }}</span></div>
                        <div class="mb-2"><span class="text-body-tertiary">Criado por:</span> <span class="fw-semibold">{{ $usage->creator?->name ?? '-' }}</span></div>
                        <div class="mb-2"><span class="text-body-tertiary">Fechado em:</span> <span class="fw-semibold">{{ $usage->posted_at ? $usage->posted_at->format('Y-m-d H:i') : '-' }}</span></div>
                        <div><span class="text-body-tertiary">Linhas:</span> <span class="fw-semibold">{{ number_format((int) $usage->items->count(), 0, ',', '.') }}</span></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-xxl-8">
            <div class="card h-100">
                <div class="card-header bg-body-tertiary">
                    <h5 class="mb-0">Resumo</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3 mb-3">
                        <div class="col-12 col-md-4">
                            <div class="text-body-tertiary fs-9">Total de linhas</div>
                            <div class="fw-semibold fs-8">{{ number_format((int) $usage->items->count(), 0, ',', '.') }}</div>
                        </div>
                        <div class="col-12 col-md-4">
                            <div class="text-body-tertiary fs-9">Quantidade total</div>
                            <div class="fw-semibold fs-8">{{ number_format((float) $usage->items->sum('quantity'), 3, ',', '.') }}</div>
                        </div>
                        <div class="col-12 col-md-4">
                            <div class="text-body-tertiary fs-9">Custo estimado</div>
                            <div class="fw-semibold fs-8">{{ number_format((float) $linesTotal, 2, ',', '.') }} EUR</div>
                        </div>
                    </div>
                    <div class="text-body-tertiary fs-9 mb-1">Notas</div>
                    <div>{{ $usage->notes ?: '-' }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header bg-body-tertiary">
            <h5 class="mb-0">Linhas consumidas</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm fs-9 mb-0">
                    <thead class="bg-body-tertiary">
                        <tr>
                            <th class="ps-3">Artigo</th>
                            <th>Descricao</th>
                            <th>Unidade</th>
                            <th>Quantidade</th>
                            <th>Custo unit.</th>
                            <th>Total</th>
                            <th class="pe-3">Notas</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($usage->items as $item)
                            @php($lineTotal = (float) $item->quantity * (float) ($item->unit_cost ?? 0))
                            <tr>
                                <td class="ps-3">
                                    @if ($item->article)
                                        <a href="{{ route('admin.articles.show', $item->article->id) }}">{{ $item->article_code ?: $item->article->code }}</a>
                                    @else
                                        {{ $item->article_code ?: '-' }}
                                    @endif
                                </td>
                                <td>{{ $item->description }}</td>
                                <td>{{ $item->unit_name ?: '-' }}</td>
                                <td>{{ number_format((float) $item->quantity, 3, ',', '.') }}</td>
                                <td>{{ $item->unit_cost !== null ? number_format((float) $item->unit_cost, 4, ',', '.') : '-' }}</td>
                                <td>{{ number_format($lineTotal, 2, ',', '.') }}</td>
                                <td class="pe-3">{{ $item->notes ?: '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-4 text-body-tertiary">Sem linhas de consumo.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header bg-body-tertiary">
            <h5 class="mb-0">Movimentos de stock gerados</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm fs-9 mb-0">
                    <thead class="bg-body-tertiary">
                        <tr>
                            <th class="ps-3">Data</th>
                            <th>Artigo</th>
                            <th>Tipo</th>
                            <th>Direcao</th>
                            <th>Quantidade</th>
                            <th>Custo unit.</th>
                            <th class="pe-3">Utilizador</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($usage->stockMovements as $movement)
                            <tr>
                                <td class="ps-3">{{ optional($movement->movement_date)->format('Y-m-d') ?? '-' }}</td>
                                <td>
                                    @if ($movement->article)
                                        <a href="{{ route('admin.articles.show', $movement->article->id) }}">{{ $movement->article->code }}</a> - {{ $movement->article->designation }}
                                    @else
                                        -
                                    @endif
                                </td>
                                <td>{{ \App\Models\StockMovement::typeLabels()[$movement->type] ?? $movement->type }}</td>
                                <td>{{ \App\Models\StockMovement::directionLabels()[$movement->direction] ?? $movement->direction }}</td>
                                <td>{{ number_format((float) $movement->quantity, 3, ',', '.') }}</td>
                                <td>{{ $movement->unit_cost !== null ? number_format((float) $movement->unit_cost, 4, ',', '.') : '-' }}</td>
                                <td class="pe-3">{{ $movement->performer?->name ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-4 text-body-tertiary">Sem movimentos de stock para este registo.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
