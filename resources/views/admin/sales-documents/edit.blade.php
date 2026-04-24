@extends('layouts.admin')

@section('title', 'Editar Documento de Venda')
@section('page_title', 'Editar Documento de Venda')
@section('page_subtitle', $document->number)

@section('page_actions')
    <a href="{{ route('admin.sales-documents.show', $document->id) }}" class="btn btn-phoenix-secondary btn-sm">Voltar</a>
@endsection

@section('breadcrumbs')
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.sales-documents.index') }}">Documentos de Venda</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.sales-documents.show', $document->id) }}">{{ $document->number }}</a></li>
        <li class="breadcrumb-item active" aria-current="page">Editar</li>
    </ol>
@endsection

@section('content')
    @include('admin.sales-documents._form', [
        'isEdit' => true,
        'formAction' => route('admin.sales-documents.update', $document->id),
    ])
@endsection

