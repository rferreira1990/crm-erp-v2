@extends('layouts.admin')

@section('title', 'Ficha do Pagamento a Fornecedor')
@section('page_title', 'Ficha do Pagamento a Fornecedor')
@section('page_subtitle', $payment->number)

@section('page_actions')
    <a href="{{ route('admin.supplier-payments.index') }}" class="btn btn-phoenix-secondary btn-sm">Voltar</a>
    <a href="{{ route('admin.purchase-documents.show', $payment->purchaseDocument->id) }}" class="btn btn-phoenix-secondary btn-sm">Ver documento</a>
    @can('company.supplier_payments.pdf')
        <form method="POST" action="{{ route('admin.supplier-payments.pdf.generate', $payment->id) }}" class="d-inline">
            @csrf
            <button type="submit" class="btn btn-phoenix-secondary btn-sm">Gerar PDF</button>
        </form>
        @if ($payment->pdf_path)
            <a href="{{ route('admin.supplier-payments.pdf.download', $payment->id) }}" class="btn btn-phoenix-secondary btn-sm">Download PDF</a>
        @endif
    @endcan
    @can('company.supplier_payments.cancel')
        @if ($payment->canCancel())
            <form method="POST" action="{{ route('admin.supplier-payments.cancel', $payment->id) }}" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-phoenix-danger btn-sm">Cancelar pagamento</button>
            </form>
        @endif
    @endcan
@endsection

@section('breadcrumbs')
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.supplier-payments.index') }}">Pagamentos a Fornecedor</a></li>
        <li class="breadcrumb-item active" aria-current="page">{{ $payment->number }}</li>
    </ol>
@endsection

