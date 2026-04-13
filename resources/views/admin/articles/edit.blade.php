@extends('layouts.admin')

@section('title', 'Editar artigo')
@section('page_title', 'Editar artigo')
@section('page_subtitle', 'Atualizar artigo e anexos')

@section('breadcrumbs')
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.articles.index') }}">Artigos</a></li>
        <li class="breadcrumb-item active" aria-current="page">Editar</li>
    </ol>
@endsection

@section('content')
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">
            {{ $errors->first() }}
        </div>
    @endif

    <form method="POST" action="{{ route('admin.articles.update', $article->id) }}" enctype="multipart/form-data">
        @csrf
        @method('PATCH')
        @include('admin.articles._form', ['article' => $article])
    </form>
@endsection
