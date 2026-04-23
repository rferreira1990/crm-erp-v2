@extends('layouts.admin')

@section('title', 'Editar fornecedor')
@section('page_title', 'Editar fornecedor')
@section('page_subtitle', 'Atualizar dados do fornecedor')

@section('page_actions')
    <a href="{{ route('admin.suppliers.show', $supplier->id) }}" class="btn btn-phoenix-secondary btn-sm">Ver ficha</a>
@endsection

@section('breadcrumbs')
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.suppliers.index') }}">Fornecedores</a></li>
        <li class="breadcrumb-item active" aria-current="page">Editar</li>
    </ol>
@endsection

@section('content')

    @if ($errors->any())
        <div class="alert alert-danger" role="alert">
            {{ $errors->first() }}
        </div>
    @endif

    <form method="POST" action="{{ route('admin.suppliers.update', $supplier->id) }}" enctype="multipart/form-data">
        @csrf
        @method('PATCH')
        @include('admin.suppliers._form', ['supplier' => $supplier])
    </form>

    @include('admin.suppliers._contacts', ['supplier' => $supplier])
@endsection
