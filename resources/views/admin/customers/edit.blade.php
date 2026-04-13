@extends('layouts.admin')

@section('title', 'Editar cliente')
@section('page_title', 'Editar cliente')
@section('page_subtitle', 'Atualizar dados do cliente')

@section('breadcrumbs')
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.customers.index') }}">Clientes</a></li>
        <li class="breadcrumb-item active" aria-current="page">Editar</li>
    </ol>
@endsection

@section('content')
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">
            {{ $errors->first() }}
        </div>
    @endif

    <form method="POST" action="{{ route('admin.customers.update', $customer->id) }}" enctype="multipart/form-data">
        @csrf
        @method('PATCH')
        @include('admin.customers._form', ['customer' => $customer])
    </form>
@endsection

