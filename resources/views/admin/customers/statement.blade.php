@extends('layouts.admin')

@section('title', 'Conta Corrente do Cliente')
@section('page_title', 'Conta Corrente do Cliente')
@section('page_subtitle', $customer->name)

@section('page_actions')
    <a href="{{ route('admin.customers.show', $customer->id) }}" class="btn btn-phoenix-secondary btn-sm">Voltar ao cliente</a>
    @can('company.customer_statement.pdf')
        <a href="{{ route('admin.customers.statement.pdf.download', ['customer' => $customer->id, ...array_filter($filters)]) }}" class="btn btn-phoenix-secondary btn-sm" data-live-table-export>Exportar PDF</a>
    @endcan
    @can('company.customer_statement.send')
        <a href="#statement-email-form" class="btn btn-primary btn-sm">Enviar extrato</a>
    @endcan
@endsection

@section('breadcrumbs')
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.customers.index') }}">Clientes</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.customers.show', $customer->id) }}">{{ $customer->name }}</a></li>
        <li class="breadcrumb-item active" aria-current="page">Conta Corrente</li>
    </ol>
@endsection

@section('content')
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
    @endif

    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.customers.statement.show', $customer->id) }}" class="row g-3 align-items-end" data-live-table-form data-live-table-target="#customer-statement-live-content" data-live-table-export-selector="[data-live-table-export]">
                <div class="col-12 col-md-4">
                    <label for="date_from" class="form-label">Data inicio</label>
                    <input type="date" id="date_from" name="date_from" class="form-control" value="{{ $filters['date_from'] ?? '' }}">
                </div>
                <div class="col-12 col-md-4">
                    <label for="date_to" class="form-label">Data fim</label>
                    <input type="date" id="date_to" name="date_to" class="form-control" value="{{ $filters['date_to'] ?? '' }}">
                </div>
                <div class="col-12 col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-fill">Filtrar</button>
                    <a href="{{ route('admin.customers.statement.show', $customer->id) }}" class="btn btn-phoenix-secondary flex-fill">Limpar</a>
                </div>
            </form>
        </div>
    </div>

    <div id="customer-statement-live-content">
        <div class="text-body-tertiary fs-9 mt-2">{{ $periodLabel }}</div>

        <div class="row g-4 mb-4">
            <div class="col-12 col-md-4">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="text-body-tertiary fs-9">Debitos</div>
                        <div class="h4 mb-0">{{ number_format((float) $totalDebit, 2, ',', '.') }} &euro;</div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="text-body-tertiary fs-9">Creditos</div>
                        <div class="h4 mb-0">{{ number_format((float) $totalCredit, 2, ',', '.') }} &euro;</div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="text-body-tertiary fs-9">Saldo</div>
                        <div class="h4 mb-0 {{ $balance > 0 ? 'text-danger' : ($balance < 0 ? 'text-success' : '') }}">{{ number_format((float) $balance, 2, ',', '.') }} &euro;</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm fs-9 mb-0">
                    <thead class="bg-body-tertiary">
                        <tr>
                            <th class="ps-3">Data</th>
                            <th>Tipo</th>
                            <th>Numero</th>
                            <th>Descricao</th>
                            <th>Debito</th>
                            <th>Credito</th>
                            <th>Saldo acumulado</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($movements as $movement)
                            <tr>
                                <td class="ps-3">{{ optional($movement['date'])->format('Y-m-d') ?? '-' }}</td>
                                <td>
                                    @if ($movement['type'] === 'sales_document')
                                        <span class="badge badge-phoenix badge-phoenix-warning">Documento de Venda</span>
                                    @else
                                        @if ($movement['status'] === \App\Models\SalesDocumentReceipt::STATUS_ISSUED)
                                            <span class="badge badge-phoenix badge-phoenix-success">Recibo</span>
                                        @else
                                            <span class="badge badge-phoenix badge-phoenix-secondary">Recibo cancelado</span>
                                        @endif
                                    @endif
                                </td>
                                <td>
                                    <a href="{{ $movement['route'] }}">{{ $movement['number'] }}</a>
                                </td>
                                <td>{{ $movement['description'] }}</td>
                                <td>{{ number_format((float) $movement['debit'], 2, ',', '.') }} &euro;</td>
                                <td>{{ number_format((float) $movement['credit'], 2, ',', '.') }} &euro;</td>
                                <td class="fw-semibold">{{ number_format((float) $movement['balance'], 2, ',', '.') }} &euro;</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-4 text-body-tertiary">Sem movimentos para este cliente.</td>
                            </tr>
                        @endforelse
                    </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    @can('company.customer_statement.send')
        <div class="card mt-4" id="statement-email-form">
            <div class="card-header bg-body-tertiary">
                <h5 class="mb-0">Enviar extrato por email</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.customers.statement.email.send', $customer->id) }}" class="row g-3">
                    @csrf
                    <input type="hidden" name="date_from" value="{{ $filters['date_from'] ?? '' }}">
                    <input type="hidden" name="date_to" value="{{ $filters['date_to'] ?? '' }}">
                    <div class="col-12 col-md-6">
                        <label for="to" class="form-label">Para</label>
                        <input type="email" id="to" name="to" value="{{ old('to', $customer->email) }}" class="form-control @error('to') is-invalid @enderror" required>
                        @error('to')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 col-md-6">
                        <label for="cc" class="form-label">CC (opcional)</label>
                        <input type="text" id="cc" name="cc" value="{{ old('cc') }}" class="form-control @error('cc') is-invalid @enderror" placeholder="email1@empresa.pt, email2@empresa.pt">
                        @error('cc')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <label for="subject" class="form-label">Assunto</label>
                        <input type="text" id="subject" name="subject" value="{{ old('subject', \App\Mail\Admin\CustomerStatementMail::defaultSubject($customer->company, $customer)) }}" class="form-control @error('subject') is-invalid @enderror" required>
                        @error('subject')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <label for="message" class="form-label">Mensagem</label>
                        <textarea id="message" name="message" rows="4" class="form-control @error('message') is-invalid @enderror">{{ old('message') }}</textarea>
                        @error('message')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary">Enviar extrato</button>
                    </div>
                </form>
            </div>
        </div>
    @endcan
@endsection

