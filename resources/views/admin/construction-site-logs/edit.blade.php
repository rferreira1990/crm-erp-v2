@extends('layouts.admin')

@section('title', 'Editar registo do diario')
@section('page_title', 'Editar registo do diario')
@section('page_subtitle', $site->code.' - '.$log->title)

@section('page_actions')
    <a href="{{ route('admin.construction-sites.logs.show', [$site->id, $log->id]) }}" class="btn btn-phoenix-secondary btn-sm">Ver registo</a>
@endsection

@section('breadcrumbs')
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.construction-sites.index') }}">Obras</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.construction-sites.show', $site->id) }}">{{ $site->code }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.construction-sites.logs.index', $site->id) }}">Diario</a></li>
        <li class="breadcrumb-item active" aria-current="page">Editar registo</li>
    </ol>
@endsection

@section('content')

    @if ($errors->any())
        <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('admin.construction-sites.logs.update', [$site->id, $log->id]) }}" enctype="multipart/form-data">
        @csrf
        @method('PATCH')
        @include('admin.construction-site-logs._form', ['log' => $log])
    </form>
@endsection
