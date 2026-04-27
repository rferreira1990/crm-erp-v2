@extends('layouts.admin')

@section('title', 'Emitir recibo')
@section('page_title', 'Emitir recibo')
@section('page_subtitle', $document->number)

@section('page_actions')
    <a href="{{ route('admin.sales-documents.show', $document->id) }}" class="btn btn-phoenix-secondary btn-sm">Voltar</a>
@endsection

@section('breadcrumbs')
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.sales-documents.index') }}">Documentos de Venda</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.sales-documents.show', $document->id) }}">{{ $document->number }}</a></li>
        <li class="breadcrumb-item active" aria-current="page">Emitir recibo</li>
    </ol>
@endsection

@section('content')
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
    @endif

    <div class="row g-4">
        <div class="col-12 col-xl-8">
            <div class="card">
                <div class="card-header bg-body-tertiary">
                    <h5 class="mb-0">Dados do recibo</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.sales-document-receipts.store', $document->id) }}" class="row g-3">
                        @csrf
                        <div class="col-12 col-md-4">
                            <label for="receipt_date" class="form-label">Data do recibo</label>
                            <input type="date" id="receipt_date" name="receipt_date" value="{{ old('receipt_date', now()->toDateString()) }}" class="form-control @error('receipt_date') is-invalid @enderror" required>
                            @error('receipt_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12 col-md-4">
                            <label for="amount" class="form-label">Valor</label>
                            <input type="text" id="amount" name="amount" value="{{ old('amount', number_format((float) $openAmount, 2, '.', '')) }}" class="form-control @error('amount') is-invalid @enderror" required>
                            @error('amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12 col-md-4">
                            <label for="payment_method_id" class="form-label">Modo de pagamento</label>
                            <select id="payment_method_id" name="payment_method_id" class="form-select @error('payment_method_id') is-invalid @enderror">
                                <option value="">Sem modo definido</option>
                                @foreach ($paymentMethods as $method)
                                    <option value="{{ $method->id }}" @selected((string) old('payment_method_id') === (string) $method->id)>{{ $method->name }}</option>
                                @endforeach
                            </select>
                            @error('payment_method_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <label for="notes" class="form-label">Notas</label>
                            <textarea id="notes" name="notes" rows="4" class="form-control @error('notes') is-invalid @enderror">{{ old('notes') }}</textarea>
                            @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12 d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary">Emitir recibo</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-4">
            <div class="card">
                <div class="card-header bg-body-tertiary">
                    <h5 class="mb-0">Resumo</h5>
                </div>
                <div class="card-body">
                    <div class="mb-2"><span class="text-body-tertiary">Documento:</span> <span class="fw-semibold">{{ $document->number }}</span></div>
                    <div class="mb-2"><span class="text-body-tertiary">Cliente:</span> <span class="fw-semibold">{{ $document->customer_name_snapshot ?: ($document->customer?->name ?? '-') }}</span></div>
                    <div class="mb-2"><span class="text-body-tertiary">Total documento:</span> <span class="fw-semibold">{{ number_format((float) $document->grand_total, 2, ',', '.') }} {{ $document->currency }}</span></div>
                    <div><span class="text-body-tertiary">Valor em aberto:</span> <span class="fw-bold">{{ number_format((float) $openAmount, 2, ',', '.') }} {{ $document->currency }}</span></div>
                </div>
            </div>
        </div>
    </div>
@endsection
