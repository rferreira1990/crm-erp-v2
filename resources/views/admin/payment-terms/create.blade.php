@extends('layouts.admin')

@section('title', 'Nova condição de pagamento')
@section('page_title', 'Nova condição de pagamento')
@section('page_subtitle', 'Criar condição de pagamento personalizada da empresa')

@section('breadcrumbs')
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.payment-terms.index') }}">Condições de pagamento</a></li>
        <li class="breadcrumb-item active" aria-current="page">Nova</li>
    </ol>
@endsection

@section('content')
    <div class="card">
        <div class="card-header bg-body-tertiary">
            <h5 class="mb-0">Criar condição de pagamento</h5>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.payment-terms.store') }}">
                @csrf
                @include('admin.payment-terms._form')
            </form>
        </div>
    </div>
@endsection

