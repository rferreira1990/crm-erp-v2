@extends('layouts.admin')

@section('title', 'Lancamento de horas')
@section('page_title', 'Lancamento de horas')
@section('page_subtitle', $site->code.' - '.$site->name)

@section('page_actions')
    <a href="{{ route('admin.construction-site-time-entries.index', ['construction_site_id' => $site->id]) }}" class="btn btn-phoenix-secondary btn-sm">Voltar</a>
    @can('company.construction_site_time_entries.update')
        <a href="{{ route('admin.construction-sites.time-entries.edit', [$site->id, $entry->id]) }}" class="btn btn-primary btn-sm">Editar</a>
    @endcan
    @can('company.construction_site_time_entries.delete')
        <form method="POST" action="{{ route('admin.construction-sites.time-entries.destroy', [$site->id, $entry->id]) }}" class="d-inline">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-phoenix-danger btn-sm" onclick="return confirm('Remover este lancamento de horas?')">Remover</button>
        </form>
    @endcan
@endsection

@section('breadcrumbs')
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.construction-sites.index') }}">Obras</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.construction-sites.show', $site->id) }}">{{ $site->code }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.construction-site-time-entries.index', ['construction_site_id' => $site->id]) }}">Horas</a></li>
        <li class="breadcrumb-item active" aria-current="page">Lancamento</li>
    </ol>
@endsection

@section('content')

    <div class="row g-4">
        <div class="col-12 col-lg-6">
            <div class="card h-100">
                <div class="card-header bg-body-tertiary">
                    <h5 class="mb-0">Dados do lancamento</h5>
                </div>
                <div class="card-body">
                    <div class="mb-2"><span class="text-body-tertiary">Obra:</span> <span class="fw-semibold">{{ $site->code }} - {{ $site->name }}</span></div>
                    <div class="mb-2"><span class="text-body-tertiary">Data:</span> <span class="fw-semibold">{{ optional($entry->work_date)->format('Y-m-d') ?? '-' }}</span></div>
                    <div class="mb-2"><span class="text-body-tertiary">Colaborador:</span> <span class="fw-semibold">{{ $entry->worker?->name ?? '-' }}</span></div>
                    <div class="mb-2"><span class="text-body-tertiary">Horas:</span> <span class="fw-semibold">{{ number_format((float) $entry->hours, 2, ',', '.') }}</span></div>
                    <div class="mb-2"><span class="text-body-tertiary">Custo/h:</span> <span class="fw-semibold">{{ number_format((float) $entry->hourly_cost, 2, ',', '.') }} EUR</span></div>
                    <div class="mb-2"><span class="text-body-tertiary">Custo total:</span> <span class="fw-semibold">{{ number_format((float) $entry->total_cost, 2, ',', '.') }} EUR</span></div>
                    <div class="mb-2">
                        <span class="text-body-tertiary">Tipo de tarefa:</span>
                        @if ($entry->task_type)
                            <span class="badge badge-phoenix {{ $entry->taskTypeBadgeClass() }}">{{ $taskTypeOptions[$entry->task_type] ?? $entry->task_type }}</span>
                        @else
                            <span class="fw-semibold">-</span>
                        @endif
                    </div>
                    <div><span class="text-body-tertiary">Criado por:</span> <span class="fw-semibold">{{ $entry->creator?->name ?? '-' }}</span></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-6">
            <div class="card h-100">
                <div class="card-header bg-body-tertiary">
                    <h5 class="mb-0">Descricao</h5>
                </div>
                <div class="card-body">
                    {!! nl2br(e($entry->description)) !!}
                </div>
            </div>
        </div>
    </div>
@endsection
