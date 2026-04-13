@extends('layouts.admin')

@section('title', 'Novo cliente')
@section('page_title', 'Novo cliente')
@section('page_subtitle', 'Criar cliente da empresa')

@section('breadcrumbs')
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.customers.index') }}">Clientes</a></li>
        <li class="breadcrumb-item active" aria-current="page">Novo</li>
    </ol>
@endsection

@section('content')
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">
            {{ $errors->first() }}
        </div>
    @endif

    <form method="POST" action="{{ route('admin.customers.store') }}" enctype="multipart/form-data">
        @csrf
        @include('admin.customers._form')
    </form>
@endsection

