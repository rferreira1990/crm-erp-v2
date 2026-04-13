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
@endsection

@section('breadcrumbs')
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.quotes.index') }}">Orcamentos</a></li>
        <li class="breadcrumb-item active" aria-current="page">{{ $quote->number }}</li>
    </ol>
@endsection

@section('content')
    @if (session('status'))
        <div class="alert alert-success" role="alert">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
    @endif

    <div class="row g-4 mb-4">
        <div class="col-12 col-xxl-8">
            <div class="card mb-4">
                <div class="card-header bg-body-tertiary d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">{{ $quote->number }}</h5>
                    <span class="badge badge-phoenix {{ in_array($quote->status, ['approved'], true) ? 'badge-phoenix-success' : (in_array($quote->status, ['rejected', 'cancelled', 'expired'], true) ? 'badge-phoenix-danger' : 'badge-phoenix-secondary') }}">
                        {{ $statusLabels[$quote->status] ?? $quote->status }}
                    </span>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <div class="text-body-tertiary fs-9">Cliente</div>
                            <div class="fw-semibold">{{ $quote->customer?->name ?? '-' }}</div>
                            <div>NIF: {{ $quote->customer?->nif ?? '-' }}</div>
                            <div>Email: {{ $quote->customer?->email ?? '-' }}</div>
                            <div>Telefone: {{ $quote->customer?->phone ?? $quote->customer?->mobile ?? '-' }}</div>
                        </div>
                        <div class="col-12 col-md-6">
                            <div class="text-body-tertiary fs-9">Contacto</div>
                            <div class="fw-semibold">{{ $quote->customerContact?->name ?? '-' }}</div>
                            <div>{{ $quote->customerContact?->email ?? '-' }}</div>
                            <div>{{ $quote->customerContact?->phone ?? '-' }}</div>
                            <div>{{ $quote->customerContact?->job_title ?? '-' }}</div>
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
                                    <tr>
                                        <td class="ps-3">{{ $item->sort_order }}</td>
                                        <td>{{ \App\Models\QuoteItem::lineTypeLabels()[$item->line_type] ?? $item->line_type }}</td>
                                        <td>
                                            <div class="fw-semibold">{{ $item->description }}</div>
                                            @if ($item->article)
                                                <div class="text-body-tertiary fs-10">{{ $item->article->code }}</div>
                                            @endif
                                        </td>
                                        <td>{{ number_format((float) $item->quantity, 3, ',', '.') }}</td>
                                        <td>{{ $item->unit?->code ?? '-' }}</td>
                                        <td>{{ number_format((float) $item->unit_price, 4, ',', '.') }}</td>
                                        <td>{{ number_format((float) ($item->discount_percent ?? 0), 2, ',', '.') }}%</td>
                                        <td>
                                            @if ($item->vatRate)
                                                {{ $item->vatRate->name }} ({{ number_format((float) $item->vatRate->rate, 2, ',', '.') }}%)
                                                @if ($item->vatExemptionReason)
                                                    <div class="text-body-tertiary fs-10">{{ $item->vatExemptionReason->code }}</div>
                                                @endif
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td class="fw-semibold">{{ number_format((float) $item->total, 2, ',', '.') }} {{ $quote->currency }}</td>
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
                            <input type="email" id="to" name="to" value="{{ old('to', $quote->customerContact?->email ?? $quote->customer?->email ?? '') }}" class="form-control form-control-sm @error('to') is-invalid @enderror" required>
                            @error('to')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <label for="subject" class="form-label">Assunto</label>
                            <input type="text" id="subject" name="subject" value="{{ old('subject', 'Orcamento '.$quote->number) }}" class="form-control form-control-sm @error('subject') is-invalid @enderror" required>
                            @error('subject')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <label for="message" class="form-label">Mensagem</label>
                            <textarea id="message" name="message" rows="4" class="form-control form-control-sm @error('message') is-invalid @enderror">{{ old('message', 'Segue em anexo o nosso orcamento.') }}</textarea>
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

