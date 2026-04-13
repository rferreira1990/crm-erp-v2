@extends('layouts.admin')

@section('title', 'Novo contacto')
@section('page_title', 'Novo contacto')
@section('page_subtitle', 'Adicionar contacto ao fornecedor')

@section('breadcrumbs')
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.suppliers.index') }}">Fornecedores</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.suppliers.edit', $supplier->id) }}">{{ $supplier->name }}</a></li>
        <li class="breadcrumb-item active" aria-current="page">Novo contacto</li>
    </ol>
@endsection

@section('content')
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">
            {{ $errors->first() }}
        </div>
    @endif

    <form method="POST" action="{{ route('admin.suppliers.contacts.store', $supplier->id) }}">
        @csrf
        @include('admin.suppliers.contacts._form', ['supplier' => $supplier])
    </form>
@endsection
