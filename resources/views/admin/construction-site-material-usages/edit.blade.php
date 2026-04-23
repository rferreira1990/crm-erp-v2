@extends('layouts.admin')

@section('title', 'Editar consumo de material')
@section('page_title', 'Editar consumo de material')
@section('page_subtitle', $usage->number)

@section('page_actions')
    <a href="{{ route('admin.construction-sites.material-usages.show', [$site->id, $usage->id]) }}" class="btn btn-phoenix-secondary btn-sm">Voltar</a>
@endsection

@section('breadcrumbs')
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.construction-sites.index') }}">Obras</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.construction-sites.show', $site->id) }}">{{ $site->code }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.construction-sites.material-usages.index', $site->id) }}">Consumos</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.construction-sites.material-usages.show', [$site->id, $usage->id]) }}">{{ $usage->number }}</a></li>
        <li class="breadcrumb-item active" aria-current="page">Editar</li>
    </ol>
@endsection

@section('content')
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
    @endif

    @include('admin.construction-site-material-usages._form', [
        'usage' => $usage,
        'formAction' => route('admin.construction-sites.material-usages.update', [$site->id, $usage->id]),
        'formMethod' => 'PATCH',
        'submitLabel' => 'Guardar alteracoes',
        'cancelUrl' => route('admin.construction-sites.material-usages.show', [$site->id, $usage->id]),
        'articleOptions' => $articleOptions,
    ])
@endsection
