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
    <div class="card">
        <div class="card-header bg-body-tertiary">
            <h5 class="mb-0">Criar artigo</h5>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.articles.store') }}" enctype="multipart/form-data">
                @csrf
                @include('admin.articles._form')
            </form>
        </div>
    </div>
@endsection

