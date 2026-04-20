@extends('layouts.admin')

@section('title', 'Dashboard de Orcamentos')
@section('page_title', 'Dashboard de Orcamentos')
@section('page_subtitle', 'Visao comercial do pipeline de propostas')

@section('page_actions')
    <a href="{{ route('admin.quotes.index') }}" class="btn btn-phoenix-secondary btn-sm">Lista de orcamentos</a>
    <a href="{{ route('admin.quotes.create') }}" class="btn btn-primary btn-sm">Novo orcamento</a>
@endsection

@section('breadcrumbs')
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.quotes.index') }}">Orcamentos</a></li>
        <li class="breadcrumb-item active" aria-current="page">Dashboard</li>
    </ol>
@endsection

@section('content')
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="card mb-4">
        <div class="card-header bg-body-tertiary">
            <h5 class="mb-0">Filtros</h5>
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route('admin.quotes.dashboard') }}" class="row g-3 align-items-end">
                <div class="col-12 col-md-3">
                    <label for="period" class="form-label">Periodo</label>
                    <select id="period" name="period" class="form-select">
                        @foreach ($periodOptions as $periodKey => $periodLabel)
                            <option value="{{ $periodKey }}" @selected(($filters['period'] ?? 'this_year') === $periodKey)>{{ $periodLabel }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-3">
                    <label for="status" class="form-label">Estado</label>
                    <select id="status" name="status" class="form-select">
                        <option value="">Todos</option>
                        @foreach ($statusLabels as $statusKey => $statusLabel)
                            <option value="{{ $statusKey }}" @selected(($filters['status'] ?? null) === $statusKey)>{{ $statusLabel }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-3">
                    <label for="customer_id" class="form-label">Cliente</label>
                    <select id="customer_id" name="customer_id" class="form-select">
                        <option value="">Todos</option>
                        @foreach ($customerOptions as $customer)
                            <option value="{{ $customer->id }}" @selected((int) ($filters['customer_id'] ?? 0) === (int) $customer->id)>{{ $customer->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-3">
                    <label for="assigned_user_id" class="form-label">Responsavel</label>
                    <select id="assigned_user_id" name="assigned_user_id" class="form-select">
                        <option value="">Todos</option>
                        @foreach ($assignedUserOptions as $assignedUser)
                            <option value="{{ $assignedUser->id }}" @selected((int) ($filters['assigned_user_id'] ?? 0) === (int) $assignedUser->id)>{{ $assignedUser->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-3">
                    <label for="date_from" class="form-label">Data inicial</label>
                    <input type="date" id="date_from" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="form-control">
                </div>
                <div class="col-12 col-md-3">
                    <label for="date_to" class="form-label">Data final</label>
                    <input type="date" id="date_to" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="form-control">
                </div>
                <div class="col-12 col-md-6 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-fill">Aplicar filtros</button>
                    <a href="{{ route('admin.quotes.dashboard') }}" class="btn btn-phoenix-secondary flex-fill">Limpar</a>
                </div>
            </form>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-12 col-xl-6">
            <div class="card h-100">
                <div class="card-header bg-body-tertiary">
                    <h5 class="mb-0">Visao geral</h5>
                </div>
                <div class="card-body">
                    <div class="row g-2">
                        <div class="col-6">
                            <div class="border rounded-2 p-2 h-100">
                                <div class="text-body-tertiary fs-9">Total de orcamentos</div>
                                <div class="fw-bold fs-7">{{ number_format((float) $kpis['total_quotes'], 0, ',', '.') }}</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="border rounded-2 p-2 h-100">
                                <div class="text-body-tertiary fs-9">Rascunho</div>
                                <div class="fw-bold fs-7">{{ number_format((float) $kpis['counts']['draft'], 0, ',', '.') }}</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="border rounded-2 p-2 h-100">
                                <div class="text-body-tertiary fs-9">Enviado</div>
                                <div class="fw-bold fs-7">{{ number_format((float) $kpis['counts']['sent'], 0, ',', '.') }}</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="border rounded-2 p-2 h-100">
                                <div class="text-body-tertiary fs-9">Visto</div>
                                <div class="fw-bold fs-7">{{ number_format((float) $kpis['counts']['viewed'], 0, ',', '.') }}</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="border rounded-2 p-2 h-100">
                                <div class="text-body-tertiary fs-9">Aprovado</div>
                                <div class="fw-bold fs-7 text-success">{{ number_format((float) $kpis['counts']['approved'], 0, ',', '.') }}</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="border rounded-2 p-2 h-100">
                                <div class="text-body-tertiary fs-9">Rejeitado</div>
                                <div class="fw-bold fs-7 text-danger">{{ number_format((float) $kpis['counts']['rejected'], 0, ',', '.') }}</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="border rounded-2 p-2 h-100">
                                <div class="text-body-tertiary fs-9">Expirado</div>
                                <div class="fw-bold fs-7 text-danger">{{ number_format((float) $kpis['counts']['expired'], 0, ',', '.') }}</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="border rounded-2 p-2 h-100">
                                <div class="text-body-tertiary fs-9">Cancelado</div>
                                <div class="fw-bold fs-7 text-danger">{{ number_format((float) $kpis['counts']['cancelled'], 0, ',', '.') }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-6">
            <div class="card h-100">
                <div class="card-header bg-body-tertiary">
                    <h5 class="mb-0">Valor comercial</h5>
                </div>
                <div class="card-body">
                    <div class="row g-2">
                        <div class="col-12">
                            <div class="border rounded-2 p-2">
                                <div class="text-body-tertiary fs-9">Valor total em draft</div>
                                <div class="fw-bold fs-7">{{ number_format((float) $kpis['values']['draft'], 2, ',', '.') }} EUR</div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="border rounded-2 p-2">
                                <div class="text-body-tertiary fs-9">Valor em aberto (draft + sent + viewed)</div>
                                <div class="fw-bold fs-7">{{ number_format((float) $kpis['values']['open'], 2, ',', '.') }} EUR</div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="border rounded-2 p-2">
                                <div class="text-body-tertiary fs-9">Valor total aprovado</div>
                                <div class="fw-bold fs-7 text-success">{{ number_format((float) $kpis['values']['approved'], 2, ',', '.') }} EUR</div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="border rounded-2 p-2">
                                <div class="text-body-tertiary fs-9">Valor perdido (rejeitado + cancelado)</div>
                                <div class="fw-bold fs-7 text-danger">{{ number_format((float) $kpis['values']['lost'], 2, ',', '.') }} EUR</div>
                            </div>
                        </div>
                        <div class="col-12 col-md-6">
                            <div class="border rounded-2 p-2 h-100">
                                <div class="text-body-tertiary fs-9">Ticket medio aprovado</div>
                                <div class="fw-bold fs-7">{{ number_format((float) $kpis['values']['approved_avg_ticket'], 2, ',', '.') }} EUR</div>
                            </div>
                        </div>
                        <div class="col-12 col-md-6">
                            <div class="border rounded-2 p-2 h-100">
                                <div class="text-body-tertiary fs-9">Taxa de aprovacao</div>
                                <div class="fw-bold fs-7">{{ number_format((float) $kpis['approval_rate'], 2, ',', '.') }}%</div>
                                <div class="fs-10 text-body-tertiary">{{ $kpis['approval_rate_formula'] }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header bg-body-tertiary">
            <h5 class="mb-0">Resumo por estado</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm fs-9 mb-0">
                    <thead class="bg-body-tertiary">
                        <tr>
                            <th class="ps-3">Estado</th>
                            <th>Quantidade</th>
                            <th class="pe-3 text-end">Valor total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($statusSummary as $stateRow)
                            <tr>
                                <td class="ps-3">
                                    <span class="badge badge-phoenix {{ $statusBadgeClasses[$stateRow['status']] ?? 'badge-phoenix-secondary' }}">
                                        {{ $stateRow['label'] }}
                                    </span>
                                </td>
                                <td>{{ number_format((float) $stateRow['count'], 0, ',', '.') }}</td>
                                <td class="pe-3 text-end fw-semibold">{{ number_format((float) $stateRow['value'], 2, ',', '.') }} EUR</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-12 col-xxl-6">
            <div class="card h-100">
                <div class="card-header bg-body-tertiary">
                    <h5 class="mb-0">Orcamentos recentes</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm fs-9 mb-0">
                            <thead class="bg-body-tertiary">
                                <tr>
                                    <th class="ps-3">Numero</th>
                                    <th>Cliente</th>
                                    <th>Data</th>
                                    <th>Validade</th>
                                    <th>Estado</th>
                                    <th>Total</th>
                                    <th>Responsavel</th>
                                    <th class="pe-3 text-end">Acao</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($recentQuotes as $quote)
                                    @php
                                        $validityExpired = $quote->valid_until && $quote->valid_until->lt($today) && in_array($quote->status, $openStatuses, true);
                                    @endphp
                                    <tr>
                                        <td class="ps-3 fw-semibold">{{ $quote->number }}</td>
                                        <td>{{ $quote->customer?->name ?? '-' }}</td>
                                        <td>{{ optional($quote->issue_date)->format('Y-m-d') }}</td>
                                        <td>
                                            {{ optional($quote->valid_until)->format('Y-m-d') ?? '-' }}
                                            @if ($validityExpired)
                                                <div><span class="badge badge-phoenix badge-phoenix-warning">Validade expirada</span></div>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="badge badge-phoenix {{ $quote->statusBadgeClass() }}">
                                                {{ $statusLabels[$quote->status] ?? $quote->status }}
                                            </span>
                                        </td>
                                        <td>{{ number_format((float) $quote->grand_total, 2, ',', '.') }} {{ $quote->currency }}</td>
                                        <td>{{ $quote->assignedUser?->name ?? '-' }}</td>
                                        <td class="pe-3 text-end">
                                            <a href="{{ route('admin.quotes.show', $quote->id) }}" class="btn btn-phoenix-secondary btn-sm">Abrir</a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center py-4 text-body-tertiary">Sem orcamentos no periodo atual.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-xxl-6">
            <div class="card h-100">
                <div class="card-header bg-body-tertiary">
                    <h5 class="mb-0">Follow-ups pendentes</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm fs-9 mb-0">
                            <thead class="bg-body-tertiary">
                                <tr>
                                    <th class="ps-3">Numero</th>
                                    <th>Cliente</th>
                                    <th>Follow-up</th>
                                    <th>Estado</th>
                                    <th>Total</th>
                                    <th>Responsavel</th>
                                    <th class="pe-3 text-end">Acao</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($followUpQuotes as $quote)
                                    @php
                                        $isOverdue = $quote->follow_up_date && $quote->follow_up_date->lt($today);
                                        $isToday = $quote->follow_up_date && $quote->follow_up_date->isSameDay($today);
                                    @endphp
                                    <tr>
                                        <td class="ps-3 fw-semibold">{{ $quote->number }}</td>
                                        <td>{{ $quote->customer?->name ?? '-' }}</td>
                                        <td>
                                            {{ optional($quote->follow_up_date)->format('Y-m-d') ?? '-' }}
                                            @if ($isOverdue)
                                                <div><span class="badge badge-phoenix badge-phoenix-danger">Atrasado</span></div>
                                            @elseif ($isToday)
                                                <div><span class="badge badge-phoenix badge-phoenix-warning">Hoje</span></div>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="badge badge-phoenix {{ $quote->statusBadgeClass() }}">
                                                {{ $statusLabels[$quote->status] ?? $quote->status }}
                                            </span>
                                        </td>
                                        <td>{{ number_format((float) $quote->grand_total, 2, ',', '.') }} {{ $quote->currency }}</td>
                                        <td>{{ $quote->assignedUser?->name ?? '-' }}</td>
                                        <td class="pe-3 text-end">
                                            <a href="{{ route('admin.quotes.show', $quote->id) }}" class="btn btn-phoenix-secondary btn-sm">Abrir</a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center py-4 text-body-tertiary">Sem follow-ups pendentes.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header bg-body-tertiary">
            <h5 class="mb-0">Propostas em aberto</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm fs-9 mb-0">
                    <thead class="bg-body-tertiary">
                        <tr>
                            <th class="ps-3">Numero</th>
                            <th>Cliente</th>
                            <th>Dias desde emissao</th>
                            <th>Validade</th>
                            <th>Estado</th>
                            <th>Total</th>
                            <th>Responsavel</th>
                            <th class="pe-3 text-end">Acao</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($openQuotes as $quote)
                            @php
                                $daysSinceIssue = $quote->issue_date ? $quote->issue_date->diffInDays($today) : null;
                                $validityExpired = $quote->valid_until && $quote->valid_until->lt($today) && in_array($quote->status, $openStatuses, true);
                            @endphp
                            <tr>
                                <td class="ps-3 fw-semibold">{{ $quote->number }}</td>
                                <td>{{ $quote->customer?->name ?? '-' }}</td>
                                <td>{{ $daysSinceIssue !== null ? number_format((float) $daysSinceIssue, 0, ',', '.') : '-' }}</td>
                                <td>
                                    {{ optional($quote->valid_until)->format('Y-m-d') ?? '-' }}
                                    @if ($validityExpired)
                                        <div><span class="badge badge-phoenix badge-phoenix-warning">Validade expirada</span></div>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge badge-phoenix {{ $quote->statusBadgeClass() }}">
                                        {{ $statusLabels[$quote->status] ?? $quote->status }}
                                    </span>
                                </td>
                                <td>{{ number_format((float) $quote->grand_total, 2, ',', '.') }} {{ $quote->currency }}</td>
                                <td>{{ $quote->assignedUser?->name ?? '-' }}</td>
                                <td class="pe-3 text-end">
                                    <a href="{{ route('admin.quotes.show', $quote->id) }}" class="btn btn-phoenix-secondary btn-sm">Abrir</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-4 text-body-tertiary">Sem propostas em aberto para os filtros atuais.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-12 col-xxl-6">
            <div class="card h-100">
                <div class="card-header bg-body-tertiary">
                    <h5 class="mb-0">Performance temporal (mensal)</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm fs-9 mb-0">
                            <thead class="bg-body-tertiary">
                                <tr>
                                    <th class="ps-3">Periodo</th>
                                    <th>Orcamentos criados</th>
                                    <th>Aprovados</th>
                                    <th class="pe-3 text-end">Valor aprovado</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($temporalPerformance as $periodRow)
                                    <tr>
                                        <td class="ps-3 fw-semibold">{{ $periodRow['month_label'] }}</td>
                                        <td>{{ number_format((float) $periodRow['created_count'], 0, ',', '.') }}</td>
                                        <td>{{ number_format((float) $periodRow['approved_count'], 0, ',', '.') }}</td>
                                        <td class="pe-3 text-end">{{ number_format((float) $periodRow['approved_value'], 2, ',', '.') }} EUR</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center py-4 text-body-tertiary">Sem dados temporais para o periodo.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-xxl-6">
            <div class="card h-100">
                <div class="card-header bg-body-tertiary">
                    <h5 class="mb-0">Performance por responsavel</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm fs-9 mb-0">
                            <thead class="bg-body-tertiary">
                                <tr>
                                    <th class="ps-3">Responsavel</th>
                                    <th>Nr orcamentos</th>
                                    <th>Valor total</th>
                                    <th>Aprovados</th>
                                    <th class="pe-3 text-end">Taxa aprovacao</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($responsiblePerformance as $responsibleRow)
                                    <tr>
                                        <td class="ps-3 fw-semibold">{{ $responsibleRow['name'] }}</td>
                                        <td>{{ number_format((float) $responsibleRow['quotes_count'], 0, ',', '.') }}</td>
                                        <td>{{ number_format((float) $responsibleRow['total_value'], 2, ',', '.') }} EUR</td>
                                        <td>{{ number_format((float) $responsibleRow['approved_count'], 0, ',', '.') }}</td>
                                        <td class="pe-3 text-end">{{ number_format((float) $responsibleRow['approval_rate'], 2, ',', '.') }}%</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center py-4 text-body-tertiary">Sem dados suficientes de responsaveis para os filtros atuais.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
