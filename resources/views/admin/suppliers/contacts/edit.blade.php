@extends('layouts.admin')

@section('title', 'Editar contacto')
@section('page_title', 'Editar contacto')
@section('page_subtitle', 'Atualizar contacto do fornecedor')

@section('breadcrumbs')
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.suppliers.index') }}">Fornecedores</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.suppliers.edit', $supplier->id) }}">{{ $supplier->name }}</a></li>
        <li class="breadcrumb-item active" aria-current="page">Editar contacto</li>
    </ol>
@endsection

@section('content')
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">
            {{ $errors->first() }}
        </div>
    @endif

    <form method="POST" action="{{ route('admin.suppliers.contacts.update', [$supplier->id, $contact->id]) }}">
        @csrf
        @method('PATCH')
        @include('admin.suppliers.contacts._form', ['supplier' => $supplier, 'contact' => $contact])
    </form>
@endsection
