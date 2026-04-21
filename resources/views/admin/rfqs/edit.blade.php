@extends('layouts.admin')

@section('title', 'Editar pedido de cotacao')
@section('page_title', 'Editar pedido de cotacao')
@section('page_subtitle', $rfq->number)

@section('breadcrumbs')
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.rfqs.index') }}">Pedidos de cotacao</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.rfqs.show', $rfq->id) }}">{{ $rfq->number }}</a></li>
        <li class="breadcrumb-item active" aria-current="page">Editar</li>
    </ol>
@endsection

@section('content')
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">
            {{ $errors->first() }}
        </div>
    @endif

    <form method="POST" action="{{ route('admin.rfqs.update', $rfq->id) }}">
        @csrf
        @method('PATCH')
        @include('admin.rfqs._form')
    </form>
@endsection

