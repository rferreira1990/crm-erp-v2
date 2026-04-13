@extends('layouts.admin')

@section('title', 'Editar artigo')
@section('page_title', 'Editar artigo')
@section('page_subtitle', 'Atualizar artigo e anexos')

@section('breadcrumbs')
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.articles.index') }}">Artigos</a></li>
        <li class="breadcrumb-item active" aria-current="page">Editar</li>
    </ol>
@endsection

@section('content')
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="card mb-4">
        <div class="card-header bg-body-tertiary">
            <h5 class="mb-0">Dados do artigo</h5>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.articles.update', $article->id) }}" enctype="multipart/form-data">
                @csrf
                @method('PATCH')
                @include('admin.articles._form', ['article' => $article])
            </form>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header bg-body-tertiary">
            <h5 class="mb-0">Imagens</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm fs-9 mb-0">
                    <thead class="bg-body-tertiary">
                        <tr>
                            <th class="ps-3">Imagem</th>
                            <th>Primaria</th>
                            <th>Tamanho</th>
                            <th class="text-end pe-3">Acoes</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($article->images as $articleImage)
                            <tr>
                                <td class="ps-3">
                                    <a href="{{ Storage::disk('public')->url($articleImage->file_path) }}" target="_blank" rel="noopener noreferrer">
                                        {{ $articleImage->original_name }}
                                    </a>
                                </td>
                                <td>
                                    @if ($articleImage->is_primary)
                                        <span class="badge badge-phoenix badge-phoenix-success">Sim</span>
                                    @else
                                        <span class="text-body-tertiary">Nao</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($articleImage->file_size)
                                        {{ number_format($articleImage->file_size / 1024, 1) }} KB
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="text-end pe-3">
                                    <form
                                        method="POST"
                                        action="{{ route('admin.articles.images.destroy', ['article' => $article->id, 'articleImage' => $articleImage->id]) }}"
                                        data-confirm="Tem a certeza que pretende remover esta imagem?"
                                    >
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-phoenix-danger btn-sm">Remover</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center py-4 text-body-tertiary">Sem imagens anexadas.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header bg-body-tertiary">
            <h5 class="mb-0">Documentos</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm fs-9 mb-0">
                    <thead class="bg-body-tertiary">
                        <tr>
                            <th class="ps-3">Ficheiro</th>
                            <th>Tipo</th>
                            <th>Tamanho</th>
                            <th class="text-end pe-3">Acoes</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($article->files as $articleFile)
                            <tr>
                                <td class="ps-3">
                                    <a href="{{ Storage::disk('public')->url($articleFile->file_path) }}" target="_blank" rel="noopener noreferrer">
                                        {{ $articleFile->original_name }}
                                    </a>
                                </td>
                                <td>{{ $articleFile->mime_type ?? '-' }}</td>
                                <td>
                                    @if ($articleFile->file_size)
                                        {{ number_format($articleFile->file_size / 1024, 1) }} KB
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="text-end pe-3">
                                    <form
                                        method="POST"
                                        action="{{ route('admin.articles.files.destroy', ['article' => $article->id, 'articleFile' => $articleFile->id]) }}"
                                        data-confirm="Tem a certeza que pretende remover este ficheiro?"
                                    >
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-phoenix-danger btn-sm">Remover</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center py-4 text-body-tertiary">Sem documentos anexados.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

