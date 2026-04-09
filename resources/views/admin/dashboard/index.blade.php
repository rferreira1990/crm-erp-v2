@extends('layouts.admin')

@section('title', 'Dashboard')
@section('page_title', 'Dashboard')
@section('page_subtitle', 'Cleanup v1 com base Phoenix')

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
    <section class="mb-4">
        <div class="row g-3">
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card h-100">
                    <div class="card-body">
                        <p class="text-body-tertiary mb-1">Orders</p>
                        <h3 class="mb-0">1,247</h3>
                        <p class="text-success mb-0 fs-9">+8.2% vs mes anterior</p>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card h-100">
                    <div class="card-body">
                        <p class="text-body-tertiary mb-1">Revenue</p>
                        <h3 class="mb-0">EUR 48,320</h3>
                        <p class="text-success mb-0 fs-9">+5.4% vs mes anterior</p>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card h-100">
                    <div class="card-body">
                        <p class="text-body-tertiary mb-1">Customers</p>
                        <h3 class="mb-0">3,891</h3>
                        <p class="text-danger mb-0 fs-9">-1.1% vs mes anterior</p>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card h-100">
                    <div class="card-body">
                        <p class="text-body-tertiary mb-1">Conversion</p>
                        <h3 class="mb-0">3.8%</h3>
                        <p class="text-success mb-0 fs-9">+0.3 p.p.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="mb-4">
        <div class="card">
            <div class="card-header bg-body-tertiary d-flex align-items-center justify-content-between">
                <h5 class="mb-0">Plano de Cleanup</h5>
                <span class="badge badge-phoenix badge-phoenix-info">Fase 1</span>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12 col-lg-6">
                        <h6 class="mb-2">Ja concluido</h6>
                        <ul class="mb-0">
                            <li>Snapshot integral do template em <code>version_old</code></li>
                            <li>Dashboard principal voltou a layout Blade modular</li>
                            <li>Rota dedicada para comparacao visual</li>
                        </ul>
                    </div>
                    <div class="col-12 col-lg-6">
                        <h6 class="mb-2">Proximos passos</h6>
                        <ul class="mb-0">
                            <li>Extrair sidebar Phoenix para componente reutilizavel</li>
                            <li>Extrair topbar Phoenix para componente reutilizavel</li>
                            <li>Remover scripts demo nao necessarios (chat, customizer, etc.)</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section>
        <div class="card">
            <div class="card-header bg-body-tertiary">
                <h5 class="mb-0">Referencia visual</h5>
            </div>
            <div class="card-body">
                <p class="mb-3">
                    A referencia completa foi guardada para consulta e comparacao durante a refatoracao.
                </p>
                <a href="{{ route('admin.dashboard.version_old') }}" class="btn btn-primary" target="_blank" rel="noopener">
                    Ver dashboard original (version_old)
                </a>
            </div>
        </div>
    </section>
@endsection
