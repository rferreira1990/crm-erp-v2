@extends('layouts.admin')

@section('title', 'Motivos de isencao de IVA')
@section('page_title', 'Motivos de isencao de IVA')
@section('page_subtitle', 'Motivos do sistema e motivos personalizados da sua empresa')

@section('page_actions')
    <a href="{{ route('admin.vat-exemption-reasons.create') }}" class="btn btn-primary btn-sm">Novo motivo</a>
@endsection

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
                            <th>Origem</th>
                            <th class="text-end pe-3">Acoes</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($reasons as $reason)
                            <tr>
                                <td class="ps-3 fw-semibold">{{ $reason->code }}</td>
                                <td>{{ $reason->name }}</td>
                                <td>{{ $reason->legal_reference ?? '-' }}</td>
                                <td>
                                    @if ($reason->is_system)
                                        <span class="badge badge-phoenix badge-phoenix-info">Sistema</span>
                                    @else
                                        <span class="badge badge-phoenix badge-phoenix-primary">Personalizado</span>
                                    @endif
                                </td>
                                <td class="text-end pe-3">
                                    @if ($reason->is_system)
                                        <span class="text-body-tertiary">Protegido</span>
                                    @else
                                        <div class="d-inline-flex gap-2">
                                            <a href="{{ route('admin.vat-exemption-reasons.edit', $reason->id) }}" class="btn btn-phoenix-secondary btn-sm">
                                                Editar
                                            </a>
                                            <form
                                                method="POST"
                                                action="{{ route('admin.vat-exemption-reasons.destroy', $reason->id) }}"
                                                data-confirm="Tem a certeza que pretende apagar este motivo de isencao?"
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

