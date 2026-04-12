@extends('layouts.admin')

@section('title', 'Motivos de isencao de IVA')
@section('page_title', 'Motivos de isencao de IVA')
@section('page_subtitle', 'Motivos do sistema com estado de disponibilidade por empresa')

@section('breadcrumbs')
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li>
        <li class="breadcrumb-item active" aria-current="page">Motivos de isencao</li>
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
            <h5 class="mb-0">Gestao de motivos de isencao</h5>
        </div>

        <div class="card-body border-bottom border-translucent">
            <form method="GET" action="{{ route('admin.vat-exemption-reasons.index') }}" class="row g-3 align-items-end">
                <div class="col-12 col-md-6">
                    <label for="q" class="form-label">Pesquisar</label>
                    <input
                        type="text"
                        id="q"
                        name="q"
                        value="{{ $filters['q'] ?? '' }}"
                        class="form-control"
                        placeholder="Codigo ou nome"
                    >
                </div>
                <div class="col-12 col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-fill">Filtrar</button>
                    <a href="{{ route('admin.vat-exemption-reasons.index') }}" class="btn btn-phoenix-secondary flex-fill">Limpar</a>
                </div>
            </form>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm fs-9 mb-0">
                    <thead class="bg-body-tertiary">
                        <tr>
                            <th class="ps-3">Codigo</th>
                            <th>Nome</th>
                            <th>Enquadramento legal</th>
                            <th>Estado na empresa</th>
                            <th class="text-end pe-3">Acoes</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($reasons as $reason)
                            @php
                                $isEnabled = $reason->isEnabledForCompany((int) auth()->user()->company_id);
                            @endphp
                            <tr>
                                <td class="ps-3 fw-semibold">{{ $reason->code }}</td>
                                <td>{{ $reason->name }}</td>
                                <td>{{ $reason->legal_reference ?? '-' }}</td>
                                <td>
                                    @if ($isEnabled)
                                        <span class="badge badge-phoenix badge-phoenix-success">Ativo</span>
                                    @else
                                        <span class="badge badge-phoenix badge-phoenix-secondary">Inativo</span>
                                    @endif
                                </td>
                                <td class="text-end pe-3">
                                    @if ($canManageAvailability)
                                        @if ($isEnabled)
                                            <form
                                                method="POST"
                                                action="{{ route('admin.vat-exemption-reasons.disable', $reason->id) }}"
                                                class="d-inline"
                                                data-confirm="Tem a certeza que pretende desativar este motivo para a sua empresa?"
                                            >
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="btn btn-phoenix-warning btn-sm">Desativar</button>
                                            </form>
                                        @else
                                            <form
                                                method="POST"
                                                action="{{ route('admin.vat-exemption-reasons.enable', $reason->id) }}"
                                                class="d-inline"
                                                data-confirm="Tem a certeza que pretende ativar este motivo para a sua empresa?"
                                            >
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="btn btn-phoenix-success btn-sm">Ativar</button>
                                            </form>
                                        @endif
                                    @else
                                        <span class="text-body-tertiary">Sem permissao</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-4 text-body-tertiary">
                                    Sem motivos de isencao disponiveis.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if ($reasons->hasPages())
            <div class="card-footer">
                {{ $reasons->links() }}
            </div>
        @endif
    </div>
@endsection
