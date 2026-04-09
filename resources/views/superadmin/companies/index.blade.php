@extends('layouts.admin')

@section('title', 'Superadmin - Empresas')
@section('page_title', 'Empresas')
@section('page_subtitle', 'Gestao de empresas da plataforma')

@section('page_actions')
    <a href="{{ route('superadmin.companies.create') }}" class="btn btn-primary btn-sm">
        Nova empresa
    </a>
@endsection

@section('breadcrumbs')
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('superadmin.home') }}">Superadmin</a></li>
        <li class="breadcrumb-item active" aria-current="page">Empresas</li>
    </ol>
@endsection

@section('content')
    <div class="alert alert-warning d-flex align-items-center" role="alert">
        <span class="me-2" data-feather="shield"></span>
        <span>Area de plataforma: apenas gestao de empresas e convites.</span>
    </div>

    <div class="card">
        <div class="card-header bg-body-tertiary">
            <h5 class="mb-0">Lista de empresas</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm fs-9 mb-0">
                    <thead class="bg-body-tertiary">
                        <tr>
                            <th class="ps-3">Nome</th>
                            <th>Slug</th>
                            <th>NIF</th>
                            <th>Email</th>
                            <th>Estado</th>
                            <th class="text-end pe-3">Acoes</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($companies as $company)
                            <tr>
                                <td class="ps-3 fw-semibold">{{ $company->name }}</td>
                                <td>{{ $company->slug }}</td>
                                <td>{{ $company->nif ?: '-' }}</td>
                                <td>{{ $company->email ?: '-' }}</td>
                                <td>
                                    @if ($company->is_active)
                                        <span class="badge badge-phoenix badge-phoenix-success">Ativa</span>
                                    @else
                                        <span class="badge badge-phoenix badge-phoenix-danger">Inativa</span>
                                    @endif
                                </td>
                                <td class="text-end pe-3">
                                    <div class="d-inline-flex gap-2">
                                        <a href="{{ route('superadmin.companies.edit', $company) }}" class="btn btn-phoenix-secondary btn-sm">
                                            Editar
                                        </a>
                                        <form method="POST" action="{{ route('superadmin.companies.toggle-active', $company) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="btn btn-phoenix-warning btn-sm">
                                                {{ $company->is_active ? 'Desativar' : 'Ativar' }}
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-4 text-body-tertiary">Sem empresas registadas.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if ($companies->hasPages())
            <div class="card-footer">
                {{ $companies->links() }}
            </div>
        @endif
    </div>
@endsection
