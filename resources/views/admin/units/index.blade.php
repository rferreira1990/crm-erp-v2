@extends('layouts.admin')

@section('title', 'Unidades')
@section('page_title', 'Unidades')
@section('page_subtitle', 'Unidades do sistema e unidades personalizadas da sua empresa')

@section('page_actions')
    <a href="{{ route('admin.units.create') }}" class="btn btn-primary btn-sm">Nova unidade</a>
@endsection

@section('breadcrumbs')
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li>
        <li class="breadcrumb-item active" aria-current="page">Unidades</li>
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
            <h5 class="mb-0">Gestao de unidades</h5>
        </div>

        <div class="card-body border-bottom border-translucent">
            <form method="GET" action="{{ route('admin.units.index') }}" class="row g-3 align-items-end" data-live-table-form data-live-table-target="#units-live-table">
                <div class="col-12 col-md-6">
                    <label for="q" class="form-label">Pesquisar</label>
                    <input
                        type="text"
                        id="q"
                        name="q"
                        value="{{ $filters['q'] ?? '' }}"
                        class="form-control"
                        placeholder="Codigo ou nome"
                    >
                </div>
                <div class="col-12 col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-fill">Filtrar</button>
                    <a href="{{ route('admin.units.index') }}" class="btn btn-phoenix-secondary flex-fill">Limpar</a>
                </div>
            </form>
        </div>

        <div id="units-live-table">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm fs-9 mb-0">
                    <thead class="bg-body-tertiary">
                        <tr>
                            <th class="ps-3">Codigo</th>
                            <th>Nome</th>
                            <th>Origem</th>
                            <th class="text-end pe-3">Acoes</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($units as $unit)
                            <tr>
                                <td class="ps-3 fw-semibold">{{ $unit->code }}</td>
                                <td>{{ $unit->name }}</td>
                                <td>
                                    @if ($unit->is_system)
                                        <span class="badge badge-phoenix badge-phoenix-info">Sistema</span>
                                    @else
                                        <span class="badge badge-phoenix badge-phoenix-primary">Personalizada</span>
                                    @endif
                                </td>
                                <td class="text-end pe-3">
                                    @if ($unit->is_system)
                                        <span class="text-body-tertiary">Protegida</span>
                                    @else
                                        <div class="d-inline-flex gap-2">
                                            <a href="{{ route('admin.units.edit', $unit->id) }}" class="btn btn-phoenix-secondary btn-sm">
                                                Editar
                                            </a>
                                            <form
                                                method="POST"
                                                action="{{ route('admin.units.destroy', $unit->id) }}"
                                                data-confirm="Tem a certeza que pretende apagar esta unidade?"
                                            >
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
                                <td colspan="4" class="text-center py-4 text-body-tertiary">Sem unidades registadas.</td>
                            </tr>
                        @endforelse
                    </tbody>
                    </table>
                </div>
            </div>

            @if ($units->hasPages())
                <div class="card-footer">
                    {{ $units->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
