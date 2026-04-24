@extends('layouts.admin')

@section('title', 'Ficha do Documento de Venda')
@section('page_title', 'Ficha do Documento de Venda')
@section('page_subtitle', $document->number)

@section('page_actions')
    <a href="{{ route('admin.sales-documents.index') }}" class="btn btn-phoenix-secondary btn-sm">Voltar</a>
    @can('company.sales_documents.update')
        @if ($document->isEditableDraft())
            <a href="{{ route('admin.sales-documents.edit', $document->id) }}" class="btn btn-phoenix-secondary btn-sm">Editar</a>
        @endif
    @endcan
    @can('company.sales_documents.issue')
        @if ($document->isDraft())
            <form method="POST" action="{{ route('admin.sales-documents.issue', $document->id) }}" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-primary btn-sm">Emitir</button>
            </form>
        @endif
    @endcan
    @can('company.sales_documents.cancel')
        @if ($document->isDraft())
            <form method="POST" action="{{ route('admin.sales-documents.cancel', $document->id) }}" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-phoenix-danger btn-sm">Cancelar</button>
            </form>
        @endif
    @endcan
    <form method="POST" action="{{ route('admin.sales-documents.pdf.generate', $document->id) }}" class="d-inline">
        @csrf
        <button type="submit" class="btn btn-phoenix-secondary btn-sm">Gerar PDF</button>
    </form>
    @if ($document->pdf_path)
        <a href="{{ route('admin.sales-documents.pdf.download', $document->id) }}" class="btn btn-phoenix-secondary btn-sm">Download PDF</a>
    @endif
@endsection

@section('breadcrumbs')
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.sales-documents.index') }}">Documentos de Venda</a></li>
        <li class="breadcrumb-item active" aria-current="page">{{ $document->number }}</li>
    </ol>
@endsection

