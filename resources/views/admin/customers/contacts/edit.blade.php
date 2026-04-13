@extends('layouts.admin')

@section('title', 'Editar contacto')
@section('page_title', 'Editar contacto')
@section('page_subtitle', 'Atualizar contacto do cliente')

@section('breadcrumbs')
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.customers.index') }}">Clientes</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.customers.edit', $customer->id) }}">{{ $customer->name }}</a></li>
        <li class="breadcrumb-item active" aria-current="page">Editar contacto</li>
    </ol>
@endsection

@section('content')
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">
            {{ $errors->first() }}
        </div>
    @endif

    <form method="POST" action="{{ route('admin.customers.contacts.update', [$customer->id, $contact->id]) }}">
        @csrf
        @method('PATCH')
        @include('admin.customers.contacts._form', ['customer' => $customer, 'contact' => $contact])
    </form>
@endsection
