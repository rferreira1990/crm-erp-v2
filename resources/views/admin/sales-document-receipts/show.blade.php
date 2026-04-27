@extends('layouts.admin')

@section('title', 'Ficha do recibo')
@section('page_title', 'Ficha do recibo')
@section('page_subtitle', $receipt->number)

@section('page_actions')
    <a href="{{ route('admin.sales-document-receipts.index') }}" class="btn btn-phoenix-secondary btn-sm">Voltar</a>
    @can('company.sales_document_receipts.pdf')
        <form method="POST" action="{{ route('admin.sales-document-receipts.pdf.generate', $receipt->id) }}" class="d-inline">
            @csrf
            <button type="submit" class="btn btn-phoenix-secondary btn-sm">Gerar PDF</button>
        </form>
        @if ($receipt->pdf_path)
            <a href="{{ route('admin.sales-document-receipts.pdf.download', $receipt->id) }}" class="btn btn-phoenix-secondary btn-sm">Download PDF</a>
        @endif
    @endcan
    @can('company.sales_document_receipts.send')
        <a href="#receipt-email-form" class="btn btn-primary btn-sm">Enviar por email</a>
    @endcan
    @can('company.sales_document_receipts.cancel')
        @if ($receipt->canCancel())
            <form method="POST" action="{{ route('admin.sales-document-receipts.cancel', $receipt->id) }}" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-phoenix-danger btn-sm">Cancelar recibo</button>
            </form>
        @endif
    @endcan
@endsection

@section('breadcrumbs')
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.sales-document-receipts.index') }}">Recibos</a></li>
        <li class="breadcrumb-item active" aria-current="page">{{ $receipt->number }}</li>
    </ol>
@endsection

