@extends('layouts.admin')

@section('title', 'Editar modo de pagamento')
@section('page_title', 'Editar modo de pagamento')
@section('page_subtitle', 'Atualizar modo de pagamento personalizado da empresa')

@section('breadcrumbs')
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.payment-methods.index') }}">Modos de pagamento</a></li>
        <li class="breadcrumb-item active" aria-current="page">Editar</li>
    </ol>
@endsection

@section('content')
    <div class="card">
        <div class="card-header bg-body-tertiary">
            <h5 class="mb-0">Editar modo de pagamento</h5>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.payment-methods.update', $paymentMethod->id) }}">
                @csrf
                @method('PATCH')
                @include('admin.payment-methods._form', ['paymentMethod' => $paymentMethod])
            </form>
        </div>
    </div>
@endsection
