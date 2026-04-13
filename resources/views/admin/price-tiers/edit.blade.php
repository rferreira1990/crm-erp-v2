@extends('layouts.admin')

@section('title', 'Editar escalao de preco')
@section('page_title', 'Editar escalao de preco')
@section('page_subtitle', 'Atualizar escalao personalizado da empresa')

@section('breadcrumbs')
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.price-tiers.index') }}">Escaloes de preco</a></li>
        <li class="breadcrumb-item active" aria-current="page">Editar</li>
    </ol>
@endsection

@section('content')
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="card">
        <div class="card-header bg-body-tertiary">
            <h5 class="mb-0">Editar escalao de preco</h5>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.price-tiers.update', $priceTier->id) }}">
                @csrf
                @method('PATCH')
                @include('admin.price-tiers._form', ['priceTier' => $priceTier])
            </form>
        </div>
    </div>
@endsection