@section('content')
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
    @endif

    <div class="row g-4">
        <div class="col-12 col-xxl-8">
            <div class="card mb-4">
                <div class="card-header bg-body-tertiary d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">{{ $receipt->number }}</h5>
                    <span class="badge badge-phoenix {{ $receipt->statusBadgeClass() }}">{{ $statusLabels[$receipt->status] ?? $receipt->status }}</span>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12 col-md-4">
                            <div class="text-body-tertiary fs-9">Data</div>
                            <div class="fw-semibold">{{ optional($receipt->receipt_date)->format('Y-m-d') ?? '-' }}</div>
                        </div>
                        <div class="col-12 col-md-4">
                            <div class="text-body-tertiary fs-9">Valor</div>
                            <div class="fw-semibold">{{ number_format((float) $receipt->amount, 2, ',', '.') }}</div>
                        </div>
                        <div class="col-12 col-md-4">
                            <div class="text-body-tertiary fs-9">Modo de pagamento</div>
                            <div class="fw-semibold">{{ $receipt->paymentMethod?->name ?? '-' }}</div>
                        </div>
                        <div class="col-12 col-md-8">
                            <div class="text-body-tertiary fs-9">Cliente</div>
                            <div class="fw-semibold">{{ $receipt->customer?->name ?? '-' }}</div>
                        </div>
                        <div class="col-12 col-md-4">
                            <div class="text-body-tertiary fs-9">Documento</div>
                            <div class="fw-semibold">
                                @if ($receipt->salesDocument)
                                    <a href="{{ route('admin.sales-documents.show', $receipt->salesDocument->id) }}">{{ $receipt->salesDocument->number }}</a>
                                @else
                                    -
                                @endif
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="text-body-tertiary fs-9">Notas</div>
                            <div class="fw-semibold">{{ $receipt->notes ?: '-' }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header bg-body-tertiary">
                    <h5 class="mb-0">Estado de pagamento do Documento de Venda</h5>
                </div>
                <div class="card-body">
                    @if ($receipt->salesDocument)
                        <div class="d-flex flex-wrap gap-3 align-items-center">
                            <span class="text-body-tertiary">{{ $receipt->salesDocument->number }}</span>
                            <span class="badge badge-phoenix {{ $receipt->salesDocument->paymentStatusBadgeClass() }}">
                                {{ $paymentStatusLabels[$receipt->salesDocument->payment_status] ?? $receipt->salesDocument->paymentStatusLabel() }}
                            </span>
                            <span class="fw-semibold">Total: {{ number_format((float) $receipt->salesDocument->grand_total, 2, ',', '.') }} {{ $receipt->salesDocument->currency }}</span>
                        </div>
                    @else
                        <span class="text-body-tertiary">Documento associado indisponivel.</span>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-12 col-xxl-4">
            <div class="card mb-4">
                <div class="card-header bg-body-tertiary">
                    <h5 class="mb-0">Rastreabilidade</h5>
                </div>
                <div class="card-body">
                    <div class="mb-2">
                        <div class="text-body-tertiary fs-9">Emitido em</div>
                        <div class="fw-semibold">{{ optional($receipt->issued_at)->format('Y-m-d H:i') ?? '-' }}</div>
                    </div>
                    <div class="mb-2">
                        <div class="text-body-tertiary fs-9">Emitido por</div>
                        <div class="fw-semibold">{{ $receipt->creator?->name ?? '-' }}</div>
                    </div>
                    <div class="mb-2">
                        <div class="text-body-tertiary fs-9">Cancelado em</div>
                        <div class="fw-semibold">{{ optional($receipt->cancelled_at)->format('Y-m-d H:i') ?? '-' }}</div>
                    </div>
                    <div>
                        <div class="text-body-tertiary fs-9">Cancelado por</div>
                        <div class="fw-semibold">{{ $receipt->canceller?->name ?? '-' }}</div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header bg-body-tertiary">
                    <h5 class="mb-0">Cliente</h5>
                </div>
                <div class="card-body">
                    <div class="mb-2"><span class="text-body-tertiary">NIF:</span> <span class="fw-semibold">{{ $receipt->customer?->nif ?: '-' }}</span></div>
                    <div class="mb-2"><span class="text-body-tertiary">Email:</span> <span class="fw-semibold">{{ $receipt->customer?->email ?: '-' }}</span></div>
                    <div><span class="text-body-tertiary">Telefone:</span> <span class="fw-semibold">{{ $receipt->customer?->phone ?: ($receipt->customer?->mobile ?: '-') }}</span></div>
                </div>
            </div>

            @can('company.sales_document_receipts.send')
                <div class="card mt-4" id="receipt-email-form">
                    <div class="card-header bg-body-tertiary">
                        <h5 class="mb-0">Enviar por email</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('admin.sales-document-receipts.email.send', $receipt->id) }}" class="row g-3">
                            @csrf
                            <div class="col-12">
                                <label for="to" class="form-label">Para</label>
                                <input type="email" id="to" name="to" value="{{ old('to', $receipt->customer?->email) }}" class="form-control form-control-sm @error('to') is-invalid @enderror" required>
                                @error('to')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12">
                                <label for="cc" class="form-label">CC (opcional)</label>
                                <input type="text" id="cc" name="cc" value="{{ old('cc') }}" class="form-control form-control-sm @error('cc') is-invalid @enderror" placeholder="email1@empresa.pt, email2@empresa.pt">
                                @error('cc')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12">
                                <label for="subject" class="form-label">Assunto</label>
                                <input type="text" id="subject" name="subject" value="{{ old('subject', \App\Mail\Admin\SalesDocumentReceiptSentMail::defaultSubjectForReceipt($receipt)) }}" class="form-control form-control-sm @error('subject') is-invalid @enderror" required>
                                @error('subject')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12">
                                <label for="message" class="form-label">Mensagem</label>
                                <textarea id="message" name="message" rows="4" class="form-control form-control-sm @error('message') is-invalid @enderror">{{ old('message') }}</textarea>
                                @error('message')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary w-100">Enviar recibo</button>
                            </div>
                        </form>
                    </div>
                </div>
            @endcan
        </div>
    </div>
@endsection
