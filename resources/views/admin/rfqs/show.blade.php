@extends('layouts.admin')

@section('title', 'Ficha do pedido de cotacao')
@section('page_title', 'Ficha do pedido de cotacao')
@section('page_subtitle', $rfq->number)

@section('page_actions')
    <a href="{{ route('admin.rfqs.index') }}" class="btn btn-phoenix-secondary btn-sm">Voltar</a>
    @if ($rfq->isEditable() && auth()->user()->can('company.rfq.update'))
        <a href="{{ route('admin.rfqs.edit', $rfq->id) }}" class="btn btn-primary btn-sm">Editar</a>
    @endif
    <form method="POST" action="{{ route('admin.rfqs.pdf.generate', $rfq->id) }}" class="d-inline">
        @csrf
        <button type="submit" class="btn btn-phoenix-secondary btn-sm">Gerar PDF</button>
    </form>
    @if ($rfq->pdf_path)
        <a href="{{ route('admin.rfqs.pdf.download', $rfq->id) }}" class="btn btn-phoenix-secondary btn-sm">Download PDF</a>
    @endif
    @if ($rfq->status === \App\Models\SupplierQuoteRequest::STATUS_DRAFT && auth()->user()->can('company.rfq.delete'))
        <form method="POST" action="{{ route('admin.rfqs.destroy', $rfq->id) }}" class="d-inline" onsubmit="return confirm('Eliminar este pedido em rascunho?');">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-phoenix-danger btn-sm">Apagar</button>
        </form>
    @endif
@endsection

