@extends('layouts.admin')

@section('title', 'Diario de obra')
@section('page_title', 'Diario de obra')
@section('page_subtitle', $site->code.' - '.$site->name)

@section('page_actions')
    <a href="{{ route('admin.construction-sites.show', $site->id) }}" class="btn btn-phoenix-secondary btn-sm">Voltar a obra</a>
    <a href="{{ route('admin.construction-sites.logs.create', $site->id) }}" class="btn btn-primary btn-sm">Novo registo</a>
@endsection

@section('breadcrumbs')
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.construction-sites.index') }}">Obras</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.construction-sites.show', $site->id) }}">{{ $site->code }}</a></li>
        <li class="breadcrumb-item active" aria-current="page">Diario</li>
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
                            <th class="ps-3">Data</th>
                            <th>Tipo</th>
                            <th>Titulo</th>
                            <th>Criado por</th>
                            <th>Associado</th>
                            <th>Importante</th>
                            <th class="text-end pe-3">Acoes</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($logs as $log)
                            <tr>
                                <td class="ps-3 fw-semibold">{{ optional($log->log_date)->format('Y-m-d') }}</td>
                                <td>
                                    <span class="badge badge-phoenix {{ $log->typeBadgeClass() }}">
                                        {{ $typeLabels[$log->type] ?? $log->type }}
                                    </span>
                                </td>
                                <td>
                                    <div class="fw-semibold">{{ $log->title }}</div>
                                    <div class="text-body-tertiary">{{ \Illuminate\Support\Str::limit($log->description, 110) }}</div>
                                </td>
                                <td>{{ $log->creator?->name ?? '-' }}</td>
                                <td>{{ $log->assignedUser?->name ?? '-' }}</td>
                                <td>
                                    @if ($log->is_important)
                                        <span class="badge badge-phoenix badge-phoenix-danger">Sim</span>
                                    @else
                                        <span class="badge badge-phoenix badge-phoenix-secondary">Nao</span>
                                    @endif
                                </td>
                                <td class="text-end pe-3">
                                    <div class="d-inline-flex gap-2">
                                        <a href="{{ route('admin.construction-sites.logs.show', [$site->id, $log->id]) }}" class="btn btn-phoenix-secondary btn-sm">Ver</a>
                                        <a href="{{ route('admin.construction-sites.logs.edit', [$site->id, $log->id]) }}" class="btn btn-phoenix-secondary btn-sm">Editar</a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-4 text-body-tertiary">Sem registos no diario desta obra.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if ($logs->hasPages())
            <div class="card-footer">
                {{ $logs->links() }}
            </div>
        @endif
    </div>
@endsection
