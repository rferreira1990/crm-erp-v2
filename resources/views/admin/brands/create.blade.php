@extends('layouts.admin')

@section('title', 'Nova marca')
@section('page_title', 'Nova marca')
@section('page_subtitle', 'Criar marca da empresa')

@section('breadcrumbs')
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.brands.index') }}">Marcas</a></li>
        <li class="breadcrumb-item active" aria-current="page">Nova marca</li>
    </ol>
@endsection

@section('content')
    <div class="card">
        <div class="card-header bg-body-tertiary">
            <h5 class="mb-0">Criar marca</h5>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.brands.store') }}" enctype="multipart/form-data">
                @csrf
                @include('admin.brands._form')
            </form>
        </div>
    </div>
@endsection
