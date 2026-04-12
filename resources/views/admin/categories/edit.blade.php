@extends('layouts.admin')

@section('title', 'Editar categoria')
@section('page_title', 'Editar categoria')
@section('page_subtitle', 'Atualizar categoria personalizada da empresa')

@section('breadcrumbs')
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.categories.index') }}">Categorias</a></li>
        <li class="breadcrumb-item active" aria-current="page">Editar</li>
    </ol>
@endsection

@section('content')
    <div class="card">
        <div class="card-header bg-body-tertiary">
            <h5 class="mb-0">Editar categoria</h5>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.categories.update', $category->id) }}">
                @csrf
                @method('PATCH')
                @include('admin.categories._form', ['category' => $category])
            </form>
        </div>
    </div>
@endsection
