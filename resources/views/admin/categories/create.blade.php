@extends('layouts.admin')

@section('title', 'Nova categoria')
@section('page_title', 'Nova categoria')
@section('page_subtitle', 'Criar categoria personalizada da empresa')

@section('breadcrumbs')
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.categories.index') }}">Categorias</a></li>
        <li class="breadcrumb-item active" aria-current="page">Nova categoria</li>
    </ol>
@endsection

@section('content')
    <div class="card">
        <div class="card-header bg-body-tertiary">
            <h5 class="mb-0">Criar categoria</h5>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.categories.store') }}">
                @csrf
                @include('admin.categories._form')
            </form>
        </div>
    </div>
@endsection
