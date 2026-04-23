@extends('layouts.admin')

@section('title', 'Ficha da obra')
@section('page_title', 'Ficha da obra')
@section('page_subtitle', $site->code.' - '.$site->name)

@section('page_actions')
    <a href="{{ route('admin.construction-sites.index') }}" class="btn btn-phoenix-secondary btn-sm">Voltar</a>
    <a href="{{ route('admin.construction-sites.edit', $site->id) }}" class="btn btn-primary btn-sm">Editar obra</a>
    @if (($canCreateMaterialUsages ?? false) === true)
        <a href="{{ route('admin.construction-sites.material-usages.create', $site->id) }}" class="btn btn-phoenix-secondary btn-sm">Registar consumo de material</a>
    @endif
    <a href="{{ route('admin.customers.show', $site->customer_id) }}" class="btn btn-phoenix-secondary btn-sm">Abrir cliente</a>
    @if ($site->quote)
        <a href="{{ route('admin.quotes.show', $site->quote->id) }}" class="btn btn-phoenix-secondary btn-sm">Abrir orcamento</a>
    @endif
@endsection

@section('breadcrumbs')
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.construction-sites.index') }}">Obras</a></li>
        <li class="breadcrumb-item active" aria-current="page">{{ $site->code }}</li>
    </ol>
@endsection

