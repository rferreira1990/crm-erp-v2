@extends('layouts.admin')

@section('title', 'Obras')
@section('page_title', 'Obras')
@section('page_subtitle', 'Gestao de obras da empresa')

@section('page_actions')
    <a href="{{ route('admin.construction-sites.create') }}" class="btn btn-primary btn-sm">Nova obra</a>
@endsection

@section('breadcrumbs')
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li>
        <li class="breadcrumb-item active" aria-current="page">Obras</li>
    </ol>
@endsection

@section('content')
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
    @endif

    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.construction-sites.index') }}" class="row g-3 align-items-end">
                <div class="col-12 col-lg-5">
                    <label for="q" class="form-label">Pesquisar</label>
                    <input type="text" id="q" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control" placeholder="Codigo, nome ou cliente">
                </div>
                <div class="col-12 col-md-4 col-lg-2">
                    <label for="status" class="form-label">Estado</label>
                    <select id="status" name="status" class="form-select">
                        <option value="">Todos</option>
                        @foreach ($statusLabels as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-4 col-lg-3">
                    <label for="customer_id" class="form-label">Cliente</label>
                    <select id="customer_id" name="customer_id" class="form-select">
                        <option value="">Todos</option>
                        @foreach ($customerOptions as $customer)
                            <option value="{{ $customer->id }}" @selected((int) ($filters['customer_id'] ?? 0) === (int) $customer->id)>{{ $customer->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-4 col-lg-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-fill">Filtrar</button>
                    <a href="{{ route('admin.construction-sites.index') }}" class="btn btn-phoenix-secondary flex-fill">Limpar</a>
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
                            <th class="ps-3">Codigo</th>
                            <th>Nome</th>
                            <th>Cliente</th>
                            <th>Estado</th>
                            <th>Responsavel</th>
                            <th>Inicio planeado</th>
                            <th>Fim planeado</th>
                            <th>Ativo</th>
                            <th class="text-end pe-3">Acoes</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($sites as $site)
                            <tr>
                                <td class="ps-3 fw-semibold">{{ $site->code }}</td>
                                <td>{{ $site->name }}</td>
                                <td>{{ $site->customer?->name ?? '-' }}</td>
                                <td>
                                    <span class="badge badge-phoenix {{ $site->statusBadgeClass() }}">
                                        {{ $statusLabels[$site->status] ?? $site->status }}
                                    </span>
                                </td>
                                <td>{{ $site->assignedUser?->name ?? '-' }}</td>
                                <td>{{ optional($site->planned_start_date)->format('Y-m-d') ?? '-' }}</td>
                                <td>{{ optional($site->planned_end_date)->format('Y-m-d') ?? '-' }}</td>
                                <td>
                                    @if ($site->is_active)
                                        <span class="badge badge-phoenix badge-phoenix-success">Sim</span>
                                    @else
                                        <span class="badge badge-phoenix badge-phoenix-secondary">Nao</span>
                                    @endif
                                </td>
                                <td class="text-end pe-3">
                                    <div class="d-inline-flex gap-2">
                                        <a href="{{ route('admin.construction-sites.show', $site->id) }}" class="btn btn-phoenix-secondary btn-sm">Ficha</a>
                                        <a href="{{ route('admin.construction-sites.edit', $site->id) }}" class="btn btn-phoenix-secondary btn-sm">Editar</a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center py-4 text-body-tertiary">Sem obras registadas.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if ($sites->hasPages())
            <div class="card-footer">
                {{ $sites->links() }}
            </div>
        @endif
    </div>
@endsection