@section('content')
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
    @endif

    <div class="row g-4 mb-4">
        <div class="col-12 col-xxl-8">
            <div class="card mb-4">
                <div class="card-header bg-body-tertiary d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">{{ $document->number }}</h5>
                    <span class="badge badge-phoenix {{ $document->statusBadgeClass() }}">{{ $statusLabels[$document->status] ?? $document->status }}</span>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12 col-md-4">
                            <div class="text-body-tertiary fs-9">Origem</div>
                            <div class="fw-semibold">{{ $sourceLabels[$document->source_type] ?? $document->source_type }}</div>
                        </div>
                        <div class="col-12 col-md-4">
                            <div class="text-body-tertiary fs-9">Data</div>
                            <div class="fw-semibold">{{ optional($document->issue_date)->format('Y-m-d') ?? '-' }}</div>
                        </div>
                        <div class="col-12 col-md-4">
                            <div class="text-body-tertiary fs-9">Vencimento</div>
                            <div class="fw-semibold">{{ optional($document->due_date)->format('Y-m-d') ?? '-' }}</div>
                        </div>
                        <div class="col-12 col-md-8">
                            <div class="text-body-tertiary fs-9">Cliente</div>
                            <div class="fw-semibold">{{ $document->customer_name_snapshot ?: ($document->customer?->name ?? '-') }}</div>
                        </div>
                        <div class="col-12 col-md-4">
                            <div class="text-body-tertiary fs-9">NIF</div>
                            <div class="fw-semibold">{{ $document->customer_nif_snapshot ?: '-' }}</div>
                        </div>
                        <div class="col-12 col-md-6">
                            <div class="text-body-tertiary fs-9">Email cliente</div>
                            <div class="fw-semibold">{{ $document->customer_email_snapshot ?: '-' }}</div>
                        </div>
                        <div class="col-12 col-md-6">
                            <div class="text-body-tertiary fs-9">Telefone cliente</div>
                            <div class="fw-semibold">{{ $document->customer_phone_snapshot ?: '-' }}</div>
                        </div>
                        <div class="col-12">
                            <div class="text-body-tertiary fs-9">Morada</div>
                            <div class="fw-semibold">{{ $document->customer_address_snapshot ?: '-' }}</div>
                        </div>
                        <div class="col-12">
                            <div class="text-body-tertiary fs-9">Regra de stock</div>
                            <div class="fw-semibold">
                                @if ($document->shouldMoveStock())
                                    <span class="badge badge-phoenix badge-phoenix-warning">Movimenta stock</span>
                                @else
                                    <span class="badge badge-phoenix badge-phoenix-secondary">Nao movimenta stock</span>
                                @endif
                                <span class="ms-2">{{ $document->stockRuleReasonLabel() }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header bg-body-tertiary">
                    <h5 class="mb-0">Linhas</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm fs-9 mb-0">
                            <thead class="bg-body-tertiary">
                                <tr>
                                    <th class="ps-3">#</th>
                                    <th>Codigo</th>
                                    <th>Descricao</th>
                                    <th>Unid.</th>
                                    <th>Qtd.</th>
                                    <th>P. Unit.</th>
                                    <th>Desc. %</th>
                                    <th>Taxa %</th>
                                    <th>Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($document->items as $item)
                                    <tr>
                                        <td class="ps-3">{{ $item->line_order }}</td>
                                        <td>{{ $item->article_code ?: '-' }}</td>
                                        <td>{{ $item->description }}</td>
                                        <td>{{ $item->unit_name_snapshot ?: '-' }}</td>
                                        <td>{{ number_format((float) $item->quantity, 3, ',', '.') }}</td>
                                        <td>{{ number_format((float) $item->unit_price, 4, ',', '.') }}</td>
                                        <td>{{ number_format((float) $item->discount_percent, 2, ',', '.') }}</td>
                                        <td>{{ number_format((float) $item->tax_rate, 2, ',', '.') }}</td>
                                        <td>{{ number_format((float) $item->line_total, 2, ',', '.') }} {{ $document->currency }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="text-center py-4 text-body-tertiary">Sem linhas.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header bg-body-tertiary">
                    <h5 class="mb-0">Totais</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12 col-md-3"><span class="text-body-tertiary fs-9">Subtotal:</span> <span class="fw-semibold">{{ number_format((float) $document->subtotal, 2, ',', '.') }} {{ $document->currency }}</span></div>
                        <div class="col-12 col-md-3"><span class="text-body-tertiary fs-9">Desconto:</span> <span class="fw-semibold">{{ number_format((float) $document->discount_total, 2, ',', '.') }} {{ $document->currency }}</span></div>
                        <div class="col-12 col-md-3"><span class="text-body-tertiary fs-9">Impostos:</span> <span class="fw-semibold">{{ number_format((float) $document->tax_total, 2, ',', '.') }} {{ $document->currency }}</span></div>
                        <div class="col-12 col-md-3"><span class="text-body-tertiary fs-9">Total:</span> <span class="fw-bold">{{ number_format((float) $document->grand_total, 2, ',', '.') }} {{ $document->currency }}</span></div>
                    </div>
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
                        <div class="text-body-tertiary fs-9">Orcamento origem</div>
                        <div class="fw-semibold">
                            @if ($document->quote)
                                <a href="{{ route('admin.quotes.show', $document->quote->id) }}">{{ $document->quote->number }}</a>
                            @else
                                -
                            @endif
                        </div>
                    </div>
                    <div class="mb-2">
                        <div class="text-body-tertiary fs-9">Obra origem</div>
                        <div class="fw-semibold">
                            @if ($document->constructionSite)
                                <a href="{{ route('admin.construction-sites.show', $document->constructionSite->id) }}">{{ $document->constructionSite->code }}</a>
                            @else
                                -
                            @endif
                        </div>
                    </div>
                    <div class="mb-2">
                        <div class="text-body-tertiary fs-9">Criado por</div>
                        <div class="fw-semibold">{{ $document->creator?->name ?? '-' }}</div>
                    </div>
                    <div class="mb-0">
                        <div class="text-body-tertiary fs-9">Atualizado por</div>
                        <div class="fw-semibold">{{ $document->updater?->name ?? '-' }}</div>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header bg-body-tertiary">
                    <h5 class="mb-0">Movimentos de stock</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm fs-9 mb-0">
                            <thead class="bg-body-tertiary">
                                <tr>
                                    <th class="ps-3">Data</th>
                                    <th>Artigo</th>
                                    <th>Tipo</th>
                                    <th>Qtd.</th>
                                    <th class="pe-3">Utilizador</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($document->stockMovements as $movement)
                                    <tr>
                                        <td class="ps-3">{{ optional($movement->movement_date)->format('Y-m-d') ?? '-' }}</td>
                                        <td>{{ $movement->article?->code ?? '-' }}</td>
                                        <td>{{ $movementTypeLabels[$movement->type] ?? $movement->type }} / {{ $movementDirectionLabels[$movement->direction] ?? $movement->direction }}</td>
                                        <td>{{ number_format((float) $movement->quantity, 3, ',', '.') }}</td>
                                        <td class="pe-3">{{ $movement->performer?->name ?? '-' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center py-4 text-body-tertiary">Sem movimentos gerados.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header bg-body-tertiary">
                    <h5 class="mb-0">Notas</h5>
                </div>
                <div class="card-body">
                    {{ $document->notes ?: '-' }}
                </div>
            </div>

            @can('company.sales_documents.send')
                <div class="card mb-4">
                    <div class="card-header bg-body-tertiary">
                        <h5 class="mb-0">Enviar por email</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('admin.sales-documents.email.send', $document->id) }}" class="row g-3">
                            @csrf
                            <div class="col-12">
                                <label for="to" class="form-label">Para</label>
                                <input type="email" id="to" name="to" value="{{ old('to', $document->customer_contact_email_snapshot ?: $document->customer_email_snapshot) }}" class="form-control form-control-sm @error('to') is-invalid @enderror" required>
                                @error('to')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12">
                                <label for="cc" class="form-label">CC (opcional)</label>
                                <input type="text" id="cc" name="cc" value="{{ old('cc') }}" class="form-control form-control-sm @error('cc') is-invalid @enderror" placeholder="email1@empresa.pt, email2@empresa.pt">
                                @error('cc')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12">
                                <label for="subject" class="form-label">Assunto</label>
                                <input type="text" id="subject" name="subject" value="{{ old('subject', \App\Mail\Admin\SalesDocumentSentMail::defaultSubjectForDocument($document)) }}" class="form-control form-control-sm @error('subject') is-invalid @enderror" required>
                                @error('subject')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12">
                                <label for="message" class="form-label">Mensagem</label>
                                <textarea id="message" name="message" rows="4" class="form-control form-control-sm @error('message') is-invalid @enderror">{{ old('message') }}</textarea>
                                @error('message')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary w-100">Enviar documento</button>
                            </div>
                        </form>
                    </div>
                </div>
            @endcan
        </div>
    </div>
@endsection
