@extends('layouts.admin')

@section('title', 'Novo contacto')
@section('page_title', 'Novo contacto')
@section('page_subtitle', 'Adicionar contacto ao cliente')

@section('breadcrumbs')
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.customers.index') }}">Clientes</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.customers.edit', $customer->id) }}">{{ $customer->name }}</a></li>
        <li class="breadcrumb-item active" aria-current="page">Novo contacto</li>
    </ol>
@endsection

@section('content')
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">
            {{ $errors->first() }}
        </div>
    @endif

    <form method="POST" action="{{ route('admin.customers.contacts.store', $customer->id) }}">
        @csrf
        @include('admin.customers.contacts._form', ['customer' => $customer])
    </form>
@endsection
