@extends('layouts.admin')

@section('title', 'Ficha do movimento')
@section('page_title', 'Ficha do movimento')
@section('page_subtitle', 'Movimento #'.$movement->id)

@section('page_actions')
    <a href="{{ route('admin.stock-movements.index') }}" class="btn btn-phoenix-secondary btn-sm">Voltar</a>
    @can('company.stock_movements.create')
        <a href="{{ route('admin.stock-movements.create') }}" class="btn btn-primary btn-sm">Novo movimento manual</a>
    @endcan
@endsection

@section('breadcrumbs')
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.stock-movements.index') }}">Movimentos de stock</a></li>
        <li class="breadcrumb-item active" aria-current="page">#{{ $movement->id }}</li>
    </ol>
@endsection

@section('content')
    <div class="card">
        <div class="card-header bg-body-tertiary d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Movimento #{{ $movement->id }}</h5>
            @if ($movement->direction === 'in')
                <span class="badge badge-phoenix badge-phoenix-success">{{ $directionLabels[$movement->direction] ?? $movement->direction }}</span>
            @else
                <span class="badge badge-phoenix badge-phoenix-danger">{{ $directionLabels[$movement->direction] ?? $movement->direction }}</span>
            @endif
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-12 col-md-4">
                    <div class="text-body-tertiary fs-9">Data</div>
                    <div class="fw-semibold">{{ optional($movement->movement_date)->format('Y-m-d') ?? '-' }}</div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="text-body-tertiary fs-9">Tipo</div>
                    <div class="fw-semibold">{{ $typeLabels[$movement->type] ?? $movement->type }}</div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="text-body-tertiary fs-9">Quantidade</div>
                    <div class="fw-semibold">{{ number_format((float) $movement->quantity, 3, ',', '.') }}</div>
                </div>
                <div class="col-12 col-md-6">
                    <div class="text-body-tertiary fs-9">Motivo</div>
                    <div class="fw-semibold">{{ $movement->reasonLabel() ?? '-' }}</div>
                </div>
                <div class="col-12 col-md-6">
                    <div class="text-body-tertiary fs-9">Utilizador</div>
                    <div class="fw-semibold">{{ $movement->performer?->name ?? '-' }}</div>
                </div>
                <div class="col-12 col-md-6">
                    <div class="text-body-tertiary fs-9">Artigo</div>
                    <div class="fw-semibold">
                        @if ($movement->article)
                            <a href="{{ route('admin.articles.show', $movement->article->id) }}">{{ $movement->article->code }}</a>
                            - {{ $movement->article->designation }}
                        @else
                            -
                        @endif
                    </div>
                </div>
                <div class="col-12 col-md-6">
                    <div class="text-body-tertiary fs-9">Stock atual do artigo</div>
                    <div class="fw-semibold">
                        @if ($movement->article)
                            {{ number_format((float) $movement->article->stock_quantity, 3, ',', '.') }}
                        @else
                            -
                        @endif
                    </div>
                </div>
                <div class="col-12 col-md-6">
                    <div class="text-body-tertiary fs-9">Origem</div>
                    <div class="fw-semibold">
                        @if ($movement->reference_type === 'manual')
                            Manual
                        @else
                            {{ $movement->reference_type }} #{{ $movement->reference_id }}
                        @endif
                    </div>
                </div>
                <div class="col-12">
                    <div class="text-body-tertiary fs-9">Notas</div>
                    <div>{{ $movement->notes ?: '-' }}</div>
                </div>
            </div>
        </div>
    </div>
@endsection
