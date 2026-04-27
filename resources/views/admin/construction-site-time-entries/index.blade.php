@extends('layouts.admin')

@section('title', 'Lancamentos de horas')
@section('page_title', 'Lancamentos de horas')
@section('page_subtitle', 'Mao de obra por obra')

@section('page_actions')
    @if (($filters['construction_site_id'] ?? null) !== null && auth()->user()->can('company.construction_site_time_entries.create'))
        <a href="{{ route('admin.construction-sites.time-entries.create', (int) $filters['construction_site_id']) }}" class="btn btn-primary btn-sm">Registar horas</a>
    @endif
@endsection

@section('breadcrumbs')
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li>
        <li class="breadcrumb-item active" aria-current="page">Lancamentos de horas</li>
    </ol>
@endsection

@section('content')

    <div class="card mb-4">
        <div class="card-header bg-body-tertiary">
            <h5 class="mb-0">Filtros</h5>
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route('admin.construction-site-time-entries.index') }}" class="row g-3" data-live-table-form data-live-table-target="#construction-site-time-entries-live-table">
                <div class="col-12 col-md-3">
                    <label class="form-label" for="construction_site_id">Obra</label>
                    <select id="construction_site_id" name="construction_site_id" class="form-select">
                        <option value="">Todas</option>
                        @foreach ($siteOptions as $siteOption)
                            <option value="{{ $siteOption->id }}" @selected((int) ($filters['construction_site_id'] ?? 0) === (int) $siteOption->id)>
                                {{ $siteOption->code }} - {{ $siteOption->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-2">
                    <label class="form-label" for="user_id">Colaborador</label>
                    <select id="user_id" name="user_id" class="form-select">
                        <option value="">Todos</option>
                        @foreach ($workerOptions as $workerOption)
                            <option value="{{ $workerOption->id }}" @selected((int) ($filters['user_id'] ?? 0) === (int) $workerOption->id)>{{ $workerOption->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-2">
                    <label class="form-label" for="task_type">Tipo</label>
                    <select id="task_type" name="task_type" class="form-select">
                        <option value="">Todos</option>
                        @foreach ($taskTypeOptions as $taskType => $taskTypeLabel)
                            <option value="{{ $taskType }}" @selected(($filters['task_type'] ?? '') === $taskType)>{{ $taskTypeLabel }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-2">
                    <label class="form-label" for="date_from">Data inicio</label>
                    <input type="date" id="date_from" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="form-control">
                </div>
                <div class="col-12 col-md-2">
                    <label class="form-label" for="date_to">Data fim</label>
                    <input type="date" id="date_to" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="form-control">
                </div>
                <div class="col-12 col-md-1 d-flex align-items-end">
                    <button type="submit" class="btn btn-phoenix-primary w-100">Filtrar</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card" id="construction-site-time-entries-live-table">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm fs-9 mb-0">
                    <thead class="bg-body-tertiary">
                        <tr>
                            <th class="ps-3">Data</th>
                            <th>Obra</th>
                            <th>Colaborador</th>
                            <th>Horas</th>
                            <th>Custo/h</th>
                            <th>Custo total</th>
                            <th>Tipo</th>
                            <th>Descricao</th>
                            <th>Criado por</th>
                            <th class="text-end pe-3">Acoes</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($entries as $entry)
                            <tr>
                                <td class="ps-3">{{ optional($entry->work_date)->format('Y-m-d') ?? '-' }}</td>
                                <td>
                                    @if ($entry->constructionSite)
                                        <a href="{{ route('admin.construction-sites.show', $entry->constructionSite->id) }}">{{ $entry->constructionSite->code }}</a>
                                    @else
                                        -
                                    @endif
                                </td>
                                <td>{{ $entry->worker?->name ?? '-' }}</td>
                                <td>{{ number_format((float) $entry->hours, 2, ',', '.') }}</td>
                                <td>{{ number_format((float) $entry->hourly_cost, 2, ',', '.') }}</td>
                                <td>{{ number_format((float) $entry->total_cost, 2, ',', '.') }}</td>
                                <td>
                                    @if ($entry->task_type)
                                        <span class="badge badge-phoenix {{ $entry->taskTypeBadgeClass() }}">{{ $taskTypeOptions[$entry->task_type] ?? $entry->task_type }}</span>
                                    @else
                                        -
                                    @endif
                                </td>
                                <td>{{ \Illuminate\Support\Str::limit($entry->description, 70) }}</td>
                                <td>{{ $entry->creator?->name ?? '-' }}</td>
                                <td class="text-end pe-3">
                                    <a href="{{ route('admin.construction-sites.time-entries.show', [$entry->construction_site_id, $entry->id]) }}" class="btn btn-phoenix-secondary btn-sm">Ver</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="text-center py-4 text-body-tertiary">Sem lancamentos de horas para os filtros selecionados.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if ($entries->hasPages())
            <div class="card-footer">
                {{ $entries->links() }}
            </div>
        @endif
    </div>
@endsection
