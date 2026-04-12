@extends('layouts.admin')

@section('title', 'Taxas de IVA')
@section('page_title', 'Taxas de IVA')
@section('page_subtitle', 'Taxas do sistema e taxas personalizadas da sua empresa')

@section('page_actions')
    <a href="{{ route('admin.vat-rates.create') }}" class="btn btn-primary btn-sm">Nova taxa</a>
@endsection

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
                            <th>Motivo de isencao</th>
                            <th>Origem</th>
                            <th class="text-end pe-3">Acoes</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($vatRates as $vatRate)
                            <tr>
                                <td class="ps-3 fw-semibold">{{ $vatRate->name }}</td>
                                <td>{{ $vatRate->regionLabel() }}</td>
                                <td>{{ number_format((float) $vatRate->rate, 2, ',', '.') }}%</td>
                                <td>
                                    @if ($vatRate->is_exempt)
                                        <span class="badge badge-phoenix badge-phoenix-warning">Isento</span>
                                    @else
                                        <span class="badge badge-phoenix badge-phoenix-success">Normal</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($vatRate->vatExemptionReason)
                                        {{ $vatRate->vatExemptionReason->code }} - {{ $vatRate->vatExemptionReason->name }}
                                    @else
                                        -
                                    @endif
                                </td>
                                <td>
                                    @if ($vatRate->is_system)
                                        <span class="badge badge-phoenix badge-phoenix-info">Sistema</span>
                                    @else
                                        <span class="badge badge-phoenix badge-phoenix-primary">Personalizada</span>
                                    @endif
                                </td>
                                <td class="text-end pe-3">
                                    @if ($vatRate->is_system)
                                        <span class="text-body-tertiary">Protegida</span>
                                    @else
                                        <div class="d-inline-flex gap-2">
                                            <a href="{{ route('admin.vat-rates.edit', $vatRate->id) }}" class="btn btn-phoenix-secondary btn-sm">
                                                Editar
                                            </a>
                                            <form
                                                method="POST"
                                                action="{{ route('admin.vat-rates.destroy', $vatRate->id) }}"
                                                data-confirm="Tem a certeza que pretende apagar esta taxa de IVA?"
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
                                <td colspan="7" class="text-center py-4 text-body-tertiary">
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

