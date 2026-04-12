@extends('layouts.admin')

@section('title', 'Novo modo de pagamento')
@section('page_title', 'Novo modo de pagamento')
@section('page_subtitle', 'Criar modo de pagamento personalizado da empresa')

@section('breadcrumbs')
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.payment-methods.index') }}">Modos de pagamento</a></li>
        <li class="breadcrumb-item active" aria-current="page">Novo</li>
    </ol>
@endsection

@section('content')
    <div class="card">
        <div class="card-header bg-body-tertiary">
            <h5 class="mb-0">Criar modo de pagamento</h5>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.payment-methods.store') }}">
                @csrf
                @include('admin.payment-methods._form')
            </form>
        </div>
    </div>
@endsection
