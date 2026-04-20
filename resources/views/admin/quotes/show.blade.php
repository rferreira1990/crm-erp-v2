@extends('layouts.admin')

@section('title', 'Ficha do orcamento')
@section('page_title', 'Ficha do orcamento')
@section('page_subtitle', 'Detalhe comercial do orcamento')

@section('page_actions')
    <a href="{{ route('admin.quotes.index') }}" class="btn btn-phoenix-secondary btn-sm">Voltar</a>
    @if ($quote->isEditable() && auth()->user()->can('company.quotes.update'))
        <a href="{{ route('admin.quotes.edit', $quote->id) }}" class="btn btn-primary btn-sm">Editar</a>
    @endif
    @can('create', \App\Models\Quote::class)
        <form method="POST" action="{{ route('admin.quotes.duplicate', $quote->id) }}" class="d-inline">
            @csrf
            <button type="submit" class="btn btn-phoenix-secondary btn-sm">Duplicar</button>
        </form>
    @endcan
    <form method="POST" action="{{ route('admin.quotes.pdf.generate', $quote->id) }}" class="d-inline">
        @csrf
        <button type="submit" class="btn btn-phoenix-secondary btn-sm">Gerar PDF</button>
    </form>
    @if ($quote->pdf_path)
        <a href="{{ route('admin.quotes.pdf.download', $quote->id) }}" class="btn btn-phoenix-secondary btn-sm">Download PDF</a>
    @endif
    @if ($quote->status === \App\Models\Quote::STATUS_DRAFT && auth()->user()->can('company.quotes.delete'))
        <form method="POST" action="{{ route('admin.quotes.destroy', $quote->id) }}" class="d-inline" onsubmit="return confirm('Eliminar este orcamento em rascunho?');">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-phoenix-danger btn-sm">Apagar</button>
        </form>
    @endif
@endsection

@section('breadcrumbs')
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.quotes.index') }}">Orcamentos</a></li>
        <li class="breadcrumb-item active" aria-current="page">{{ $quote->number }}</li>
    </ol>
@endsection

