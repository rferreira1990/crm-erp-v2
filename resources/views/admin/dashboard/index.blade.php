@extends('layouts.admin')

@section('title', 'Dashboard')
@section('page_title', 'Dashboard')
@section('page_subtitle', 'Visão geral operacional do CRM/ERP')

@section('breadcrumbs')
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li>
        <li class="breadcrumb-item active" aria-current="page">Dashboard</li>
    </ol>
@endsection

@section('content')
    <section aria-label="Indicadores principais">
        <div class="row g-3">
            <div class="col-12 col-sm-6 col-xl-3">
                <article class="card h-100 shadow-sm border-0">
                    <div class="card-body">
                        <p class="text-uppercase text-body-secondary small mb-1">Clientes ativos</p>
                        <h2 class="h4 mb-0">0</h2>
                    </div>
                </article>
            </div>
            <div class="col-12 col-sm-6 col-xl-3">
                <article class="card h-100 shadow-sm border-0">
                    <div class="card-body">
                        <p class="text-uppercase text-body-secondary small mb-1">Produtos</p>
                        <h2 class="h4 mb-0">0</h2>
                    </div>
                </article>
            </div>
            <div class="col-12 col-sm-6 col-xl-3">
                <article class="card h-100 shadow-sm border-0">
                    <div class="card-body">
                        <p class="text-uppercase text-body-secondary small mb-1">Orçamentos em aberto</p>
                        <h2 class="h4 mb-0">0</h2>
                    </div>
                </article>
            </div>
            <div class="col-12 col-sm-6 col-xl-3">
                <article class="card h-100 shadow-sm border-0">
                    <div class="card-body">
                        <p class="text-uppercase text-body-secondary small mb-1">Faturas pendentes</p>
                        <h2 class="h4 mb-0">0</h2>
                    </div>
                </article>
            </div>
        </div>
    </section>

    <section class="mt-4" aria-label="Próximos módulos">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-transparent">
                <h3 class="h6 mb-0">Roadmap funcional</h3>
            </div>
            <div class="card-body">
                <ul class="mb-0">
                    <li>Dashboard analítico com filtros por período</li>
                    <li>Gestão de clientes e contactos</li>
                    <li>Catálogo de produtos e controlo de stock</li>
                    <li>Ciclo comercial: orçamentos, faturas e pagamentos</li>
                    <li>Perfis de utilizador e permissões por módulo</li>
                </ul>
            </div>
        </div>
    </section>
@endsection
