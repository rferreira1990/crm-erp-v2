@extends('layouts.admin')

@section('title', 'Registo do diario')
@section('page_title', 'Registo do diario')
@section('page_subtitle', $site->code.' - '.$log->title)

@section('page_actions')
    <a href="{{ route('admin.construction-sites.logs.index', $site->id) }}" class="btn btn-phoenix-secondary btn-sm">Ver todos os logs</a>
    <a href="{{ route('admin.construction-sites.logs.edit', [$site->id, $log->id]) }}" class="btn btn-primary btn-sm">Editar registo</a>
@endsection

@section('breadcrumbs')
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.construction-sites.index') }}">Obras</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.construction-sites.show', $site->id) }}">{{ $site->code }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.construction-sites.logs.index', $site->id) }}">Diario</a></li>
        <li class="breadcrumb-item active" aria-current="page">Registo</li>
    </ol>
@endsection

@section('content')
    @if (session('status'))
        <div class="alert alert-success" role="alert">{{ session('status') }}</div>
    @endif

    <div class="row g-4 mb-4">
        <div class="col-12 col-xxl-4">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <h4 class="mb-0">{{ $log->title }}</h4>
                        <span class="badge badge-phoenix {{ $log->typeBadgeClass() }}">
                            {{ $typeLabels[$log->type] ?? $log->type }}
                        </span>
                    </div>

                    @if ($log->is_important)
                        <div class="mb-3">
                            <span class="badge badge-phoenix badge-phoenix-danger">Importante</span>
                        </div>
                    @endif

                    <div class="border-top border-dashed pt-3">
                        <div class="mb-2"><span class="text-body-tertiary">Obra:</span> <a href="{{ route('admin.construction-sites.show', $site->id) }}" class="fw-semibold">{{ $site->code }} - {{ $site->name }}</a></div>
                        <div class="mb-2"><span class="text-body-tertiary">Data:</span> <span class="fw-semibold">{{ optional($log->log_date)->format('Y-m-d') }}</span></div>
                        <div class="mb-2"><span class="text-body-tertiary">Criado por:</span> <span class="fw-semibold">{{ $log->creator?->name ?? '-' }}</span></div>
                        <div class="mb-2"><span class="text-body-tertiary">Utilizador associado:</span> <span class="fw-semibold">{{ $log->assignedUser?->name ?? '-' }}</span></div>
                        <div><span class="text-body-tertiary">Criado em:</span> <span class="fw-semibold">{{ optional($log->created_at)->format('Y-m-d H:i') }}</span></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-xxl-8">
            <div class="card h-100">
                <div class="card-header bg-body-tertiary">
                    <h5 class="mb-0">Descricao detalhada</h5>
                </div>
                <div class="card-body">
                    {!! nl2br(e($log->description)) !!}
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-12 col-xl-6">
            <div class="card h-100">
                <div class="card-header bg-body-tertiary">
                    <h5 class="mb-0">Imagens</h5>
                </div>
                <div class="card-body">
                    @forelse ($log->images as $image)
                        <div class="d-flex justify-content-between align-items-center border rounded p-2 mb-2">
                            <a href="{{ route('admin.construction-sites.logs.images.show', [$site->id, $log->id, $image->id]) }}" target="_blank" rel="noopener noreferrer">{{ $image->original_name }}</a>
                            @if ($image->is_primary)
                                <span class="badge badge-phoenix badge-phoenix-success">Primaria</span>
                            @endif
                        </div>
                    @empty
                        <div class="text-body-tertiary">Sem imagens registadas.</div>
                    @endforelse
                </div>
            </div>
        </div>
        <div class="col-12 col-xl-6">
            <div class="card h-100">
                <div class="card-header bg-body-tertiary">
                    <h5 class="mb-0">Anexos</h5>
                </div>
                <div class="card-body">
                    @forelse ($log->files as $file)
                        <div class="border rounded p-2 mb-2">
                            <a href="{{ route('admin.construction-sites.logs.files.download', [$site->id, $log->id, $file->id]) }}" class="fw-semibold">{{ $file->original_name }}</a>
                            <div class="small text-body-tertiary">{{ $file->mime_type ?? '-' }}</div>
                        </div>
                    @empty
                        <div class="text-body-tertiary">Sem ficheiros registados.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
@endsection
