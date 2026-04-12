@extends('layouts.admin')

@section('title', 'Editar taxa de IVA')
@section('page_title', 'Editar taxa de IVA')
@section('page_subtitle', 'Atualizar taxa de IVA personalizada da empresa')

@section('breadcrumbs')
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.vat-rates.index') }}">Taxas de IVA</a></li>
        <li class="breadcrumb-item active" aria-current="page">Editar</li>
    </ol>
@endsection

@section('content')
    <div class="card">
        <div class="card-header bg-body-tertiary">
            <h5 class="mb-0">Editar taxa de IVA</h5>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.vat-rates.update', $vatRate->id) }}">
                @csrf
                @method('PATCH')
                @include('admin.vat-rates._form', ['vatRate' => $vatRate])
            </form>
        </div>
    </div>
@endsection

