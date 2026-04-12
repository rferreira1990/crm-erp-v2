@extends('layouts.admin')

@section('title', 'Condições de pagamento')
@section('page_title', 'Condições de pagamento')
@section('page_subtitle', 'Condições do sistema e condições personalizadas da sua empresa')

@section('page_actions')
    <a href="{{ route('admin.payment-terms.create') }}" class="btn btn-primary btn-sm">Nova condição</a>
@endsection

@section('breadcrumbs')
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li>
        <li class="breadcrumb-item active" aria-current="page">Condições de pagamento</li>
    </ol>
@endsection

@section('content')
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="card mb-3">
        <div class="card-header bg-body-tertiary d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Gestão de condições de pagamento</h5>
        </div>

        <div class="card-body border-bottom border-translucent">
            <form method="GET" action="{{ route('admin.payment-terms.index') }}" class="row g-3 align-items-end">
                <div class="col-12 col-md-6">
                    <label for="q" class="form-label">Pesquisar</label>
                    <input
                        type="text"
                        id="q"
                        name="q"
                        value="{{ $filters['q'] ?? '' }}"
                        class="form-control"
                        placeholder="Nome da condição"
                    >
                </div>
                <div class="col-12 col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-fill">Filtrar</button>
                    <a href="{{ route('admin.payment-terms.index') }}" class="btn btn-phoenix-secondary flex-fill">Limpar</a>
                </div>
            </form>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm fs-9 mb-0">
                    <thead class="bg-body-tertiary">
                        <tr>
                            <th class="ps-3">Nome</th>
                            <th>Tipo de calculo</th>
                            <th>Dias</th>
                            <th>Origem</th>
                            <th class="text-end pe-3">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($paymentTerms as $paymentTerm)
                            <tr>
                                <td class="ps-3 fw-semibold">{{ $paymentTerm->name }}</td>
                                <td>{{ $paymentTerm->calculationTypeLabel() }}</td>
                                <td>{{ $paymentTerm->days }}</td>
                                <td>
                                    @if ($paymentTerm->is_system)
                                        <span class="badge badge-phoenix badge-phoenix-info">Sistema</span>
                                    @else
                                        <span class="badge badge-phoenix badge-phoenix-primary">Personalizada</span>
                                    @endif
                                </td>
                                <td class="text-end pe-3">
                                    @if ($paymentTerm->is_system)
                                        @if ($canManageDefaults)
                                            <form
                                                method="POST"
                                                action="{{ route('admin.payment-terms.deactivate-system', $paymentTerm->id) }}"
                                                class="d-inline"
                                                data-confirm="Tem a certeza que pretende desativar esta condição do sistema apenas para a sua empresa?"
                                            >
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="btn btn-phoenix-warning btn-sm">Desativar</button>
                                            </form>
                                        @else
                                            <span class="text-body-tertiary">Protegida</span>
                                        @endif
                                    @else
                                        <div class="d-inline-flex gap-2">
                                            <a href="{{ route('admin.payment-terms.edit', $paymentTerm->id) }}" class="btn btn-phoenix-secondary btn-sm">
                                                Editar
                                            </a>
                                            <form
                                                method="POST"
                                                action="{{ route('admin.payment-terms.destroy', $paymentTerm->id) }}"
                                                data-confirm="Tem a certeza que pretende apagar esta condição de pagamento?"
                                            >
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-phoenix-danger btn-sm">Apagar</button>
                                            </form>
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-4 text-body-tertiary">
                                    Sem condições de pagamento disponíveis.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if ($paymentTerms->hasPages())
            <div class="card-footer">
                {{ $paymentTerms->links() }}
            </div>
        @endif
    </div>

    @if ($canManageDefaults && $disabledSystemTerms->isNotEmpty())
        <div class="card">
            <div class="card-header bg-body-tertiary">
                <h5 class="mb-0">Condições do sistema desativadas para a sua empresa</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm fs-9 mb-0">
                        <thead class="bg-body-tertiary">
                            <tr>
                                <th class="ps-3">Nome</th>
                                <th>Tipo de calculo</th>
                                <th>Dias</th>
                                <th class="text-end pe-3">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($disabledSystemTerms as $disabledSystemTerm)
                                <tr>
                                    <td class="ps-3 fw-semibold">{{ $disabledSystemTerm->name }}</td>
                                    <td>{{ $disabledSystemTerm->calculationTypeLabel() }}</td>
                                    <td>{{ $disabledSystemTerm->days }}</td>
                                    <td class="text-end pe-3">
                                        <form
                                            method="POST"
                                            action="{{ route('admin.payment-terms.reactivate-system', $disabledSystemTerm->id) }}"
                                            class="d-inline"
                                            data-confirm="Tem a certeza que pretende reativar esta condição do sistema para a sua empresa?"
                                        >
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="btn btn-phoenix-success btn-sm">Reativar</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif
@endsection
