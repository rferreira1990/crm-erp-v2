@extends('layouts.admin')

@section('title', 'Novo registo do diario')
@section('page_title', 'Novo registo do diario')
@section('page_subtitle', $site->code.' - '.$site->name)

@section('page_actions')
    <a href="{{ route('admin.construction-sites.logs.index', $site->id) }}" class="btn btn-phoenix-secondary btn-sm">Ver todos os logs</a>
@endsection

@section('breadcrumbs')
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.construction-sites.index') }}">Obras</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.construction-sites.show', $site->id) }}">{{ $site->code }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.construction-sites.logs.index', $site->id) }}">Diario</a></li>
        <li class="breadcrumb-item active" aria-current="page">Novo registo</li>
    </ol>
@endsection

@section('content')
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('admin.construction-sites.logs.store', $site->id) }}" enctype="multipart/form-data">
        @csrf
        @include('admin.construction-site-logs._form')
    </form>
@endsection