@section('content')
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
    @endif

    @php
        $documentTotal = (float) $payment->purchaseDocument->grand_total;
        $paymentAmount = (float) $payment->amount;
        $paymentCoverage = $documentTotal > 0 ? min(100, ($paymentAmount / $documentTotal) * 100) : 0;
        $isCancelled = $payment->status === \App\Models\SupplierPayment::STATUS_CANCELLED;
    @endphp

    <div class="card mb-4">
        <div class="card-body">
            <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="flex-shrink-0 d-flex align-items-center justify-content-center rounded-circle bg-body-highlight" style="width: 72px; height: 72px;">
                        <span class="fw-bold fs-4">{{ mb_substr((string) ($payment->supplier?->name ?? 'F'), 0, 1) }}</span>
                    </div>
                    <div>
                        <h3 class="mb-1">{{ $payment->number }}</h3>
                        <div class="d-flex flex-wrap gap-2 align-items-center">
                            <span class="badge badge-phoenix {{ $payment->statusBadgeClass() }}">{{ $statusLabels[$payment->status] ?? $payment->status }}</span>
                            <span class="badge badge-phoenix {{ $payment->purchaseDocument->paymentStatusBadgeClass() }}">
                                {{ $paymentStatusLabels[$payment->purchaseDocument->payment_status] ?? $payment->purchaseDocument->paymentStatusLabel() }}
                            </span>
                        </div>
                        <div class="text-body-secondary mt-1">
                            {{ $payment->supplier?->name ?? '-' }} · {{ $payment->supplier?->email ?? '-' }}
                        </div>
                    </div>
                </div>

                <div class="d-flex flex-wrap gap-2">
                    @if ($payment->pdf_path)
                        <a href="{{ route('admin.supplier-payments.pdf.download', $payment->id) }}" class="btn btn-phoenix-secondary btn-sm">PDF</a>
                    @endif
                    <a href="{{ route('admin.purchase-documents.show', $payment->purchaseDocument->id) }}" class="btn btn-phoenix-secondary btn-sm">Documento de Compra</a>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-12 col-md-6 col-xl-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-body-tertiary fs-9">Valor pago</div>
                    <div class="h4 mb-0 {{ $isCancelled ? 'text-body-tertiary text-decoration-line-through' : 'text-success' }}">
                        {{ number_format($paymentAmount, 2, ',', '.') }} &euro;
                    </div>
                    <div class="text-body-secondary fs-10 mt-2">Montante deste pagamento</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-body-tertiary fs-9">Cobertura do documento</div>
                    <div class="h4 mb-0">{{ number_format($paymentCoverage, 1, ',', '.') }}%</div>
                    <div class="text-body-secondary fs-10 mt-2">Face ao total do Documento de Compra</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-body-tertiary fs-9">Documento associado</div>
                    <div class="h4 mb-0">{{ $payment->purchaseDocument->number }}</div>
                    <div class="text-body-secondary fs-10 mt-2">{{ number_format($documentTotal, 2, ',', '.') }} {{ $payment->purchaseDocument->currency }}</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-body-tertiary fs-9">Data de emissao</div>
                    <div class="h4 mb-0">{{ optional($payment->issued_at)->format('Y-m-d') ?? '-' }}</div>
                    <div class="text-body-secondary fs-10 mt-2">{{ optional($payment->payment_date)->format('Y-m-d') ? 'Pagamento em '.optional($payment->payment_date)->format('Y-m-d') : 'Sem data de pagamento' }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-12 col-xl-8">
            <div class="card mb-4">
                <div class="card-header bg-body-tertiary">
                    <h5 class="mb-0">Dados do pagamento</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12 col-md-4">
                            <div class="text-body-tertiary fs-9">Data pagamento</div>
                            <div class="fw-semibold">{{ optional($payment->payment_date)->format('Y-m-d') ?? '-' }}</div>
                        </div>
                        <div class="col-12 col-md-4">
                            <div class="text-body-tertiary fs-9">Valor</div>
                            <div class="fw-semibold">{{ number_format((float) $payment->amount, 2, ',', '.') }} &euro;</div>
                        </div>
                        <div class="col-12 col-md-4">
                            <div class="text-body-tertiary fs-9">Modo pagamento</div>
                            <div class="fw-semibold">{{ $payment->paymentMethod?->name ?? '-' }}</div>
                        </div>
                        <div class="col-12 col-md-6">
                            <div class="text-body-tertiary fs-9">Fornecedor</div>
                            <div class="fw-semibold">{{ $payment->supplier?->name ?? '-' }}</div>
                        </div>
                        <div class="col-12 col-md-6">
                            <div class="text-body-tertiary fs-9">Documento de Compra</div>
                            <div class="fw-semibold"><a href="{{ route('admin.purchase-documents.show', $payment->purchaseDocument->id) }}">{{ $payment->purchaseDocument->number }}</a></div>
                        </div>
                        <div class="col-12">
                            <div class="text-body-tertiary fs-9">Notas</div>
                            <div class="fw-semibold">{{ $payment->notes ?: '-' }}</div>
                        </div>
                    </div>
                </div>
            </div>

            @can('company.supplier_payments.send')
                <div class="card mb-4">
                    <div class="card-header bg-body-tertiary">
                        <h5 class="mb-0">Enviar por email</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('admin.supplier-payments.email.send', $payment->id) }}" class="row g-3">
                            @csrf
                            <div class="col-12 col-lg-6">
                                <label for="to" class="form-label">Para</label>
                                <input type="email" id="to" name="to" class="form-control @error('to') is-invalid @enderror" value="{{ old('to', $payment->supplier?->email) }}" required>
                                @error('to')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12 col-lg-6">
                                <label for="cc" class="form-label">CC</label>
                                <input type="text" id="cc" name="cc" class="form-control @error('cc') is-invalid @enderror" value="{{ old('cc') }}" placeholder="email1@dominio.pt; email2@dominio.pt">
                                @error('cc')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12">
                                <label for="subject" class="form-label">Assunto</label>
                                <input type="text" id="subject" name="subject" class="form-control @error('subject') is-invalid @enderror" value="{{ old('subject', \App\Mail\Admin\SupplierPaymentSentMail::defaultSubjectForPayment($payment)) }}" required>
                                @error('subject')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12">
                                <label for="message" class="form-label">Mensagem</label>
                                <textarea id="message" name="message" rows="4" class="form-control @error('message') is-invalid @enderror">{{ old('message', 'Segue em anexo o Documento de Pagamento para vosso registo.') }}</textarea>
                                @error('message')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12 d-flex justify-content-end">
                                <button type="submit" class="btn btn-primary btn-sm">Enviar pagamento</button>
                            </div>
                        </form>
                    </div>
                </div>
            @endcan
        </div>

        <div class="col-12 col-xl-4">
            <div class="card mb-4">
                <div class="card-header bg-body-tertiary">
                    <h5 class="mb-0">Estado do documento</h5>
                </div>
                <div class="card-body">
                    <div class="mb-2"><span class="text-body-tertiary">Documento:</span> <span class="fw-semibold">{{ $payment->purchaseDocument->number }}</span></div>
                    <div class="mb-2"><span class="text-body-tertiary">Estado:</span> <span class="fw-semibold">{{ $payment->purchaseDocument->statusLabel() }}</span></div>
                    <div class="mb-2"><span class="text-body-tertiary">Pagamento:</span> <span class="fw-semibold">{{ $paymentStatusLabels[$payment->purchaseDocument->payment_status] ?? $payment->purchaseDocument->paymentStatusLabel() }}</span></div>
                    <div class="mb-0"><span class="text-body-tertiary">Total documento:</span> <span class="fw-semibold">{{ number_format((float) $payment->purchaseDocument->grand_total, 2, ',', '.') }} {{ $payment->purchaseDocument->currency }}</span></div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header bg-body-tertiary">
                    <h5 class="mb-0">Rastreabilidade</h5>
                </div>
                <div class="card-body">
                    <div class="mb-2"><span class="text-body-tertiary">Criado por:</span> <span class="fw-semibold">{{ $payment->creator?->name ?? '-' }}</span></div>
                    <div class="mb-2"><span class="text-body-tertiary">Cancelado por:</span> <span class="fw-semibold">{{ $payment->canceller?->name ?? '-' }}</span></div>
                    <div class="mb-2"><span class="text-body-tertiary">Emitido em:</span> <span class="fw-semibold">{{ optional($payment->issued_at)->format('Y-m-d H:i') ?? '-' }}</span></div>
                    <div class="mb-0"><span class="text-body-tertiary">Ultimo envio email:</span> <span class="fw-semibold">{{ optional($payment->email_last_sent_at)->format('Y-m-d H:i') ?? '-' }}</span></div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header bg-body-tertiary">
                    <h5 class="mb-0">Contacto fornecedor</h5>
                </div>
                <div class="card-body">
                    <div class="mb-2"><span class="text-body-tertiary">Email:</span> <span class="fw-semibold">{{ $payment->supplier?->email ?? '-' }}</span></div>
                    <div class="mb-2"><span class="text-body-tertiary">Telefone:</span> <span class="fw-semibold">{{ $payment->supplier?->phone ?: ($payment->supplier?->mobile ?: '-') }}</span></div>
                    <div class="mb-0"><span class="text-body-tertiary">NIF:</span> <span class="fw-semibold">{{ $payment->supplier?->nif ?: '-' }}</span></div>
                </div>
            </div>
        </div>
    </div>
@endsection
