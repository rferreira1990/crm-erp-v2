@extends('layouts.admin')

@section('title', 'Familias de produtos')
@section('page_title', 'Familias de produtos')
@section('page_subtitle', 'Familias personalizadas da sua empresa')

@section('page_actions')
    <a href="{{ route('admin.product-families.create') }}" class="btn btn-primary btn-sm">Nova familia</a>
@endsection

@section('breadcrumbs')
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li>
        <li class="breadcrumb-item active" aria-current="page">Familias de produtos</li>
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
            <h5 class="mb-0">Gestao de familias de produtos</h5>
        </div>

        <div class="card-body border-bottom border-translucent">
            <form method="GET" action="{{ route('admin.product-families.index') }}" class="row g-3 align-items-end" data-live-table-form data-live-table-target="#product-families-live-table">
                <div class="col-12 col-md-6">
                    <label for="q" class="form-label">Pesquisar</label>
                    <input
                        type="text"
                        id="q"
                        name="q"
                        value="{{ $filters['q'] ?? '' }}"
                        class="form-control"
                        placeholder="Nome da familia"
                    >
                </div>
                <div class="col-12 col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-fill">Filtrar</button>
                    <a href="{{ route('admin.product-families.index') }}" class="btn btn-phoenix-secondary flex-fill">Limpar</a>
                </div>
            </form>
        </div>

        <div id="product-families-live-table">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm fs-9 mb-0">
                    <thead class="bg-body-tertiary">
                        <tr>
                            <th class="ps-3">Codigo</th>
                            <th class="ps-3">Nome</th>
                            <th>Hierarquia</th>
                            <th class="text-end pe-3">Acoes</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($families as $family)
                            @php
                                $depth = (int) ($familyDepths[$family->id] ?? 0);
                                $isChild = $depth > 0;
                                $padding = 12 + ($depth * 20);
                                $treePrefix = $isChild ? '└─ ' : '• ';
                            @endphp
                            <tr>
                                <td class="ps-3">
                                    @if ($family->family_code)
                                        <span class="badge badge-phoenix badge-phoenix-secondary">{{ $family->family_code }}</span>
                                    @else
                                        <span class="text-body-tertiary">-</span>
                                    @endif
                                </td>
                                <td class="ps-3 fw-semibold">
                                    <div style="padding-left: {{ $padding }}px;">
                                        <span class="text-body-tertiary">{{ $treePrefix }}</span>{{ $family->name }}
                                    </div>
                                </td>
                                <td>{{ $hierarchyLabels[$family->id] ?? $family->name }}</td>
                                <td class="text-end pe-3">
                                    <div class="d-inline-flex gap-2">
                                        <a href="{{ route('admin.product-families.edit', $family->id) }}" class="btn btn-phoenix-secondary btn-sm">
                                            Editar
                                        </a>
                                        <form
                                            method="POST"
                                            action="{{ route('admin.product-families.destroy', $family->id) }}"
                                            data-confirm="Tem a certeza que pretende apagar esta familia?"
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
                                <td colspan="4" class="text-center py-4 text-body-tertiary">Sem familias registadas.</td>
                            </tr>
                        @endforelse
                    </tbody>
                    </table>
                </div>
            </div>

            @if ($families->hasPages())
                <div class="card-footer">
                    {{ $families->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
