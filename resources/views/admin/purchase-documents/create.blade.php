@extends('layouts.admin')

@section('title', 'Novo Documento de Compra')
@section('page_title', 'Novo Documento de Compra')
@section('page_subtitle', 'Registo de documento recebido de fornecedor')

@section('page_actions')
    <a href="{{ route('admin.purchase-documents.index') }}" class="btn btn-phoenix-secondary btn-sm">Voltar</a>
@endsection

@section('breadcrumbs')
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.purchase-documents.index') }}">Documentos de Compra</a></li>
        <li class="breadcrumb-item active" aria-current="page">Novo</li>
    </ol>
@endsection

@section('content')
    @include('admin.purchase-documents._form', [
        'formAction' => route('admin.purchase-documents.store'),
        'isEdit' => false,
    ])
@endsection
