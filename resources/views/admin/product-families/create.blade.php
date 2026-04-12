@extends('layouts.admin')

@section('title', 'Nova familia de produtos')
@section('page_title', 'Nova familia de produtos')
@section('page_subtitle', 'Criar familia personalizada da empresa')

@section('breadcrumbs')
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.product-families.index') }}">Familias de produtos</a></li>
        <li class="breadcrumb-item active" aria-current="page">Nova familia</li>
    </ol>
@endsection

@section('content')
    <div class="card">
        <div class="card-header bg-body-tertiary">
            <h5 class="mb-0">Criar familia de produtos</h5>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.product-families.store') }}">
                @csrf
                @include('admin.product-families._form')
            </form>
        </div>
    </div>
@endsection

