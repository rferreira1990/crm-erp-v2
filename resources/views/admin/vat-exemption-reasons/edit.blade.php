@extends('layouts.admin')

@section('title', 'Editar motivo de isencao')
@section('page_title', 'Editar motivo de isencao')
@section('page_subtitle', 'Atualizar motivo de isencao personalizado da empresa')

@section('breadcrumbs')
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.vat-exemption-reasons.index') }}">Motivos de isencao</a></li>
        <li class="breadcrumb-item active" aria-current="page">Editar</li>
    </ol>
@endsection

@section('content')
    <div class="card">
        <div class="card-header bg-body-tertiary">
            <h5 class="mb-0">Editar motivo de isencao</h5>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.vat-exemption-reasons.update', $reason->id) }}">
                @csrf
                @method('PATCH')
                @include('admin.vat-exemption-reasons._form', ['reason' => $reason])
            </form>
        </div>
    </div>
@endsection

