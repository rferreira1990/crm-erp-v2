@extends('layouts.admin')

@section('title', 'Taxas de IVA')
@section('page_title', 'Taxas de IVA')
@section('page_subtitle', 'Taxas do sistema com estado de disponibilidade por empresa')

@section('breadcrumbs')
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li>
        <li class="breadcrumb-item active" aria-current="page">Taxas de IVA</li>
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
            <h5 class="mb-0">Gestao de taxas de IVA</h5>
        </div>

        <div class="card-body border-bottom border-translucent">
            <div class="alert alert-info mb-0" role="alert">
                A taxa Isento so pode ser ativada quando existir pelo menos um motivo de isencao ativo.
            </div>
        </div>

        <div class="card-body border-bottom border-translucent">
            <form method="GET" action="{{ route('admin.vat-rates.index') }}" class="row g-3 align-items-end">
                <div class="col-12 col-md-6">
                    <label for="q" class="form-label">Pesquisar</label>
                    <input
                        type="text"
                        id="q"
                        name="q"
                        value="{{ $filters['q'] ?? '' }}"
                        class="form-control"
                        placeholder="Nome da taxa"
                    >
                </div>
                <div class="col-12 col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-fill">Filtrar</button>
                    <a href="{{ route('admin.vat-rates.index') }}" class="btn btn-phoenix-secondary flex-fill">Limpar</a>
                </div>
            </form>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm fs-9 mb-0">
                    <thead class="bg-body-tertiary">
                        <tr>
                            <th class="ps-3">Nome</th>
                            <th>Regiao fiscal</th>
                            <th>Taxa</th>
                            <th>Tipo</th>
                            <th>Estado na empresa</th>
                            <th class="text-end pe-3">Acoes</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($vatRates as $vatRate)
                            @php
                                $isEnabled = $vatRate->isEnabledForCompany((int) auth()->user()->company_id);
                                $regionBadgeClass = match ($vatRate->region) {
                                    'mainland' => 'badge-phoenix-primary',
                                    'madeira' => 'badge-phoenix-info',
                                    'azores' => 'badge-phoenix-warning',
                                    default => 'badge-phoenix-secondary',
                                };
                            @endphp
                            <tr>
                                <td class="ps-3 fw-semibold">{{ $vatRate->name }}</td>
                                <td>
                                    <span class="badge badge-phoenix {{ $regionBadgeClass }}">{{ $vatRate->regionLabel() }}</span>
                                </td>
                                <td>{{ number_format((float) $vatRate->rate, 2, ',', '.') }}%</td>
                                <td>
                                    @if ($vatRate->is_exempt)
                                        <span class="badge badge-phoenix badge-phoenix-warning">Isento</span>
                                    @else
                                        <span class="badge badge-phoenix badge-phoenix-success">Normal</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($isEnabled)
                                        <span class="badge badge-phoenix badge-phoenix-success">Ativa</span>
                                    @else
                                        <span class="badge badge-phoenix badge-phoenix-secondary">Inativa</span>
                                    @endif
                                </td>
                                <td class="text-end pe-3">
                                    @if ($canManageAvailability)
                                        @if ($isEnabled)
                                            <form
                                                method="POST"
                                                action="{{ route('admin.vat-rates.disable', $vatRate->id) }}"
                                                class="d-inline"
                                                data-confirm="Tem a certeza que pretende desativar esta taxa para a sua empresa?"
                                            >
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="btn btn-phoenix-warning btn-sm">Desativar</button>
                                            </form>
                                        @else
                                            <form
                                                method="POST"
                                                action="{{ route('admin.vat-rates.enable', $vatRate->id) }}"
                                                class="d-inline"
                                                data-confirm="Tem a certeza que pretende ativar esta taxa para a sua empresa?"
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
                                <td colspan="6" class="text-center py-4 text-body-tertiary">
                                    Sem taxas de IVA disponiveis.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if ($vatRates->hasPages())
            <div class="card-footer">
                {{ $vatRates->links() }}
            </div>
        @endif
    </div>
@endsection
