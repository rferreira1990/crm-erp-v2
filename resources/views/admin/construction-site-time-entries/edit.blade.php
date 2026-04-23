@extends('layouts.admin')

@section('title', 'Editar lancamento de horas')
@section('page_title', 'Editar lancamento de horas')
@section('page_subtitle', $site->code.' - '.$site->name)

@section('page_actions')
    <a href="{{ route('admin.construction-sites.time-entries.show', [$site->id, $entry->id]) }}" class="btn btn-phoenix-secondary btn-sm">Voltar</a>
@endsection

@section('breadcrumbs')
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.construction-sites.index') }}">Obras</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.construction-sites.show', $site->id) }}">{{ $site->code }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.construction-site-time-entries.index', ['construction_site_id' => $site->id]) }}">Horas</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.construction-sites.time-entries.show', [$site->id, $entry->id]) }}">Lancamento</a></li>
        <li class="breadcrumb-item active" aria-current="page">Editar</li>
    </ol>
@endsection

@section('content')
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
    @endif

    @include('admin.construction-site-time-entries._form', [
        'entry' => $entry,
        'formAction' => route('admin.construction-sites.time-entries.update', [$site->id, $entry->id]),
        'formMethod' => 'PATCH',
        'submitLabel' => 'Guardar alteracoes',
        'cancelUrl' => route('admin.construction-sites.time-entries.show', [$site->id, $entry->id]),
        'workerOptions' => $workerOptions,
        'taskTypeOptions' => $taskTypeOptions,
    ])
@endsection
