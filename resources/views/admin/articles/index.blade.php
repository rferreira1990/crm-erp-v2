@extends('layouts.admin')

@section('title', 'Artigos / Produtos')
@section('page_title', 'Artigos / Produtos')
@section('page_subtitle', 'Gestao de artigos da sua empresa')

@section('page_actions')
    <a href="{{ route('admin.articles.export.csv', request()->only(['q', 'family_id', 'brand_id'])) }}" class="btn btn-phoenix-secondary btn-sm" data-live-table-export>Exportar CSV</a>
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

    <div class="mb-4">
        <ul class="nav nav-links mx-n3">
            <li class="nav-item">
                <a class="nav-link active" aria-current="page" href="{{ route('admin.articles.index') }}">
                    <span>Todos </span>
                    <span class="text-body-tertiary fw-semibold">({{ $articles->total() }})</span>
                </a>
            </li>
            @if (! empty($filters['q']))
                <li class="nav-item">
                    <span class="nav-link">
                        <span>Pesquisa: </span>
                        <span class="text-body-tertiary fw-semibold">{{ $filters['q'] }}</span>
                    </span>
                </li>
            @endif
            @if (! empty($filters['family_id']) || ! empty($filters['brand_id']))
                <li class="nav-item">
                    <span class="nav-link text-body-tertiary fw-semibold">Filtros ativos</span>
                </li>
            @endif
        </ul>
    </div>

    <div id="products">
        <div class="mb-4">
            <form
                method="GET"
                action="{{ route('admin.articles.index') }}"
                id="articles-filter-form"
                data-live-table-form
                data-live-table-target="#articles-table-container"
                data-live-table-endpoint="{{ route('admin.articles.table') }}"
                data-live-table-history-endpoint="{{ route('admin.articles.index') }}"
                data-live-table-export-selector="[data-live-table-export]"
            >
                <div class="d-flex flex-wrap gap-3">
                    <div class="search-box">
                        <input
                            class="form-control search-input"
                            type="search"
                            id="q"
                            name="q"
                            value="{{ $filters['q'] ?? '' }}"
                            placeholder="Pesquisar por codigo, designacao ou EAN"
                            aria-label="Pesquisar artigos"
                            autocomplete="off"
                        />
                        <span class="fas fa-search search-box-icon"></span>
                    </div>

                    <div class="scrollbar overflow-hidden-y">
                        <div class="btn-group position-static" role="group">
                            <select name="family_id" class="form-select form-select-sm">
                                <option value="">Familia</option>
                                @foreach ($familyOptions as $familyOption)
                                    <option value="{{ $familyOption->id }}" @selected((string) $filters['family_id'] === (string) $familyOption->id)>
                                        {{ $familyOption->name }}
                                    </option>
                                @endforeach
                            </select>
                            <select name="brand_id" class="form-select form-select-sm">
                                <option value="">Marca</option>
                                @foreach ($brandOptions as $brandOption)
                                    <option value="{{ $brandOption->id }}" @selected((string) $filters['brand_id'] === (string) $brandOption->id)>
                                        {{ $brandOption->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <div id="articles-table-container">
            @include('admin.articles.partials.table', ['articles' => $articles])
        </div>
    </div>
@endsection