@section('content')
    @php
        $isDraft = $quote->status === \App\Models\Quote::STATUS_DRAFT;
        $customerName = $quote->customer_name ?? ($isDraft ? $quote->customer?->name : null);
        $customerNif = $quote->customer_nif ?? ($isDraft ? $quote->customer?->nif : null);
        $customerEmail = $quote->customer_email ?? ($isDraft ? $quote->customer?->email : null);
        $customerPhone = $quote->customer_phone
            ?? $quote->customer_mobile
            ?? ($isDraft ? ($quote->customer?->phone ?? $quote->customer?->mobile) : null);
        $contactName = $quote->customer_contact_name ?? ($isDraft ? $quote->customerContact?->name : null);
        $contactEmail = $quote->customer_contact_email ?? ($isDraft ? $quote->customerContact?->email : null);
        $contactPhone = $quote->customer_contact_phone ?? ($isDraft ? $quote->customerContact?->phone : null);
        $contactJobTitle = $quote->customer_contact_job_title ?? ($isDraft ? $quote->customerContact?->job_title : null);
    @endphp

    @if ($errors->any())
        <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
    @endif

    <div class="row g-4 mb-4">
        <div class="col-12 col-xxl-8">
            <div class="card mb-4">
                <div class="card-header bg-body-tertiary d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">{{ $quote->number }}</h5>
                    <span class="badge badge-phoenix {{ $quote->statusBadgeClass() }}">
                        {{ $statusLabels[$quote->status] ?? $quote->status }}
                    </span>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <div class="text-body-tertiary fs-9">Cliente</div>
                            <div class="fw-semibold">{{ $customerName ?? '-' }}</div>
                            <div>NIF: {{ $customerNif ?? '-' }}</div>
                            <div>Email: {{ $customerEmail ?? '-' }}</div>
                            <div>Telefone: {{ $customerPhone ?? '-' }}</div>
                        </div>
                        <div class="col-12 col-md-6">
                            <div class="text-body-tertiary fs-9">Contacto</div>
                            <div class="fw-semibold">{{ $contactName ?? '-' }}</div>
                            <div>{{ $contactEmail ?? '-' }}</div>
                            <div>{{ $contactPhone ?? '-' }}</div>
                            <div>{{ $contactJobTitle ?? '-' }}</div>
                        </div>
                        <div class="col-12 col-md-4">
                            <div class="text-body-tertiary fs-9">Data emissao</div>
                            <div class="fw-semibold">{{ optional($quote->issue_date)->format('Y-m-d') }}</div>
                        </div>
                        <div class="col-12 col-md-4">
                            <div class="text-body-tertiary fs-9">Validade</div>
                            <div class="fw-semibold">{{ optional($quote->valid_until)->format('Y-m-d') ?? '-' }}</div>
                        </div>
                        <div class="col-12 col-md-4">
                            <div class="text-body-tertiary fs-9">Responsavel</div>
                            <div class="fw-semibold">{{ $quote->assignedUser?->name ?? '-' }}</div>
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
                                    <th>Tipo</th>
                                    <th>Descricao</th>
                                    <th>Qtd</th>
                                    <th>Unid.</th>
                                    <th>P. unit.</th>
                                    <th>Desc.</th>
                                    <th>IVA</th>
                                    <th>Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($quote->items as $item)
                                    @if ($item->line_type === \App\Models\QuoteItem::TYPE_SECTION)
                                        <tr class="table-light">
                                            <td class="ps-3 fw-bold">{{ $item->sort_order }}</td>
                                            <td class="fw-bold">{{ \App\Models\QuoteItem::lineTypeLabels()[$item->line_type] ?? $item->line_type }}</td>
                                            <td class="fw-bold" colspan="7">{{ $item->description }}</td>
                                        </tr>
                                    @elseif ($item->line_type === \App\Models\QuoteItem::TYPE_NOTE)
                                        <tr>
                                            <td class="ps-3">{{ $item->sort_order }}</td>
                                            <td>{{ \App\Models\QuoteItem::lineTypeLabels()[$item->line_type] ?? $item->line_type }}</td>
                                            <td class="fst-italic" colspan="7">{{ $item->description }}</td>
                                        </tr>
                                    @else
                                        @php
                                            $articleCode = $item->article_code ?? ($isDraft ? $item->article?->code : null);
                                            $unitCode = $item->unit_code ?? ($isDraft ? $item->unit?->code : null);
                                            $vatRateName = $item->vat_rate_name ?? ($isDraft ? $item->vatRate?->name : null);
                                            $vatRatePercentage = $item->vat_rate_percentage ?? ($isDraft ? $item->vatRate?->rate : null);
                                            $exemptionCode = $item->vat_exemption_reason_code ?? ($isDraft ? $item->vatExemptionReason?->code : null);
                                        @endphp
                                        <tr>
                                            <td class="ps-3">{{ $item->sort_order }}</td>
                                            <td>{{ \App\Models\QuoteItem::lineTypeLabels()[$item->line_type] ?? $item->line_type }}</td>
                                            <td>
                                                <div class="fw-semibold">{{ $item->description }}</div>
                                                @if ($articleCode)
                                                    <div class="text-body-tertiary fs-10">{{ $articleCode }}</div>
                                                @endif
                                            </td>
                                            <td>{{ number_format((float) $item->quantity, 3, ',', '.') }}</td>
                                            <td>{{ $unitCode ?? '-' }}</td>
                                            <td>{{ number_format((float) $item->unit_price, 4, ',', '.') }}</td>
                                            <td>{{ number_format((float) ($item->discount_percent ?? 0), 2, ',', '.') }}%</td>
                                            <td>
                                                @if ($vatRateName)
                                                    {{ $vatRateName }} ({{ number_format((float) ($vatRatePercentage ?? 0), 2, ',', '.') }}%)
                                                    @if ($exemptionCode)
                                                        <div class="text-body-tertiary fs-10">{{ $exemptionCode }}</div>
                                                    @endif
                                                @else
                                                    -
                                                @endif
                                            </td>
                                            <td class="fw-semibold">{{ number_format((float) $item->total, 2, ',', '.') }} {{ $quote->currency }}</td>
                                        </tr>
                                    @endif
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

            @include('admin.quotes._totals', [
                'subtotal' => $quote->subtotal,
                'discountTotal' => $quote->discount_total,
                'taxTotal' => $quote->tax_total,
                'grandTotal' => $quote->grand_total,
                'currency' => $quote->currency,
            ])
        </div>

        <div class="col-12 col-xxl-4">
            <div class="card mb-4">
                <div class="card-header bg-body-tertiary">
                    <h5 class="mb-0">Acoes comerciais</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2 mb-3">
                        <form method="POST" action="{{ route('admin.quotes.status.change', $quote->id) }}">
                            @csrf
                            <input type="hidden" name="status" value="sent">
                            <button type="submit" class="btn btn-phoenix-secondary w-100" @disabled(! $quote->canTransitionTo('sent'))>Marcar como enviado</button>
                        </form>
                        <form method="POST" action="{{ route('admin.quotes.status.change', $quote->id) }}">
                            @csrf
                            <input type="hidden" name="status" value="approved">
                            <button type="submit" class="btn btn-success w-100" @disabled(! $quote->canTransitionTo('approved'))>Marcar aprovado</button>
                        </form>
                        <form method="POST" action="{{ route('admin.quotes.status.change', $quote->id) }}">
                            @csrf
                            <input type="hidden" name="status" value="rejected">
                            <button type="submit" class="btn btn-danger w-100" @disabled(! $quote->canTransitionTo('rejected'))>Marcar rejeitado</button>
                        </form>
                        <form method="POST" action="{{ route('admin.quotes.status.change', $quote->id) }}">
                            @csrf
                            <input type="hidden" name="status" value="cancelled">
                            <button type="submit" class="btn btn-phoenix-danger w-100" @disabled(! $quote->canTransitionTo('cancelled'))>Cancelar</button>
                        </form>
                        <form method="POST" action="{{ route('admin.quotes.status.change', $quote->id) }}">
                            @csrf
                            <input type="hidden" name="status" value="draft">
                            <button type="submit" class="btn btn-phoenix-secondary w-100" @disabled(! $quote->canTransitionTo('draft'))>Voltar a rascunho</button>
                        </form>
                    </div>

                    <hr>

                    <form method="POST" action="{{ route('admin.quotes.email.send', $quote->id) }}" class="row g-2">
                        @csrf
                        <div class="col-12">
                            <label for="to" class="form-label">Enviar para</label>
                            <input type="email" id="to" name="to" value="{{ old('to', $contactEmail ?? $customerEmail ?? '') }}" class="form-control form-control-sm @error('to') is-invalid @enderror" required>
                            @error('to')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <label for="cc" class="form-label">CC (opcional)</label>
                            <input type="text" id="cc" name="cc" value="{{ old('cc') }}" class="form-control form-control-sm @error('cc') is-invalid @enderror" placeholder="email1@empresa.pt, email2@empresa.pt">
                            @error('cc')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <label for="subject" class="form-label">Assunto</label>
                            <input type="text" id="subject" name="subject" value="{{ old('subject', \App\Mail\Admin\QuoteSentMail::defaultSubjectForQuote($quote)) }}" class="form-control form-control-sm @error('subject') is-invalid @enderror" required>
                            @error('subject')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <label for="message" class="form-label">Mensagem</label>
                            <textarea id="message" name="message" rows="4" class="form-control form-control-sm @error('message') is-invalid @enderror" placeholder="Mensagem opcional para incluir no email.">{{ old('message') }}</textarea>
                            @error('message')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary w-100">Enviar por email</button>
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
                        <div class="text-body-tertiary fs-9">Mensagem cliente</div>
                        <div>{{ $quote->customer_message ?: '-' }}</div>
                    </div>
                    <div class="mb-3">
                        <div class="text-body-tertiary fs-9">Notas internas</div>
                        <div>{{ $quote->internal_notes ?: '-' }}</div>
                    </div>
                    <div class="mb-0">
                        <div class="text-body-tertiary fs-9">Comentarios impressao</div>
                        <div>{{ $quote->print_comments ?: '-' }}</div>
                    </div>
                </div>
            </div>

            @include('admin.quotes._timeline', ['quote' => $quote, 'statusLabels' => $statusLabels])
        </div>
    </div>
@endsection
