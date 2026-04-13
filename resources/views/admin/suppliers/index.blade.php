@extends('layouts.admin')

@section('title', 'Fornecedores')
@section('page_title', 'Fornecedores')
@section('page_subtitle', 'Gestao de fornecedores da sua empresa')

@section('page_actions')
    <a href="{{ route('admin.suppliers.create') }}" class="btn btn-primary btn-sm">Novo fornecedor</a>
@endsection

@section('breadcrumbs')
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li>
        <li class="breadcrumb-item active" aria-current="page">Fornecedores</li>
    </ol>
@endsection

@section('content')
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="card">
        <div class="card-header bg-body-tertiary d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Lista de fornecedores</h5>
        </div>

        <div class="card-body border-bottom border-translucent">
            <form method="GET" action="{{ route('admin.suppliers.index') }}" class="row g-3 align-items-end">
                <div class="col-12 col-md-6">
                    <label for="q" class="form-label">Pesquisar</label>
                    <input
                        type="text"
                        id="q"
                        name="q"
                        value="{{ $filters['q'] ?? '' }}"
                        class="form-control"
                        placeholder="Nome, NIF, email ou telefone"
                    >
                </div>
                <div class="col-12 col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-fill">Filtrar</button>
                    <a href="{{ route('admin.suppliers.index') }}" class="btn btn-phoenix-secondary flex-fill">Limpar</a>
                </div>
            </form>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm fs-9 mb-0">
                    <thead class="bg-body-tertiary">
                        <tr>
                            <th>Designacao</th>
                            <th>NIF</th>
                            <th>Telefone</th>
                            <th>Email</th>
                            <th>Localidade / Cidade</th>
                            <th>Cond. pagamento</th>
                            <th>IVA habitual</th>
                            <th>Estado</th>
                            <th class="text-end pe-3">Acoes</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($suppliers as $supplier)
                            <tr>
                                <td class="fw-semibold ps-3">{{ $supplier->name }}</td>
                                <td>{{ $supplier->nif ?? '-' }}</td>
                                <td>{{ $supplier->phone ?? $supplier->mobile ?? '-' }}</td>
                                <td>{{ $supplier->email ?? '-' }}</td>
                                <td>{{ trim(($supplier->locality ?? '').' / '.($supplier->city ?? ''), ' /') ?: '-' }}</td>
                                <td>{{ $supplier->paymentTerm?->name ?? '-' }}</td>
                                <td>
                                    @if ($supplier->defaultVatRate)
                                        {{ $supplier->defaultVatRate->name }} ({{ number_format((float) $supplier->defaultVatRate->rate, 2) }}%)
                                    @else
                                        -
                                    @endif
                                </td>
                                <td>
                                    @if ($supplier->is_active)
                                        <span class="badge badge-phoenix badge-phoenix-success">Ativo</span>
                                    @else
                                        <span class="badge badge-phoenix badge-phoenix-secondary">Inativo</span>
                                    @endif
                                </td>
                                <td class="text-end pe-3">
                                    <div class="d-inline-flex gap-2">
                                        <a href="{{ route('admin.suppliers.show', $supplier->id) }}" class="btn btn-phoenix-secondary btn-sm">
                                            Ficha
                                        </a>
                                        <a href="{{ route('admin.suppliers.edit', $supplier->id) }}" class="btn btn-phoenix-secondary btn-sm">
                                            Editar
                                        </a>
                                        <form
                                            method="POST"
                                            action="{{ route('admin.suppliers.destroy', $supplier->id) }}"
                                            data-confirm="Tem a certeza que pretende apagar este fornecedor?"
                                        >
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-phoenix-danger btn-sm">Apagar</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center py-4 text-body-tertiary">Sem fornecedores registados.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if ($suppliers->hasPages())
            <div class="card-footer">
                {{ $suppliers->links() }}
            </div>
        @endif
    </div>
@endsection
