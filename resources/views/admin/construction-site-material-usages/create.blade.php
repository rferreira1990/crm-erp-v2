@extends('layouts.admin')

@section('title', 'Registar consumo de material')
@section('page_title', 'Registar consumo de material')
@section('page_subtitle', $site->code.' - '.$site->name)

@section('breadcrumbs')
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.construction-sites.index') }}">Obras</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.construction-sites.show', $site->id) }}">{{ $site->code }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.construction-sites.material-usages.index', $site->id) }}">Consumos</a></li>
        <li class="breadcrumb-item active" aria-current="page">Novo</li>
    </ol>
@endsection

@section('content')
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
    @endif

    @include('admin.construction-site-material-usages._form', [
        'formAction' => route('admin.construction-sites.material-usages.store', $site->id),
        'formMethod' => 'POST',
        'submitLabel' => 'Guardar em rascunho',
        'cancelUrl' => route('admin.construction-sites.material-usages.index', $site->id),
        'articleOptions' => $articleOptions,
    ])
@endsection
