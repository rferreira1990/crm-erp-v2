@extends('layouts.admin')

@section('title', 'Registar rececao')
@section('page_title', 'Registar rececao')
@section('page_subtitle', $purchaseOrder->number)

@section('breadcrumbs')
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.purchase-orders.index') }}">Encomendas a fornecedor</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.purchase-orders.show', $purchaseOrder->id) }}">{{ $purchaseOrder->number }}</a></li>
        <li class="breadcrumb-item active" aria-current="page">Registar rececao</li>
    </ol>
@endsection

@section('content')
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
    @endif

    <div class="card mb-4">
        <div class="card-header bg-body-tertiary">
            <h5 class="mb-0">Resumo da encomenda</h5>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-12 col-md-4">
                    <div class="text-body-tertiary fs-9">Encomenda</div>
                    <div class="fw-semibold">{{ $purchaseOrder->number }}</div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="text-body-tertiary fs-9">Fornecedor</div>
                    <div class="fw-semibold">{{ $purchaseOrder->supplier_name_snapshot }}</div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="text-body-tertiary fs-9">Estado atual</div>
                    <div class="fw-semibold">{{ $purchaseOrder->statusLabel() }}</div>
                </div>
            </div>
        </div>
    </div>

    @include('admin.purchase-order-receipts._form', [
        'formAction' => route('admin.purchase-order-receipts.store', $purchaseOrder->id),
        'formMethod' => 'POST',
        'submitLabel' => 'Guardar rececao em rascunho',
        'purchaseOrder' => $purchaseOrder,
        'lines' => $lines,
    ])
@endsection
