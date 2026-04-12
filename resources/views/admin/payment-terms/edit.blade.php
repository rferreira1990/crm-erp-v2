@extends('layouts.admin')

@section('title', 'Editar condição de pagamento')
@section('page_title', 'Editar condição de pagamento')
@section('page_subtitle', 'Atualizar condição de pagamento personalizada da empresa')

@section('breadcrumbs')
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.payment-terms.index') }}">Condições de pagamento</a></li>
        <li class="breadcrumb-item active" aria-current="page">Editar</li>
    </ol>
@endsection

@section('content')
    <div class="card">
        <div class="card-header bg-body-tertiary">
            <h5 class="mb-0">Editar condição de pagamento</h5>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.payment-terms.update', $paymentTerm->id) }}">
                @csrf
                @method('PATCH')
                @include('admin.payment-terms._form', ['paymentTerm' => $paymentTerm])
            </form>
        </div>
    </div>
@endsection

