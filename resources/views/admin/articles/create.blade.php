@extends('layouts.admin')

@section('title', 'Novo artigo')
@section('page_title', 'Novo artigo')
@section('page_subtitle', 'Criar artigo da empresa')

@section('breadcrumbs')
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.articles.index') }}">Artigos</a></li>
        <li class="breadcrumb-item active" aria-current="page">Novo</li>
    </ol>
@endsection

@section('content')
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">
            {{ $errors->first() }}
        </div>
    @endif

    <form method="POST" action="{{ route('admin.articles.store') }}" enctype="multipart/form-data">
        @csrf
        @include('admin.articles._form')
    </form>
@endsection