@section('content')
    @if (session('status'))
        <div class="alert alert-success" role="alert">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
    @endif

    <div class="row g-4 mb-4">
        <div class="col-12 col-xxl-4">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <h3 class="mb-1">{{ $site->code }}</h3>
                            <p class="mb-0 text-body-secondary">{{ $site->name }}</p>
                        </div>
                        <span class="badge badge-phoenix {{ $site->statusBadgeClass() }}">
                            {{ $statusLabels[$site->status] ?? $site->status }}
                        </span>
                    </div>

                    <div class="mb-2">
                        @if ($site->is_active)
                            <span class="badge badge-phoenix badge-phoenix-success">Ativa</span>
                        @else
                            <span class="badge badge-phoenix badge-phoenix-secondary">Inativa</span>
                        @endif
                    </div>

                    <div class="border-top border-dashed pt-3">
                        <div class="mb-2"><span class="text-body-tertiary">Cliente:</span> <span class="fw-semibold">{{ $site->customer?->name ?? '-' }}</span></div>
                        <div class="mb-2"><span class="text-body-tertiary">Contacto:</span> <span class="fw-semibold">{{ $site->customerContact?->name ?? '-' }}</span></div>
                        <div class="mb-2"><span class="text-body-tertiary">Responsavel:</span> <span class="fw-semibold">{{ $site->assignedUser?->name ?? '-' }}</span></div>
                        <div class="mb-2"><span class="text-body-tertiary">Criado por:</span> <span class="fw-semibold">{{ $site->creator?->name ?? '-' }}</span></div>
                        <div><span class="text-body-tertiary">Criado em:</span> <span class="fw-semibold">{{ optional($site->created_at)->format('Y-m-d H:i') }}</span></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-xxl-8">
            <div class="row g-4">
                <div class="col-12 col-md-6">
                    <div class="card h-100">
                        <div class="card-header bg-body-tertiary">
                            <h5 class="mb-0">Dados gerais</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-2"><span class="text-body-tertiary">Inicio planeado:</span> <span class="fw-semibold">{{ optional($site->planned_start_date)->format('Y-m-d') ?? '-' }}</span></div>
                            <div class="mb-2"><span class="text-body-tertiary">Fim planeado:</span> <span class="fw-semibold">{{ optional($site->planned_end_date)->format('Y-m-d') ?? '-' }}</span></div>
                            <div class="mb-2"><span class="text-body-tertiary">Inicio real:</span> <span class="fw-semibold">{{ optional($site->actual_start_date)->format('Y-m-d') ?? '-' }}</span></div>
                            <div><span class="text-body-tertiary">Fim real:</span> <span class="fw-semibold">{{ optional($site->actual_end_date)->format('Y-m-d') ?? '-' }}</span></div>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-md-6">
                    <div class="card h-100">
                        <div class="card-header bg-body-tertiary">
                            <h5 class="mb-0">Morada e localizacao</h5>
                        </div>
                        <div class="card-body">
                            <p class="mb-2">{{ $site->address ?: '-' }}</p>
                            <p class="mb-2">{{ $site->postal_code ?: '-' }}</p>
                            <p class="mb-2">{{ $site->locality ?: '-' }}{{ $site->city ? ' / '.$site->city : '' }}</p>
                            <p class="mb-0">{{ $site->country?->name ?: '-' }}</p>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-md-6">
                    <div class="card h-100">
                        <div class="card-header bg-body-tertiary">
                            <h5 class="mb-0">Ligacao comercial</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-2"><span class="text-body-tertiary">Cliente:</span> <span class="fw-semibold">{{ $site->customer?->name ?? '-' }}</span></div>
                            <div class="mb-2"><span class="text-body-tertiary">Contacto:</span> <span class="fw-semibold">{{ $site->customerContact?->name ?? '-' }}</span></div>
                            <div>
                                <span class="text-body-tertiary">Orcamento:</span>
                                @if ($site->quote)
                                    <a href="{{ route('admin.quotes.show', $site->quote->id) }}" class="fw-semibold">{{ $site->quote->number }}</a>
                                    <span class="text-body-tertiary">({{ $quoteStatusLabels[$site->quote->status] ?? $site->quote->status }})</span>
                                @else
                                    <span class="fw-semibold">-</span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-md-6">
                    <div class="card h-100">
                        <div class="card-header bg-body-tertiary">
                            <h5 class="mb-0">Observacoes</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <div class="text-body-tertiary fs-9">Descricao</div>
                                <div>{{ $site->description ?: '-' }}</div>
                            </div>
                            <div class="mb-0">
                                <div class="text-body-tertiary fs-9">Notas internas</div>
                                <div>{{ $site->internal_notes ?: '-' }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        @if ($canViewMaterialUsages ?? false)
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-body-tertiary d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Consumo de material</h5>
                        <div class="d-flex gap-2">
                            <a href="{{ route('admin.construction-sites.material-usages.index', $site->id) }}" class="btn btn-phoenix-secondary btn-sm">Ver todos os consumos</a>
                            @if (($canCreateMaterialUsages ?? false) === true)
                                <a href="{{ route('admin.construction-sites.material-usages.create', $site->id) }}" class="btn btn-primary btn-sm">Novo consumo</a>
                            @endif
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row g-3 mb-3">
                            <div class="col-12 col-md-3">
                                <div class="border rounded p-3 h-100">
                                    <div class="text-body-tertiary fs-9">Registos</div>
                                    <div class="fw-semibold fs-8">{{ number_format((int) ($materialUsageSummary['total_usages'] ?? 0), 0, ',', '.') }}</div>
                                </div>
                            </div>
                            <div class="col-12 col-md-3">
                                <div class="border rounded p-3 h-100">
                                    <div class="text-body-tertiary fs-9">Registos fechados</div>
                                    <div class="fw-semibold fs-8">{{ number_format((int) ($materialUsageSummary['posted_usages'] ?? 0), 0, ',', '.') }}</div>
                                </div>
                            </div>
                            <div class="col-12 col-md-3">
                                <div class="border rounded p-3 h-100">
                                    <div class="text-body-tertiary fs-9">Linhas consumidas</div>
                                    <div class="fw-semibold fs-8">{{ number_format((int) ($materialUsageSummary['posted_lines'] ?? 0), 0, ',', '.') }}</div>
                                </div>
                            </div>
                            <div class="col-12 col-md-3">
                                <div class="border rounded p-3 h-100">
                                    <div class="text-body-tertiary fs-9">Custo estimado</div>
                                    <div class="fw-semibold fs-8">{{ number_format((float) ($materialUsageSummary['posted_estimated_cost'] ?? 0), 2, ',', '.') }} EUR</div>
                                </div>
                            </div>
                        </div>

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
                                    @forelse ($recentMaterialUsages as $usage)
                                        <tr>
                                            <td class="ps-3 fw-semibold">{{ $usage->number }}</td>
                                            <td>{{ optional($usage->usage_date)->format('Y-m-d') ?? '-' }}</td>
                                            <td>
                                                <span class="badge badge-phoenix {{ $usage->statusBadgeClass() }}">
                                                    {{ $materialUsageStatusLabels[$usage->status] ?? $usage->status }}
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
                                            <td colspan="6" class="text-center py-4 text-body-tertiary">Sem consumos de material registados.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        @if ($canViewLogs ?? false)
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-body-tertiary d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Diario de obra</h5>
                        <div class="d-flex gap-2">
                            <a href="{{ route('admin.construction-sites.logs.index', $site->id) }}" class="btn btn-phoenix-secondary btn-sm">Ver todos os logs</a>
                            <a href="{{ route('admin.construction-sites.logs.create', $site->id) }}" class="btn btn-primary btn-sm">Novo registo</a>
                        </div>
                    </div>
                    <div class="card-body">
                        @php($logTypeLabels = \App\Models\ConstructionSiteLog::typeLabels())
                        @forelse ($recentLogs as $log)
                            <div class="border rounded p-3 mb-2">
                                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                                    <div>
                                        <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                                            <span class="badge badge-phoenix {{ $log->typeBadgeClass() }}">{{ $logTypeLabels[$log->type] ?? $log->type }}</span>
                                            @if ($log->is_important)
                                                <span class="badge badge-phoenix badge-phoenix-danger">Importante</span>
                                            @endif
                                            <span class="text-body-tertiary">{{ optional($log->log_date)->format('Y-m-d') }}</span>
                                        </div>
                                        <div class="fw-semibold">{{ $log->title }}</div>
                                        <div class="text-body-tertiary small">Por {{ $log->creator?->name ?? '-' }}</div>
                                        <div class="text-body mt-1">{{ \Illuminate\Support\Str::limit($log->description, 180) }}</div>
                                    </div>
                                    <div class="d-flex gap-2">
                                        <a href="{{ route('admin.construction-sites.logs.show', [$site->id, $log->id]) }}" class="btn btn-phoenix-secondary btn-sm">Ver</a>
                                        <a href="{{ route('admin.construction-sites.logs.edit', [$site->id, $log->id]) }}" class="btn btn-phoenix-secondary btn-sm">Editar</a>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="text-body-tertiary">Sem registos no diario desta obra.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        @endif

        <div class="col-12 col-xl-6">
            <div class="card h-100">
                <div class="card-header bg-body-tertiary">
                    <h5 class="mb-0">Imagens</h5>
                </div>
                <div class="card-body">
                    @forelse ($site->images as $image)
                        <div class="d-flex justify-content-between align-items-center border rounded p-2 mb-2">
                            <a href="{{ route('admin.construction-sites.images.show', [$site->id, $image->id]) }}" target="_blank" rel="noopener noreferrer">{{ $image->original_name }}</a>
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
                    @forelse ($site->files as $file)
                        <div class="border rounded p-2 mb-2">
                            <a href="{{ route('admin.construction-sites.files.download', [$site->id, $file->id]) }}" class="fw-semibold">{{ $file->original_name }}</a>
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
