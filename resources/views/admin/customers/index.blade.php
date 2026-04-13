@extends('layouts.admin')

@section('title', 'Clientes')
@section('page_title', 'Clientes')
@section('page_subtitle', 'Gestao de clientes da sua empresa')

@section('page_actions')
    <a href="{{ route('admin.customers.create') }}" class="btn btn-primary btn-sm">Novo cliente</a>
@endsection

@section('breadcrumbs')
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li>
        <li class="breadcrumb-item active" aria-current="page">Clientes</li>
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
            <h5 class="mb-0">Lista de clientes</h5>
        </div>

        <div class="card-body border-bottom border-translucent">
            <form method="GET" action="{{ route('admin.customers.index') }}" class="row g-3 align-items-end">
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
                    <a href="{{ route('admin.customers.index') }}" class="btn btn-phoenix-secondary flex-fill">Limpar</a>
                </div>
            </form>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm fs-9 mb-0">
                    <thead class="bg-body-tertiary">
                        <tr>
                            <th class="ps-3">Tipo</th>
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
                        @forelse ($customers as $customer)
                            <tr>
                                <td class="ps-3">{{ $customerTypeLabels[$customer->customer_type] ?? $customer->customer_type }}</td>
                                <td class="fw-semibold">{{ $customer->name }}</td>
                                <td>{{ $customer->nif ?? '-' }}</td>
                                <td>{{ $customer->phone ?? $customer->mobile ?? '-' }}</td>
                                <td>{{ $customer->email ?? '-' }}</td>
                                <td>{{ trim(($customer->locality ?? '').' / '.($customer->city ?? ''), ' /') ?: '-' }}</td>
                                <td>{{ $customer->paymentTerm?->name ?? '-' }}</td>
                                <td>
                                    @if ($customer->defaultVatRate)
                                        {{ $customer->defaultVatRate->name }} ({{ number_format((float) $customer->defaultVatRate->rate, 2) }}%)
                                    @else
                                        -
                                    @endif
                                </td>
                                <td>
                                    @if ($customer->is_active)
                                        <span class="badge badge-phoenix badge-phoenix-success">Ativo</span>
                                    @else
                                        <span class="badge badge-phoenix badge-phoenix-secondary">Inativo</span>
                                    @endif
                                </td>
                                <td class="text-end pe-3">
                                    <div class="d-inline-flex gap-2">
                                        <a href="{{ route('admin.customers.show', $customer->id) }}" class="btn btn-phoenix-secondary btn-sm">
                                            Ficha
                                        </a>
                                        <a href="{{ route('admin.customers.edit', $customer->id) }}" class="btn btn-phoenix-secondary btn-sm">
                                            Editar
                                        </a>
                                        <form
                                            method="POST"
                                            action="{{ route('admin.customers.destroy', $customer->id) }}"
                                            data-confirm="Tem a certeza que pretende apagar este cliente?"
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
                                <td colspan="10" class="text-center py-4 text-body-tertiary">Sem clientes registados.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if ($customers->hasPages())
            <div class="card-footer">
                {{ $customers->links() }}
            </div>
        @endif
    </div>
@endsection
