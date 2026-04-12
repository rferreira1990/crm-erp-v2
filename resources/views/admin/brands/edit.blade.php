@extends('layouts.admin')

@section('title', 'Editar marca')
@section('page_title', 'Editar marca')
@section('page_subtitle', 'Atualizar marca e anexos')

@section('breadcrumbs')
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.brands.index') }}">Marcas</a></li>
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
            <h5 class="mb-0">Dados da marca</h5>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.brands.update', $brand->id) }}" enctype="multipart/form-data">
                @csrf
                @method('PATCH')
                @include('admin.brands._form', ['brand' => $brand])
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header bg-body-tertiary">
            <h5 class="mb-0">Ficheiros anexados</h5>
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
                        @forelse ($brand->files as $brandFile)
                            <tr>
                                <td class="ps-3">
                                    <a href="{{ Storage::disk('public')->url($brandFile->file_path) }}" target="_blank" rel="noopener noreferrer">
                                        {{ $brandFile->original_name }}
                                    </a>
                                </td>
                                <td>{{ $brandFile->mime_type ?? '-' }}</td>
                                <td>
                                    @if ($brandFile->file_size)
                                        {{ number_format($brandFile->file_size / 1024, 1) }} KB
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="text-end pe-3">
                                    <form
                                        method="POST"
                                        action="{{ route('admin.brands.files.destroy', ['brand' => $brand->id, 'brandFile' => $brandFile->id]) }}"
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
                                <td colspan="4" class="text-center py-4 text-body-tertiary">Sem ficheiros anexados.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
