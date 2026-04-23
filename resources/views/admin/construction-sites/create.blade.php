@extends('layouts.admin')

@section('title', 'Nova obra')
@section('page_title', 'Nova obra')
@section('page_subtitle', 'Criar obra da empresa')

@section('breadcrumbs')
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.construction-sites.index') }}">Obras</a></li>
        <li class="breadcrumb-item active" aria-current="page">Nova</li>
    </ol>
@endsection

@section('content')
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('admin.construction-sites.store') }}" enctype="multipart/form-data">
        @csrf
        @include('admin.construction-sites._form')
    </form>
@endsection
