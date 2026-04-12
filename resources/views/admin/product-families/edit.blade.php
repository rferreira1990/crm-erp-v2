@extends('layouts.admin')

@section('title', 'Editar familia de produtos')
@section('page_title', 'Editar familia de produtos')
@section('page_subtitle', 'Atualizar familia personalizada da empresa')

@section('breadcrumbs')
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.product-families.index') }}">Familias de produtos</a></li>
        <li class="breadcrumb-item active" aria-current="page">Editar</li>
    </ol>
@endsection

@section('content')
    <div class="card">
        <div class="card-header bg-body-tertiary">
            <h5 class="mb-0">Editar familia de produtos</h5>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.product-families.update', $family->id) }}">
                @csrf
                @method('PATCH')
                @include('admin.product-families._form', ['family' => $family])
            </form>
        </div>
    </div>
@endsection

