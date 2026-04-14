@extends('layouts.admin')

@section('title', 'Editar orcamento')
@section('page_title', 'Editar orcamento')
@section('page_subtitle', 'Atualizar proposta comercial')

@section('page_actions')
    <a href="{{ route('admin.quotes.show', $quote->id) }}" class="btn btn-phoenix-secondary btn-sm">Ver ficha</a>
@endsection

@section('breadcrumbs')
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.quotes.index') }}">Orcamentos</a></li>
        <li class="breadcrumb-item active" aria-current="page">Editar</li>
    </ol>
@endsection

@section('content')
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">
            {{ $errors->first() }}
        </div>
    @endif

    <form method="POST" action="{{ route('admin.quotes.update', $quote->id) }}">
        @csrf
        @method('PATCH')
        @include('admin.quotes._form', ['quote' => $quote])
    </form>
@endsection
