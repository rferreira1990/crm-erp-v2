@extends('layouts.admin')

@section('title', 'Novo pedido de cotacao')
@section('page_title', 'Novo pedido de cotacao')
@section('page_subtitle', 'Criar consulta de precos a fornecedores')

@section('breadcrumbs')
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.rfqs.index') }}">Pedidos de cotacao</a></li>
        <li class="breadcrumb-item active" aria-current="page">Novo</li>
    </ol>
@endsection

@section('content')
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">
            {{ $errors->first() }}
        </div>
    @endif

    <form method="POST" action="{{ route('admin.rfqs.store') }}">
        @csrf
        @include('admin.rfqs._form')
    </form>
@endsection

