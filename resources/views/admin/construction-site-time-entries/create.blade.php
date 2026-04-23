@extends('layouts.admin')

@section('title', 'Registar horas')
@section('page_title', 'Registar horas')
@section('page_subtitle', $site->code.' - '.$site->name)

@section('breadcrumbs')
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.construction-sites.index') }}">Obras</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.construction-sites.show', $site->id) }}">{{ $site->code }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.construction-site-time-entries.index', ['construction_site_id' => $site->id]) }}">Horas</a></li>
        <li class="breadcrumb-item active" aria-current="page">Novo</li>
    </ol>
@endsection

@section('content')
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
    @endif

    @include('admin.construction-site-time-entries._form', [
        'formAction' => route('admin.construction-sites.time-entries.store', $site->id),
        'formMethod' => 'POST',
        'submitLabel' => 'Guardar lancamento',
        'cancelUrl' => route('admin.construction-site-time-entries.index', ['construction_site_id' => $site->id]),
        'workerOptions' => $workerOptions,
        'taskTypeOptions' => $taskTypeOptions,
    ])
@endsection
