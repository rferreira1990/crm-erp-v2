@extends('layouts.admin')

@section('title', 'Marcas')
@section('page_title', 'Marcas')
@section('page_subtitle', 'Gestao de marcas da sua empresa')

@section('page_actions')
    <a href="{{ route('admin.brands.create') }}" class="btn btn-primary btn-sm">Nova marca</a>
@endsection

@section('breadcrumbs')
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li>
        <li class="breadcrumb-item active" aria-current="page">Marcas</li>
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
            <h5 class="mb-0">Lista de marcas</h5>
        </div>

        <div class="card-body border-bottom border-translucent">
            <form method="GET" action="{{ route('admin.brands.index') }}" class="row g-3 align-items-end" data-live-table-form data-live-table-target="#brands-live-table">
                <div class="col-12 col-md-6">
                    <label for="q" class="form-label">Pesquisar</label>
                    <input
                        type="text"
                        id="q"
                        name="q"
                        value="{{ $filters['q'] ?? '' }}"
                        class="form-control"
                        placeholder="Nome ou website"
                    >
                </div>
                <div class="col-12 col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-fill">Filtrar</button>
                    <a href="{{ route('admin.brands.index') }}" class="btn btn-phoenix-secondary flex-fill">Limpar</a>
                </div>
            </form>
        </div>

        <div id="brands-live-table">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm fs-9 mb-0">
                    <thead class="bg-body-tertiary">
                        <tr>
                            <th class="ps-3">Logotipo</th>
                            <th>Nome</th>
                            <th>Website</th>
                            <th>Ficheiros</th>
                            <th class="text-end pe-3">Acoes</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($brands as $brand)
                            <tr>
                                <td class="ps-3">
                                    @if ($brand->logo_path)
                                        <img src="{{ Storage::disk('public')->url($brand->logo_path) }}" alt="Logo {{ $brand->name }}" style="max-height: 40px;">
                                    @else
                                        <span class="text-body-tertiary">-</span>
                                    @endif
                                </td>
                                <td class="fw-semibold">{{ $brand->name }}</td>
                                <td>
                                    @if ($brand->website_url)
                                        <a href="{{ $brand->website_url }}" target="_blank" rel="noopener noreferrer">
                                            {{ $brand->website_url }}
                                        </a>
                                    @else
                                        <span class="text-body-tertiary">-</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge badge-phoenix badge-phoenix-info">{{ $brand->files_count }}</span>
                                </td>
                                <td class="text-end pe-3">
                                    <div class="d-inline-flex gap-2">
                                        <a href="{{ route('admin.brands.edit', $brand->id) }}" class="btn btn-phoenix-secondary btn-sm">
                                            Editar
                                        </a>
                                        <form
                                            method="POST"
                                            action="{{ route('admin.brands.destroy', $brand->id) }}"
                                            data-confirm="Tem a certeza que pretende apagar esta marca?"
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
                                <td colspan="5" class="text-center py-4 text-body-tertiary">Sem marcas registadas.</td>
                            </tr>
                        @endforelse
                    </tbody>
                    </table>
                </div>
            </div>

            @if ($brands->hasPages())
                <div class="card-footer">
                    {{ $brands->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
