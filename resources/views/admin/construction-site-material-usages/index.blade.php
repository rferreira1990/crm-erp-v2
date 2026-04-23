@extends('layouts.admin')

@section('title', 'Consumos de material')
@section('page_title', 'Consumos de material')
@section('page_subtitle', $site->code.' - '.$site->name)

@section('page_actions')
    <a href="{{ route('admin.construction-sites.show', $site->id) }}" class="btn btn-phoenix-secondary btn-sm">Voltar a obra</a>
    @can('company.construction_site_material_usages.create')
        <a href="{{ route('admin.construction-sites.material-usages.create', $site->id) }}" class="btn btn-primary btn-sm">Novo consumo</a>
    @endcan
@endsection

@section('breadcrumbs')
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.construction-sites.index') }}">Obras</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.construction-sites.show', $site->id) }}">{{ $site->code }}</a></li>
        <li class="breadcrumb-item active" aria-current="page">Consumos</li>
    </ol>
@endsection

@section('content')

    @if ($errors->any())
        <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
    @endif

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm fs-9 mb-0">
                    <thead class="bg-body-tertiary">
                        <tr>
                            <th class="ps-3">Numero</th>
                            <th>Data</th>
                            <th>Estado</th>
                            <th>Criado por</th>
                            <th>Linhas</th>
                            <th class="text-end pe-3">Acoes</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($usages as $usage)
                            <tr>
                                <td class="ps-3 fw-semibold">{{ $usage->number }}</td>
                                <td>{{ optional($usage->usage_date)->format('Y-m-d') ?? '-' }}</td>
                                <td>
                                    <span class="badge badge-phoenix {{ $usage->statusBadgeClass() }}">
                                        {{ $statusLabels[$usage->status] ?? $usage->status }}
                                    </span>
                                </td>
                                <td>{{ $usage->creator?->name ?? '-' }}</td>
                                <td>{{ number_format((int) ($usage->items_count ?? 0), 0, ',', '.') }}</td>
                                <td class="text-end pe-3">
                                    <a href="{{ route('admin.construction-sites.material-usages.show', [$site->id, $usage->id]) }}" class="btn btn-phoenix-secondary btn-sm">Ver</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-4 text-body-tertiary">Sem consumos registados.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if ($usages->hasPages())
            <div class="card-footer">
                {{ $usages->links() }}
            </div>
        @endif
    </div>
@endsection
