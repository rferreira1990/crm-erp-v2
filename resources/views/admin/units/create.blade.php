@extends('layouts.admin')

@section('title', 'Nova unidade')
@section('page_title', 'Nova unidade')
@section('page_subtitle', 'Criar unidade personalizada da empresa')

@section('breadcrumbs')
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.units.index') }}">Unidades</a></li>
        <li class="breadcrumb-item active" aria-current="page">Nova unidade</li>
    </ol>
@endsection

@section('content')
    <div class="card">
        <div class="card-header bg-body-tertiary">
            <h5 class="mb-0">Criar unidade</h5>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.units.store') }}">
                @csrf
                @include('admin.units._form')
            </form>
        </div>
    </div>
@endsection
