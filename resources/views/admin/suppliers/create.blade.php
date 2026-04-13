@extends('layouts.admin')

@section('title', 'Novo fornecedor')
@section('page_title', 'Novo fornecedor')
@section('page_subtitle', 'Criar fornecedor da empresa')

@section('breadcrumbs')
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.suppliers.index') }}">Fornecedores</a></li>
        <li class="breadcrumb-item active" aria-current="page">Novo</li>
    </ol>
@endsection

@section('content')
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">
            {{ $errors->first() }}
        </div>
    @endif

    <form method="POST" action="{{ route('admin.suppliers.store') }}" enctype="multipart/form-data">
        @csrf
        @include('admin.suppliers._form')
    </form>
@endsection
