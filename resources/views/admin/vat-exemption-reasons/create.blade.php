@extends('layouts.admin')

@section('title', 'Novo motivo de isencao')
@section('page_title', 'Novo motivo de isencao')
@section('page_subtitle', 'Criar motivo de isencao personalizado da empresa')

@section('breadcrumbs')
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.vat-exemption-reasons.index') }}">Motivos de isencao</a></li>
        <li class="breadcrumb-item active" aria-current="page">Novo</li>
    </ol>
@endsection

@section('content')
    <div class="card">
        <div class="card-header bg-body-tertiary">
            <h5 class="mb-0">Criar motivo de isencao</h5>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.vat-exemption-reasons.store') }}">
                @csrf
                @include('admin.vat-exemption-reasons._form')
            </form>
        </div>
    </div>
@endsection

