@extends('layouts.admin')

@section('title', 'Editar rececao')
@section('page_title', 'Editar rececao')
@section('page_subtitle', $receipt->number)

@section('page_actions')
    <a href="{{ route('admin.purchase-order-receipts.show', $receipt->id) }}" class="btn btn-phoenix-secondary btn-sm">Voltar</a>
@endsection

@section('breadcrumbs')
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.purchase-order-receipts.index') }}">Rececoes de encomendas</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.purchase-order-receipts.show', $receipt->id) }}">{{ $receipt->number }}</a></li>
        <li class="breadcrumb-item active" aria-current="page">Editar</li>
    </ol>
@endsection

@section('content')
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
    @endif

    <div class="card mb-4">
        <div class="card-header bg-body-tertiary">
            <h5 class="mb-0">Resumo da rececao</h5>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-12 col-md-4">
                    <div class="text-body-tertiary fs-9">Rececao</div>
                    <div class="fw-semibold">{{ $receipt->number }}</div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="text-body-tertiary fs-9">Encomenda</div>
                    <div class="fw-semibold">
                        <a href="{{ route('admin.purchase-orders.show', $purchaseOrder->id) }}">{{ $purchaseOrder->number }}</a>
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="text-body-tertiary fs-9">Fornecedor</div>
                    <div class="fw-semibold">{{ $purchaseOrder->supplier_name_snapshot }}</div>
                </div>
            </div>
        </div>
    </div>

    @include('admin.purchase-order-receipts._form', [
        'formAction' => route('admin.purchase-order-receipts.update', $receipt->id),
        'formMethod' => 'PATCH',
        'submitLabel' => 'Atualizar rascunho',
        'purchaseOrder' => $purchaseOrder,
        'receipt' => $receipt,
        'lines' => $lines,
    ])
@endsection
