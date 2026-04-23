@extends('layouts.admin')

@section('title', 'Editar obra')
@section('page_title', 'Editar obra')
@section('page_subtitle', $site->code.' - '.$site->name)

@section('page_actions')
    <a href="{{ route('admin.construction-sites.show', $site->id) }}" class="btn btn-phoenix-secondary btn-sm">Ver ficha</a>
@endsection

@section('breadcrumbs')
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.construction-sites.index') }}">Obras</a></li>
        <li class="breadcrumb-item active" aria-current="page">Editar</li>
    </ol>
@endsection

@section('content')

    @if ($errors->any())
        <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('admin.construction-sites.update', $site->id) }}" enctype="multipart/form-data">
        @csrf
        @method('PATCH')
        @include('admin.construction-sites._form', ['site' => $site])
    </form>
@endsection
