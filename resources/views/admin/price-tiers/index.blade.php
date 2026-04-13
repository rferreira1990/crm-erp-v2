@extends('layouts.admin')

@section('title', 'Escaloes de preco')
@section('page_title', 'Escaloes de preco')
@section('page_subtitle', 'Escaloes do sistema e escaloes personalizados da sua empresa')

@section('page_actions')
    <a href="{{ route('admin.price-tiers.create') }}" class="btn btn-primary btn-sm">Novo escalao</a>
@endsection

@section('breadcrumbs')
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li>
        <li class="breadcrumb-item active" aria-current="page">Escaloes de preco</li>
    </ol>
@endsection

@section('content')
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="card">
        <div class="card-header bg-body-tertiary d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Gestao de escaloes de preco</h5>
        </div>

        <div class="card-body border-bottom border-translucent">
            <form method="GET" action="{{ route('admin.price-tiers.index') }}" class="row g-3 align-items-end">
                <div class="col-12 col-md-6">
                    <label for="q" class="form-label">Pesquisar</label>
                    <input
                        type="text"
                        id="q"
                        name="q"
                        value="{{ $filters['q'] ?? '' }}"
                        class="form-control"
                        placeholder="Nome do escalao"
                    >
                </div>
                <div class="col-12 col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-fill">Filtrar</button>
                    <a href="{{ route('admin.price-tiers.index') }}" class="btn btn-phoenix-secondary flex-fill">Limpar</a>
                </div>
            </form>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm fs-9 mb-0">
                    <thead class="bg-body-tertiary">
                        <tr>
                            <th class="ps-3">Nome</th>
                            <th>Ajuste %</th>
                            <th>Origem</th>
                            <th>Default</th>
                            <th>Estado</th>
                            <th class="text-end pe-3">Acoes</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($priceTiers as $priceTier)
                            <tr>
                                <td class="ps-3 fw-semibold">{{ $priceTier->name }}</td>
                                <td>{{ number_format((float) $priceTier->percentage_adjustment, 2) }}%</td>
                                <td>
                                    @if ($priceTier->is_system)
                                        <span class="badge badge-phoenix badge-phoenix-info">Sistema</span>
                                    @else
                                        <span class="badge badge-phoenix badge-phoenix-primary">Empresa</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($priceTier->is_default)
                                        <span class="badge badge-phoenix badge-phoenix-success">Default</span>
                                    @else
                                        -
                                    @endif
                                </td>
                                <td>
                                    @if ($priceTier->is_active)
                                        <span class="badge badge-phoenix badge-phoenix-success">Ativo</span>
                                    @else
                                        <span class="badge badge-phoenix badge-phoenix-secondary">Inativo</span>
                                    @endif
                                </td>
                                <td class="text-end pe-3">
                                    @if ($priceTier->is_system || $priceTier->is_default)
                                        <span class="text-body-tertiary">Protegido</span>
                                    @else
                                        <div class="d-inline-flex gap-2">
                                            <a href="{{ route('admin.price-tiers.edit', $priceTier->id) }}" class="btn btn-phoenix-secondary btn-sm">Editar</a>
                                            <form method="POST" action="{{ route('admin.price-tiers.destroy', $priceTier->id) }}" data-confirm="Tem a certeza que pretende apagar este escalao?">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-phoenix-danger btn-sm">Apagar</button>
                                            </form>
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-4 text-body-tertiary">Sem escaloes de preco disponiveis.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if ($priceTiers->hasPages())
            <div class="card-footer">
                {{ $priceTiers->links() }}
            </div>
        @endif
    </div>
@endsection