@section('breadcrumbs')
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.rfqs.index') }}">Pedidos de cotacao</a></li>
        <li class="breadcrumb-item active" aria-current="page">{{ $rfq->number }}</li>
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
                    <h5 class="mb-0">{{ $rfq->number }}</h5>
                    <span class="badge badge-phoenix {{ $rfq->statusBadgeClass() }}">{{ $statusLabels[$rfq->status] ?? $rfq->status }}</span>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12 col-md-4">
                            <div class="text-body-tertiary fs-9">Titulo</div>
                            <div class="fw-semibold">{{ $rfq->title ?: '-' }}</div>
                        </div>
                        <div class="col-12 col-md-4">
                            <div class="text-body-tertiary fs-9">Data emissao</div>
                            <div class="fw-semibold">{{ optional($rfq->issue_date)->format('Y-m-d') }}</div>
                        </div>
                        <div class="col-12 col-md-4">
                            <div class="text-body-tertiary fs-9">Prazo resposta</div>
                            <div class="fw-semibold">{{ optional($rfq->response_deadline)->format('Y-m-d') ?? '-' }}</div>
                        </div>
                        <div class="col-12 col-md-4">
                            <div class="text-body-tertiary fs-9">Responsavel</div>
                            <div class="fw-semibold">{{ $rfq->assignedUser?->name ?? '-' }}</div>
                        </div>
                        <div class="col-12 col-md-4">
                            <div class="text-body-tertiary fs-9">Criado por</div>
                            <div class="fw-semibold">{{ $rfq->creator?->name ?? '-' }}</div>
                        </div>
                        <div class="col-12 col-md-4">
                            <div class="text-body-tertiary fs-9">Total estimado</div>
                            <div class="fw-semibold">{{ $rfq->estimated_total !== null ? number_format((float) $rfq->estimated_total, 2, ',', '.').' EUR' : '-' }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header bg-body-tertiary">
                    <h5 class="mb-0">Linhas do pedido</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm fs-9 mb-0">
                            <thead class="bg-body-tertiary">
                                <tr>
                                    <th class="ps-3">#</th>
                                    <th>Tipo</th>
                                    <th>Codigo</th>
                                    <th>Descricao</th>
                                    <th>Unidade</th>
                                    <th>Qtd.</th>
                                    <th class="pe-3">Notas internas</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($rfq->items as $item)
                                    <tr>
                                        <td class="ps-3">{{ $item->line_order }}</td>
                                        <td>{{ \App\Models\SupplierQuoteRequestItem::lineTypeLabels()[$item->line_type] ?? $item->line_type }}</td>
                                        <td>{{ $item->article_code ?: '-' }}</td>
                                        <td>{{ $item->description }}</td>
                                        <td>{{ $item->unit_name ?: '-' }}</td>
                                        <td>{{ number_format((float) $item->quantity, 3, ',', '.') }}</td>
                                        <td class="pe-3">{{ $item->internal_notes ?: '-' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center py-4 text-body-tertiary">Sem linhas.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header bg-body-tertiary">
                    <h5 class="mb-0">Fornecedores e respostas</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm fs-9 mb-0">
                            <thead class="bg-body-tertiary">
                                <tr>
                                    <th class="ps-3">Fornecedor</th>
                                    <th>Email fornecedor</th>
                                    <th>Estado</th>
                                    <th>Enviado em</th>
                                    <th>Respondido em</th>
                                    <th>Total resposta</th>
                                    <th class="text-end pe-3">Acoes</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($rfq->invitedSuppliers as $invite)
                                    <tr>
                                        <td class="ps-3 fw-semibold">{{ $invite->supplier_name }}</td>
                                        <td>{{ $invite->supplier_email ?: '-' }}</td>
                                        <td>{{ $supplierInviteStatusLabels[$invite->status] ?? $invite->status }}</td>
                                        <td>{{ optional($invite->sent_at)->format('Y-m-d H:i') ?? '-' }}</td>
                                        <td>
                                            @if ($invite->supplierQuote?->supplier_document_date)
                                                {{ optional($invite->supplierQuote->supplier_document_date)->format('Y-m-d') }}
                                            @else
                                                {{ optional($invite->responded_at)->format('Y-m-d H:i') ?? '-' }}
                                            @endif
                                        </td>
                                        <td>
                                            @if ($invite->supplierQuote)
                                                <div>{{ number_format((float) $invite->supplierQuote->grand_total, 2, ',', '.') }} EUR</div>
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td class="text-end pe-3">
                                            <div class="d-inline-flex gap-2">
                                                <a href="{{ route('admin.rfqs.responses.create', [$rfq->id, $invite->id]) }}" class="btn btn-phoenix-secondary btn-sm">
                                                    {{ $invite->supplierQuote ? 'Editar resposta' : 'Registar resposta' }}
                                                </a>
                                                @if ($invite->pdf_path)
                                                    <a href="{{ route('admin.rfqs.suppliers.pdf.download', [$rfq->id, $invite->id]) }}" class="btn btn-phoenix-secondary btn-sm">
                                                        PDF fornecedor
                                                    </a>
                                                @endif
                                                @if ($invite->supplierQuote?->supplier_document_pdf_path)
                                                    <a href="{{ route('admin.rfqs.responses.document.download', [$rfq->id, $invite->id]) }}" class="btn btn-phoenix-secondary btn-sm">
                                                        Doc real
                                                    </a>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center py-4 text-body-tertiary">Sem fornecedores associados.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-xxl-4">
            <div class="card mb-4">
                <div class="card-header bg-body-tertiary">
                    <h5 class="mb-0">Enviar pedido por email</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.rfqs.email.send', $rfq->id) }}" class="row g-3">
                        @csrf
                        <div class="col-12">
                            <label class="form-label">Fornecedores destino</label>
                            <div class="border rounded p-2" style="max-height:180px;overflow:auto;">
                                @foreach ($rfq->invitedSuppliers as $invite)
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="supplier_{{ $invite->id }}" name="supplier_ids[]" value="{{ $invite->supplier_id }}" @checked(collect(old('supplier_ids', $rfq->invitedSuppliers->pluck('supplier_id')->all()))->map(fn ($id) => (int) $id)->contains((int) $invite->supplier_id))>
                                        <label class="form-check-label" for="supplier_{{ $invite->id }}">
                                            {{ $invite->supplier_name }}{{ $invite->supplier_email ? ' ('.$invite->supplier_email.')' : '' }}
                                        </label>
                                    </div>
                                @endforeach
                            </div>
                            @error('supplier_ids')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <label for="cc" class="form-label">CC (opcional)</label>
                            <input type="text" id="cc" name="cc" value="{{ old('cc') }}" class="form-control form-control-sm @error('cc') is-invalid @enderror" placeholder="email1@empresa.pt, email2@empresa.pt">
                            @error('cc')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <label for="subject" class="form-label">Assunto</label>
                            <input type="text" id="subject" name="subject" value="{{ old('subject', \App\Mail\Admin\SupplierQuoteRequestSentMail::defaultSubjectForRfq($rfq)) }}" class="form-control form-control-sm @error('subject') is-invalid @enderror" required>
                            @error('subject')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <label for="message" class="form-label">Mensagem</label>
                            <textarea id="message" name="message" rows="4" class="form-control form-control-sm @error('message') is-invalid @enderror" placeholder="Mensagem opcional para fornecedores">{{ old('message') }}</textarea>
                            @error('message')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary w-100">Enviar pedido</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header bg-body-tertiary">
                    <h5 class="mb-0">Notas</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <div class="text-body-tertiary fs-9">Notas para fornecedor</div>
                        <div>{{ $rfq->supplier_notes ?: '-' }}</div>
                    </div>
                    <div class="mb-0">
                        <div class="text-body-tertiary fs-9">Notas internas</div>
                        <div>{{ $rfq->internal_notes ?: '-' }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
