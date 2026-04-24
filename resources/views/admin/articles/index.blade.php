@extends('layouts.admin')

@section('title', 'Artigos / Produtos')
@section('page_title', 'Artigos / Produtos')
@section('page_subtitle', 'Gestao de artigos da sua empresa')

@section('page_actions')
    <a href="{{ route('admin.articles.export.csv', request()->only('q')) }}" class="btn btn-phoenix-secondary btn-sm">Exportar CSV</a>
    <a href="{{ route('admin.articles.import') }}" class="btn btn-phoenix-secondary btn-sm">Importar CSV</a>
    <a href="{{ route('admin.articles.create') }}" class="btn btn-primary btn-sm">Novo artigo</a>
@endsection

@section('breadcrumbs')
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li>
        <li class="breadcrumb-item active" aria-current="page">Artigos</li>
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
            <h5 class="mb-0">Lista de artigos</h5>
        </div>

        <div class="card-body border-bottom border-translucent">
            <form method="GET" action="{{ route('admin.articles.index') }}" class="row g-3 align-items-end">
                <div class="col-12 col-md-6">
                    <label for="q" class="form-label">Pesquisar</label>
                    <input
                        type="text"
                        id="q"
                        name="q"
                        value="{{ $filters['q'] ?? '' }}"
                        class="form-control"
                        placeholder="Codigo, designacao ou EAN"
                    >
                </div>
                <div class="col-12 col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-fill">Filtrar</button>
                    <a href="{{ route('admin.articles.index') }}" class="btn btn-phoenix-secondary flex-fill">Limpar</a>
                </div>
            </form>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm fs-9 mb-0">
                    <thead class="bg-body-tertiary">
                        <tr>
                            <th class="ps-3">Codigo</th>
                            <th>Designacao</th>
                            <th>Familia</th>
                            <th>Categoria</th>
                            <th>Marca</th>
                            <th>Unidade</th>
                            <th>IVA</th>
                            <th>Custo</th>
                            <th>Venda</th>
                            <th>Estado</th>
                            <th class="text-end pe-3">Acoes</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($articles as $article)
                            <tr>
                                <td class="ps-3 fw-semibold">{{ $article->code }}</td>
                                <td>{{ $article->designation }}</td>
                                <td>{{ $article->productFamily?->name ?? '-' }}</td>
                                <td>{{ $article->category?->name ?? '-' }}</td>
                                <td>{{ $article->brand?->name ?? '-' }}</td>
                                <td>{{ $article->unit?->code ?? '-' }}</td>
                                <td>
                                    @if ($article->vatRate)
                                        {{ $article->vatRate->name }} ({{ number_format((float) $article->vatRate->rate, 2) }}%)
                                    @else
                                        -
                                    @endif
                                </td>
                                <td>
                                    @if ($article->cost_price !== null)
                                        {{ number_format((float) $article->cost_price, 4, ',', '.') }}
                                    @else
                                        -
                                    @endif
                                </td>
                                <td>
                                    @if ($article->sale_price !== null)
                                        {{ number_format((float) $article->sale_price, 4, ',', '.') }}
                                    @else
                                        -
                                    @endif
                                </td>
                                <td>
                                    @if ($article->is_active)
                                        <span class="badge badge-phoenix badge-phoenix-success">Ativo</span>
                                    @else
                                        <span class="badge badge-phoenix badge-phoenix-secondary">Inativo</span>
                                    @endif
                                </td>
                                <td class="text-end pe-3">
                                    <div class="d-inline-flex gap-2">
                                        <a href="{{ route('admin.articles.show', $article->id) }}" class="btn btn-phoenix-secondary btn-sm">
                                            Ficha
                                        </a>
                                        <a href="{{ route('admin.articles.edit', $article->id) }}" class="btn btn-phoenix-secondary btn-sm">
                                            Editar
                                        </a>
                                        <form
                                            method="POST"
                                            action="{{ route('admin.articles.destroy', $article->id) }}"
                                            data-confirm="Tem a certeza que pretende apagar este artigo?"
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
                                <td colspan="11" class="text-center py-4 text-body-tertiary">Sem artigos registados.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if ($articles->hasPages())
            <div class="card-footer">
                {{ $articles->links() }}
            </div>
        @endif
    </div>
@endsection
