@extends('layouts.admin')

@section('title', 'Registar Pagamento a Fornecedor')
@section('page_title', 'Registar Pagamento a Fornecedor')
@section('page_subtitle', $document->number)

@section('page_actions')
    <a href="{{ route('admin.purchase-documents.show', $document->id) }}" class="btn btn-phoenix-secondary btn-sm">Voltar ao documento</a>
@endsection

@section('breadcrumbs')
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.purchase-documents.index') }}">Documentos de Compra</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.purchase-documents.show', $document->id) }}">{{ $document->number }}</a></li>
        <li class="breadcrumb-item active" aria-current="page">Registar pagamento</li>
    </ol>
@endsection

@section('content')
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
    @endif

    <div class="row g-4">
        <div class="col-12 col-lg-8">
            <div class="card">
                <div class="card-header bg-body-tertiary">
                    <h5 class="mb-0">Dados do pagamento</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.supplier-payments.store', $document->id) }}" class="row g-3">
                        @csrf
                        <div class="col-12 col-md-6">
                            <label for="payment_date" class="form-label">Data</label>
                            <input type="date" id="payment_date" name="payment_date" class="form-control @error('payment_date') is-invalid @enderror" value="{{ old('payment_date', now()->toDateString()) }}" required>
                            @error('payment_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="amount" class="form-label">Valor</label>
                            <input type="number" id="amount" name="amount" class="form-control @error('amount') is-invalid @enderror" step="0.01" min="0.01" value="{{ old('amount', number_format((float) $openAmount, 2, '.', '')) }}" required>
                            @error('amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <label for="payment_method_id" class="form-label">Modo de pagamento (opcional)</label>
                            <select id="payment_method_id" name="payment_method_id" class="form-select @error('payment_method_id') is-invalid @enderror">
                                <option value="">Sem modo definido</option>
                                @foreach ($paymentMethods as $paymentMethod)
                                    <option value="{{ $paymentMethod->id }}" @selected((string) old('payment_method_id') === (string) $paymentMethod->id)>
                                        {{ $paymentMethod->name }}{{ $paymentMethod->is_system ? ' (Sistema)' : '' }}
                                    </option>
                                @endforeach
                            </select>
                            @error('payment_method_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <label for="notes" class="form-label">Notas</label>
                            <textarea id="notes" name="notes" rows="3" class="form-control @error('notes') is-invalid @enderror">{{ old('notes') }}</textarea>
                            @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12 d-flex justify-content-end gap-2">
                            <a href="{{ route('admin.purchase-documents.show', $document->id) }}" class="btn btn-phoenix-secondary">Cancelar</a>
                            <button type="submit" class="btn btn-primary">Registar pagamento</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-4">
            <div class="card">
                <div class="card-header bg-body-tertiary">
                    <h5 class="mb-0">Resumo documento</h5>
                </div>
                <div class="card-body">
                    <div class="mb-2"><span class="text-body-tertiary">Documento:</span> <span class="fw-semibold">{{ $document->number }}</span></div>
                    <div class="mb-2"><span class="text-body-tertiary">Fornecedor:</span> <span class="fw-semibold">{{ $document->supplier?->name ?? '-' }}</span></div>
                    <div class="mb-2"><span class="text-body-tertiary">Total:</span> <span class="fw-semibold">{{ number_format((float) $document->grand_total, 2, ',', '.') }} {{ $document->currency }}</span></div>
                    <div class="mb-0"><span class="text-body-tertiary">Valor em aberto:</span> <span class="fw-bold">{{ number_format((float) $openAmount, 2, ',', '.') }} {{ $document->currency }}</span></div>
                </div>
            </div>
        </div>
    </div>
@endsection
