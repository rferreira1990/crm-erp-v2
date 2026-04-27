@extends('layouts.admin')

@section('title', 'Dashboard')
@section('page_title', 'Dashboard')
@section('page_subtitle', 'Visao executiva do CRM/ERP')

@section('page_actions')
    <a href="{{ route('admin.dashboard.version_old') }}" class="btn btn-phoenix-secondary btn-sm" target="_blank" rel="noopener">
        Abrir version_old
    </a>
@endsection

@section('breadcrumbs')
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li>
        <li class="breadcrumb-item active" aria-current="page">Dashboard</li>
    </ol>
@endsection

@section('content')
    @php
        $fmtMoney = fn (float|int|null $value): string => number_format((float) ($value ?? 0), 2, ',', '.').' €';
        $fmtCount = fn (int|float|null $value): string => number_format((float) ($value ?? 0), 0, ',', '.');
        $kpi = $kpis ?? [];
    @endphp

    <div class="card mb-4">
        <div class="card-header bg-body-tertiary">
            <h5 class="mb-0">Filtros globais</h5>
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route('admin.dashboard') }}" class="row g-3 align-items-end">
                <div class="col-12 col-md-3">
                    <label for="period" class="form-label">Periodo</label>
                    <select id="period" name="period" class="form-select">
                        @foreach (($options['period_options'] ?? []) as $periodKey => $periodLabel)
                            <option value="{{ $periodKey }}" @selected(($filters['period'] ?? '') === $periodKey)>{{ $periodLabel }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-2">
                    <label for="date_from" class="form-label">Data de</label>
                    <input type="date" id="date_from" name="date_from" class="form-control" value="{{ $filters['date_from'] ?? '' }}">
                </div>
                <div class="col-12 col-md-2">
                    <label for="date_to" class="form-label">Data ate</label>
                    <input type="date" id="date_to" name="date_to" class="form-control" value="{{ $filters['date_to'] ?? '' }}">
                </div>
                <div class="col-12 col-md-3">
                    <label for="customer_id" class="form-label">Cliente</label>
                    <select id="customer_id" name="customer_id" class="form-select">
                        <option value="">Todos</option>
                        @foreach (($options['customers'] ?? collect()) as $customer)
                            <option value="{{ $customer->id }}" @selected((string) ($filters['customer_id'] ?? '') === (string) $customer->id)>{{ $customer->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-2">
                    <label for="responsible_id" class="form-label">Responsavel</label>
                    <select id="responsible_id" name="responsible_id" class="form-select">
                        <option value="">Todos</option>
                        @foreach (($options['responsibles'] ?? collect()) as $responsible)
                            <option value="{{ $responsible->id }}" @selected((string) ($filters['responsible_id'] ?? '') === (string) $responsible->id)>{{ $responsible->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-fill">Aplicar</button>
                    <a href="{{ route('admin.dashboard') }}" class="btn btn-phoenix-secondary flex-fill">Limpar</a>
                </div>
                <div class="col-12 col-md-9 text-body-tertiary fs-9">
                    Filtro ativo: {{ $filters['period_label'] ?? '-' }}
                </div>
            </form>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card h-100">
                <div class="card-body">
                    <p class="text-body-tertiary mb-1">Total vendido no mes</p>
                    <h4 class="mb-0">{{ $fmtMoney($kpi['sold_month'] ?? 0) }}</h4>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card h-100">
                <div class="card-body">
                    <p class="text-body-tertiary mb-1">Total vendido no ano</p>
                    <h4 class="mb-0">{{ $fmtMoney($kpi['sold_year'] ?? 0) }}</h4>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card h-100">
                <div class="card-body">
                    <p class="text-body-tertiary mb-1">Orcamentos em aberto</p>
                    <h4 class="mb-0">{{ $fmtCount($kpi['quotes_open'] ?? 0) }}</h4>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card h-100">
                <div class="card-body">
                    <p class="text-body-tertiary mb-1">Taxa conversao orcamentos</p>
                    <h4 class="mb-0">{{ number_format((float) ($kpi['quote_conversion_rate'] ?? 0), 2, ',', '.') }}%</h4>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card h-100">
                <div class="card-body">
                    <p class="text-body-tertiary mb-1">Obras ativas</p>
                    <h4 class="mb-0">{{ $fmtCount($kpi['active_construction_sites'] ?? 0) }}</h4>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card h-100">
                <div class="card-body">
                    <p class="text-body-tertiary mb-1">Valor em aberto clientes</p>
                    <h4 class="mb-0 text-danger">{{ $fmtMoney($kpi['open_customer_value'] ?? 0) }}</h4>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card h-100">
                <div class="card-body">
                    <p class="text-body-tertiary mb-1">Total recebido no mes</p>
                    <h4 class="mb-0 text-success">{{ $fmtMoney($kpi['received_month'] ?? 0) }}</h4>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card h-100">
                <div class="card-body">
                    <p class="text-body-tertiary mb-1">Documentos de Venda por receber</p>
                    <h4 class="mb-0">{{ $fmtCount($kpi['documents_pending_payment'] ?? 0) }}</h4>
                </div>
            </div>
        </div>

        @if ($canViewEconomicMargins)
            <div class="col-12 col-xl-3">
                <div class="card h-100 border-warning-subtle">
                    <div class="card-body">
                        <p class="text-body-tertiary mb-1">Margem estimada das obras</p>
                        <h4 class="mb-0 {{ (float) ($kpi['estimated_works_margin'] ?? 0) >= 0 ? 'text-success' : 'text-danger' }}">
                            {{ $fmtMoney($kpi['estimated_works_margin'] ?? 0) }}
                        </h4>
                    </div>
                </div>
            </div>
        @endif
    </div>

    <div class="row g-4 mb-4">
        <div class="col-12 col-xl-8">
            <div class="card h-100">
                <div class="card-header bg-body-tertiary">
                    <h5 class="mb-0">Vendas por mes (ultimos 12 meses)</h5>
                </div>
                <div class="card-body">
                    <div id="dashboard-sales-chart" style="height: 320px;"></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-xl-4">
            <div class="card h-100">
                <div class="card-header bg-body-tertiary">
                    <h5 class="mb-0">Orcamentos por estado</h5>
                </div>
                <div class="card-body">
                    <div id="dashboard-quotes-chart" style="height: 320px;"></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-xl-6">
            <div class="card h-100">
                <div class="card-header bg-body-tertiary">
                    <h5 class="mb-0">Obras por estado</h5>
                </div>
                <div class="card-body">
                    <div id="dashboard-works-chart" style="height: 320px;"></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-xl-6">
            <div class="card h-100">
                <div class="card-header bg-body-tertiary">
                    <h5 class="mb-0">Recebimentos por mes</h5>
                </div>
                <div class="card-body">
                    <div id="dashboard-receipts-chart" style="height: 320px;"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-12 col-xl-6">
            <div class="card h-100">
                <div class="card-header bg-body-tertiary d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Ultimos Documentos de Venda</h5>
                    <a href="{{ route('admin.sales-documents.index') }}" class="btn btn-phoenix-secondary btn-sm">Ver todos</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm fs-9 mb-0">
                            <thead class="bg-body-tertiary">
                                <tr>
                                    <th class="ps-3">Numero</th>
                                    <th>Cliente</th>
                                    <th>Data</th>
                                    <th>Total</th>
                                    <th>Pagamento</th>
                                    <th class="text-end pe-3">Acoes</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse (($recent['sales_documents'] ?? collect()) as $document)
                                    <tr>
                                        <td class="ps-3 fw-semibold">{{ $document->number }}</td>
                                        <td>{{ $document->customer?->name ?? ($document->customer_name_snapshot ?? '-') }}</td>
                                        <td>{{ $document->issue_date?->format('Y-m-d') ?? '-' }}</td>
                                        <td>{{ number_format((float) $document->grand_total, 2, ',', '.') }} {{ $document->currency }}</td>
                                        <td>
                                            <span class="badge badge-phoenix {{ $document->paymentStatusBadgeClass() }}">
                                                {{ $salesDocumentPaymentStatusLabels[$document->payment_status] ?? $document->paymentStatusLabel() }}
                                            </span>
                                        </td>
                                        <td class="text-end pe-3">
                                            <a href="{{ route('admin.sales-documents.show', $document->id) }}" class="btn btn-phoenix-secondary btn-sm">Ficha</a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center py-4 text-body-tertiary">Sem registos.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-6">
            <div class="card h-100">
                <div class="card-header bg-body-tertiary d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Ultimos orcamentos</h5>
                    <a href="{{ route('admin.quotes.index') }}" class="btn btn-phoenix-secondary btn-sm">Ver todos</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm fs-9 mb-0">
                            <thead class="bg-body-tertiary">
                                <tr>
                                    <th class="ps-3">Numero</th>
                                    <th>Cliente</th>
                                    <th>Estado</th>
                                    <th>Total</th>
                                    <th class="text-end pe-3">Acoes</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse (($recent['quotes'] ?? collect()) as $quote)
                                    <tr>
                                        <td class="ps-3 fw-semibold">{{ $quote->number }}</td>
                                        <td>{{ $quote->customer?->name ?? ($quote->customer_name ?? '-') }}</td>
                                        <td>
                                            <span class="badge badge-phoenix {{ $quote->statusBadgeClass() }}">
                                                {{ $quoteStatusLabels[$quote->status] ?? $quote->status }}
                                            </span>
                                        </td>
                                        <td>{{ number_format((float) $quote->grand_total, 2, ',', '.') }} {{ $quote->currency }}</td>
                                        <td class="text-end pe-3">
                                            <a href="{{ route('admin.quotes.show', $quote->id) }}" class="btn btn-phoenix-secondary btn-sm">Ficha</a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center py-4 text-body-tertiary">Sem registos.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-6">
            <div class="card h-100">
                <div class="card-header bg-body-tertiary d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Obras recentes/ativas</h5>
                    <a href="{{ route('admin.construction-sites.index') }}" class="btn btn-phoenix-secondary btn-sm">Ver todas</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm fs-9 mb-0">
                            <thead class="bg-body-tertiary">
                                <tr>
                                    <th class="ps-3">Codigo</th>
                                    <th>Cliente</th>
                                    <th>Estado</th>
                                    <th>Responsavel</th>
                                    <th>{{ $canViewEconomicMargins ? 'Custo est.' : 'Info.' }}</th>
                                    <th class="text-end pe-3">Acoes</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse (($recent['construction_sites'] ?? collect()) as $site)
                                    <tr>
                                        <td class="ps-3 fw-semibold">{{ $site->code }}</td>
                                        <td>{{ $site->customer?->name ?? '-' }}</td>
                                        <td>
                                            <span class="badge badge-phoenix {{ $site->statusBadgeClass() }}">
                                                {{ $constructionSiteStatusLabels[$site->status] ?? $site->status }}
                                            </span>
                                        </td>
                                        <td>{{ $site->assignedUser?->name ?? '-' }}</td>
                                        <td>
                                            @if ($canViewEconomicMargins)
                                                {{ $fmtMoney($recent['construction_sites_estimated_costs'][$site->id] ?? 0) }}
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td class="text-end pe-3">
                                            <a href="{{ route('admin.construction-sites.show', $site->id) }}" class="btn btn-phoenix-secondary btn-sm">Ficha</a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center py-4 text-body-tertiary">Sem registos.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-6">
            <div class="card h-100">
                <div class="card-header bg-body-tertiary d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Recibos recentes</h5>
                    <a href="{{ route('admin.sales-document-receipts.index') }}" class="btn btn-phoenix-secondary btn-sm">Ver todos</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm fs-9 mb-0">
                            <thead class="bg-body-tertiary">
                                <tr>
                                    <th class="ps-3">Numero</th>
                                    <th>Cliente</th>
                                    <th>Valor</th>
                                    <th>Data</th>
                                    <th>Estado</th>
                                    <th class="text-end pe-3">Acoes</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse (($recent['receipts'] ?? collect()) as $receipt)
                                    <tr>
                                        <td class="ps-3 fw-semibold">{{ $receipt->number }}</td>
                                        <td>{{ $receipt->customer?->name ?? '-' }}</td>
                                        <td>{{ number_format((float) $receipt->amount, 2, ',', '.') }} €</td>
                                        <td>{{ $receipt->receipt_date?->format('Y-m-d') ?? '-' }}</td>
                                        <td>
                                            <span class="badge badge-phoenix {{ $receipt->statusBadgeClass() }}">
                                                {{ $receiptStatusLabels[$receipt->status] ?? $receipt->status }}
                                            </span>
                                        </td>
                                        <td class="text-end pe-3">
                                            <a href="{{ route('admin.sales-document-receipts.show', $receipt->id) }}" class="btn btn-phoenix-secondary btn-sm">Ficha</a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center py-4 text-body-tertiary">Sem registos.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-body-tertiary">
                    <h5 class="mb-0">Alertas operacionais</h5>
                </div>
                <div class="card-body">
                    <div class="d-flex flex-wrap gap-2">
                        <span class="badge badge-phoenix badge-phoenix-warning">Documentos vencidos/por receber: {{ $fmtCount($alerts['overdue_documents'] ?? 0) }}</span>
                        <span class="badge badge-phoenix badge-phoenix-info">Orcamentos sem resposta ({{ 7 }}d): {{ $fmtCount($alerts['quotes_without_response'] ?? 0) }}</span>
                        <span class="badge badge-phoenix badge-phoenix-danger">Stock baixo: {{ $fmtCount($alerts['low_stock_articles'] ?? 0) }}</span>
                        <span class="badge badge-phoenix badge-phoenix-secondary">POs pendentes de rececao: {{ $fmtCount($alerts['pending_purchase_order_receipts'] ?? 0) }}</span>
                        @if ($canViewEconomicMargins)
                            <span class="badge badge-phoenix badge-phoenix-danger">Obras acima do orcamento: {{ $fmtCount($alerts['works_over_budget'] ?? 0) }}</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('vendor/phoenix/vendors/echarts/echarts.min.js') }}"></script>
    <script>
        (function () {
            if (typeof window.echarts === 'undefined') {
                return;
            }

            const charts = {
                sales: {
                    el: document.getElementById('dashboard-sales-chart'),
                    labels: {{ \Illuminate\Support\Js::from($charts['sales_by_month']['labels'] ?? []) }},
                    values: {{ \Illuminate\Support\Js::from($charts['sales_by_month']['values'] ?? []) }},
                },
                quotes: {
                    el: document.getElementById('dashboard-quotes-chart'),
                    labels: {{ \Illuminate\Support\Js::from($charts['quotes_by_status']['labels'] ?? []) }},
                    values: {{ \Illuminate\Support\Js::from($charts['quotes_by_status']['values'] ?? []) }},
                },
                works: {
                    el: document.getElementById('dashboard-works-chart'),
                    labels: {{ \Illuminate\Support\Js::from($charts['works_by_status']['labels'] ?? []) }},
                    values: {{ \Illuminate\Support\Js::from($charts['works_by_status']['values'] ?? []) }},
                },
                receipts: {
                    el: document.getElementById('dashboard-receipts-chart'),
                    labels: {{ \Illuminate\Support\Js::from($charts['receipts_by_month']['labels'] ?? []) }},
                    values: {{ \Illuminate\Support\Js::from($charts['receipts_by_month']['values'] ?? []) }},
                },
            };

            const textColor = '#5e6e82';
            const gridColor = '#e3e6ed';

            if (charts.sales.el) {
                const salesChart = window.echarts.init(charts.sales.el);
                salesChart.setOption({
                    tooltip: { trigger: 'axis' },
                    grid: { left: 45, right: 20, top: 20, bottom: 55 },
                    xAxis: {
                        type: 'category',
                        data: charts.sales.labels,
                        axisLabel: { color: textColor, rotate: 35 },
                        axisLine: { lineStyle: { color: gridColor } },
                    },
                    yAxis: {
                        type: 'value',
                        axisLabel: { color: textColor },
                        splitLine: { lineStyle: { color: gridColor } },
                    },
                    series: [{
                        type: 'line',
                        data: charts.sales.values,
                        smooth: true,
                        areaStyle: { opacity: 0.15 },
                        lineStyle: { width: 3 },
                    }],
                });
            }

            const pieBuilder = function (config, colors) {
                if (!config.el) {
                    return;
                }

                const chart = window.echarts.init(config.el);
                chart.setOption({
                    tooltip: { trigger: 'item' },
                    legend: { bottom: 0, textStyle: { color: textColor } },
                    series: [{
                        type: 'pie',
                        radius: ['45%', '75%'],
                        avoidLabelOverlap: false,
                        label: { show: false },
                        data: config.labels.map(function (label, index) {
                            return { name: label, value: config.values[index] ?? 0 };
                        }),
                        color: colors,
                    }],
                });
            };

            pieBuilder(charts.quotes, ['#0d6efd', '#00d27a', '#f5803e', '#25b4e5', '#e63757', '#6f42c1', '#adb5bd']);
            pieBuilder(charts.works, ['#0d6efd', '#25b4e5', '#0dcaf0', '#f6c343', '#00d27a', '#e63757']);

            if (charts.receipts.el) {
                const receiptsChart = window.echarts.init(charts.receipts.el);
                receiptsChart.setOption({
                    tooltip: { trigger: 'axis' },
                    grid: { left: 45, right: 20, top: 20, bottom: 55 },
                    xAxis: {
                        type: 'category',
                        data: charts.receipts.labels,
                        axisLabel: { color: textColor, rotate: 35 },
                        axisLine: { lineStyle: { color: gridColor } },
                    },
                    yAxis: {
                        type: 'value',
                        axisLabel: { color: textColor },
                        splitLine: { lineStyle: { color: gridColor } },
                    },
                    series: [{
                        type: 'bar',
                        data: charts.receipts.values,
                        barMaxWidth: 26,
                    }],
                });
            }
        })();
    </script>
@endpush

