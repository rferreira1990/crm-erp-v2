@extends('layouts.admin')

@section('title', 'Editar unidade')
@section('page_title', 'Editar unidade')
@section('page_subtitle', 'Atualizar unidade personalizada da empresa')

@section('breadcrumbs')
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.units.index') }}">Unidades</a></li>
        <li class="breadcrumb-item active" aria-current="page">Editar</li>
    </ol>
@endsection

@section('content')
    <div class="card">
        <div class="card-header bg-body-tertiary">
            <h5 class="mb-0">Editar unidade</h5>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.units.update', $unit->id) }}">
                @csrf
                @method('PATCH')
                @include('admin.units._form', ['unit' => $unit])
            </form>
        </div>
    </div>
@endsection
