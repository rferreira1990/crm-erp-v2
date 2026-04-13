@extends('layouts.admin')

@section('title', 'Novo orcamento')
@section('page_title', 'Novo orcamento')
@section('page_subtitle', 'Criar proposta comercial')

@section('breadcrumbs')
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.quotes.index') }}">Orcamentos</a></li>
        <li class="breadcrumb-item active" aria-current="page">Novo</li>
    </ol>
@endsection

@section('content')
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">
            {{ $errors->first() }}
        </div>
    @endif

    <form method="POST" action="{{ route('admin.quotes.store') }}">
        @csrf
        @include('admin.quotes._form')
    </form>
@endsection

