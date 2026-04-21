@extends('layouts.admin')

@section('title', 'Comparador de propostas')
@section('page_title', 'Comparador de propostas')
@section('page_subtitle', $rfq->number)

@section('page_actions')
    <a href="{{ route('admin.rfqs.show', $rfq->id) }}" class="btn btn-phoenix-secondary btn-sm">Voltar a ficha</a>
@endsection

@section('breadcrumbs')
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.rfqs.index') }}">Pedidos de cotacao</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.rfqs.show', $rfq->id) }}">{{ $rfq->number }}</a></li>
        <li class="breadcrumb-item active" aria-current="page">Comparador</li>
    </ol>
@endsection

@section('content')
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
    @endif

    <div class="card mb-4">
        <div class="card-header bg-body-tertiary d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Resumo por fornecedor</h5>
            <span class="badge badge-phoenix {{ $rfq->statusBadgeClass() }}">{{ $statusLabels[$rfq->status] ?? $rfq->status }}</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm fs-9 mb-0">
                    <thead class="bg-body-tertiary">
                        <tr>
                            <th class="ps-3">Fornecedor</th>
                            <th>Estado</th>
                            <th>Subtotal</th>
                            <th>Descontos</th>
                            <th>Portes</th>
                            <th>IVA</th>
                            <th>Total</th>
                            <th>Prazo</th>
                            <th>Validade</th>
                            <th>Itens respondidos</th>
                            <th>Indisponiveis</th>
                            <th class="pe-3">Alternativos</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($comparison['suppliers'] as $supplierSummary)
                            @php
                                $invite = $supplierSummary['invite'];
                                $quote = $supplierSummary['quote'];
                                $isCheapestTotal = (int) ($comparison['cheapest_total_invite_id'] ?? 0) === (int) $invite->id;
                            @endphp
                            <tr>
                                <td class="ps-3">
                                    <div class="fw-semibold">{{ $invite->supplier_name }}</div>
                                    @if ($isCheapestTotal)
                                        <span class="badge badge-phoenix badge-phoenix-success">Mais barato total</span>
                                    @endif
                                    @if ($supplierSummary['is_complete'])
                                        <span class="badge badge-phoenix badge-phoenix-success">Completa</span>
                                    @else
                                        <span class="badge badge-phoenix badge-phoenix-warning">Incompleta</span>
                                    @endif
                                    @if ($supplierSummary['has_comparability_warning'])
                                        <span class="badge badge-phoenix badge-phoenix-warning">Com alternativas</span>
                                    @endif
                                </td>
                                <td>{{ $invite->status }}</td>
                                <td>{{ $quote ? number_format((float) $quote->subtotal, 2, ',', '.').' EUR' : '-' }}</td>
                                <td>{{ $quote ? number_format((float) $quote->discount_total, 2, ',', '.').' EUR' : '-' }}</td>
                                <td>{{ $quote ? number_format((float) $quote->shipping_cost, 2, ',', '.').' EUR' : '-' }}</td>
                                <td>{{ $quote ? number_format((float) $quote->tax_total, 2, ',', '.').' EUR' : '-' }}</td>
                                <td class="fw-semibold">{{ $quote ? number_format((float) $quote->grand_total, 2, ',', '.').' EUR' : '-' }}</td>
                                <td>{{ $quote && $quote->delivery_days !== null ? $quote->delivery_days.' dias' : '-' }}</td>
                                <td>{{ optional($quote?->valid_until)->format('Y-m-d') ?? '-' }}</td>
                                <td>{{ $supplierSummary['responded_count'] }}/{{ $supplierSummary['eligible_items_count'] }}</td>
                                <td>{{ $supplierSummary['unavailable_count'] }}</td>
                                <td class="pe-3">{{ $supplierSummary['alternative_count'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="12" class="text-center py-4 text-body-tertiary">Sem fornecedores para comparar.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header bg-body-tertiary">
            <h5 class="mb-0">Comparacao por item</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm fs-9 mb-0">
                    <thead class="bg-body-tertiary">
                        <tr>
                            <th class="ps-3">Linha</th>
                            @foreach ($comparison['suppliers'] as $supplierSummary)
                                <th>{{ $supplierSummary['invite']->supplier_name }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($comparison['item_matrix'] as $row)
                            @php
                                $rfqItem = $row['rfq_item'];
                                $exactOffers = collect($row['cells_by_invite_id'])
                                    ->filter(function (array $cell): bool {
                                        return ($cell['status'] ?? null) === 'available_exact'
                                            && isset($cell['quote_item'])
                                            && $cell['quote_item']?->line_total !== null;
                                    })
                                    ->sortBy(fn (array $cell): float => (float) $cell['quote_item']->line_total);
                                $bestExactInviteId = $exactOffers->keys()->map(fn ($key): int => (int) $key)->first();
                                $worstExactInviteId = $exactOffers->keys()->map(fn ($key): int => (int) $key)->last();
                                $hasMultipleExactOffers = $exactOffers->count() > 1;
                            @endphp
                            <tr>
                                <td class="ps-3 align-top">
                                    <div class="fw-semibold">{{ $rfqItem->description }}</div>
                                    <div class="text-body-tertiary fs-10">{{ $rfqItem->article_code ?: '-' }} | {{ number_format((float) $rfqItem->quantity, 3, ',', '.') }} {{ $rfqItem->unit_name ?: '' }}</div>
                                </td>
                                @foreach ($comparison['suppliers'] as $supplierSummary)
                                    @php
                                        $inviteId = (int) $supplierSummary['invite']->id;
                                        $cell = $row['cells_by_invite_id'][$inviteId] ?? null;
                                        $status = $cell['status'] ?? 'not_applicable';
                                        $quoteItem = $cell['quote_item'] ?? null;
                                        $cellClass = '';
                                        if ($status === 'available_exact') {
                                            if ($bestExactInviteId !== null && $inviteId === (int) $bestExactInviteId) {
                                                $cellClass = 'table-success';
                                            } elseif ($hasMultipleExactOffers && $worstExactInviteId !== null && $inviteId === (int) $worstExactInviteId) {
                                                $cellClass = 'table-danger';
                                            } else {
                                                $cellClass = 'table-warning';
                                            }
                                        } elseif ($status === 'available_alternative') {
                                            $cellClass = 'table-warning';
                                        } elseif ($status === 'unavailable') {
                                            $cellClass = 'table-danger';
                                        } elseif ($status === 'no_response') {
                                            $cellClass = 'table-secondary';
                                        }
                                    @endphp
                                    <td class="align-top {{ $cellClass }}">
                                        @if (! $row['is_comparable'])
                                            <span class="text-body-tertiary">Linha informativa</span>
                                        @elseif ($status === 'no_response')
                                            <span class="text-body-tertiary">Sem resposta</span>
                                        @elseif ($status === 'unavailable')
                                            <span class="text-danger">Indisponivel</span>
                                        @elseif ($quoteItem)
                                            <div>{{ number_format((float) $quoteItem->unit_price, 4, ',', '.') }} EUR</div>
                                            <div class="fw-semibold">{{ number_format((float) $quoteItem->line_total, 2, ',', '.') }} EUR</div>
                                            @if ($status === 'available_alternative')
                                                <div class="text-warning">Alternativo</div>
                                            @endif
                                            @if (! empty($cell['is_best_exact']))
                                                <span class="badge badge-phoenix badge-phoenix-success">Melhor preco exato</span>
                                            @endif
                                            @if ($quoteItem->notes)
                                                <div class="text-body-tertiary fs-10">{{ \Illuminate\Support\Str::limit($quoteItem->notes, 60) }}</div>
                                            @endif
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ 1 + $comparison['suppliers']->count() }}" class="text-center py-4 text-body-tertiary">
                                    Sem linhas para comparacao.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @if (auth()->user()->can('company.rfq.award'))
        <div class="card mb-4">
            <div class="card-header bg-body-tertiary">
                <h5 class="mb-0">Acoes de adjudicacao</h5>
            </div>
            <div class="card-body">
                @if ($rfq->status === \App\Models\SupplierQuoteRequest::STATUS_AWARDED)
                    <div class="alert alert-warning mb-0">Este pedido ja foi adjudicado. Para manter historico auditavel, nao e permitida nova adjudicacao.</div>
                @else
                    <div class="row g-4">
                        <div class="col-12 col-xl-6">
                            <div class="border rounded p-3 h-100">
                                <h6 class="mb-3">Adjudicacao automatica</h6>

                                <form method="POST" action="{{ route('admin.rfqs.awards.store', $rfq->id) }}" class="mb-3">
                                    @csrf
                                    <input type="hidden" name="mode" value="cheapest_total">
                                    <button type="submit" class="btn btn-success w-100" @disabled(! $comparison['cheapest_total_invite_id'])>
                                        Adjudicar ao mais barato total
                                    </button>
                                    @if (! $comparison['cheapest_total_invite_id'])
                                        <div class="text-danger fs-10 mt-2">Sem proposta completa valida para adjudicacao global automatica.</div>
                                    @endif
                                </form>

                                <form method="POST" action="{{ route('admin.rfqs.awards.store', $rfq->id) }}">
                                    @csrf
                                    <input type="hidden" name="mode" value="cheapest_item">
                                    <button type="submit" class="btn btn-primary w-100" @disabled($comparison['unresolved_item_ids'] !== [])>
                                        Adjudicar por item ao mais barato
                                    </button>
                                    @if ($comparison['unresolved_item_ids'] !== [])
                                        <div class="text-danger fs-10 mt-2">
                                            Existem linhas sem proposta exata disponivel (indisponivel, sem resposta ou apenas alternativa).
                                        </div>
                                    @endif
                                </form>
                            </div>
                        </div>

                        <div class="col-12 col-xl-6">
                            <div class="border rounded p-3 h-100">
                                <h6 class="mb-3">Adjudicacao manual global</h6>
                                <form method="POST" action="{{ route('admin.rfqs.awards.store', $rfq->id) }}" class="row g-3">
                                    @csrf
                                    <input type="hidden" name="mode" value="manual_total">
                                    <div class="col-12">
                                        <label class="form-label">Fornecedor</label>
                                        <select name="awarded_supplier_id" class="form-select form-select-sm">
                                            <option value="">Selecionar fornecedor</option>
                                            @foreach ($comparison['suppliers'] as $supplierSummary)
                                                @if ($supplierSummary['quote'])
                                                    <option value="{{ $supplierSummary['invite']->supplier_id }}" @selected(old('awarded_supplier_id') == $supplierSummary['invite']->supplier_id)>
                                                        {{ $supplierSummary['invite']->supplier_name }} ({{ number_format((float) $supplierSummary['quote']->grand_total, 2, ',', '.') }} EUR)
                                                    </option>
                                                @endif
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Motivo (obrigatorio se nao for o mais barato)</label>
                                        <select name="award_reason" class="form-select form-select-sm">
                                            <option value="">Sem motivo</option>
                                            @foreach ($awardReasonOptions as $option)
                                                <option value="{{ $option }}" @selected(old('award_reason') === $option)>{{ $option }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Notas</label>
                                        <textarea name="award_notes" rows="2" class="form-control form-control-sm">{{ old('award_notes') }}</textarea>
                                    </div>
                                    <div class="col-12">
                                        <button type="submit" class="btn btn-warning w-100">Confirmar manual global</button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="border rounded p-3">
                                <h6 class="mb-3">Adjudicacao manual por item</h6>
                                <form method="POST" action="{{ route('admin.rfqs.awards.store', $rfq->id) }}" class="row g-3">
                                    @csrf
                                    <input type="hidden" name="mode" value="manual_item">
                                    @foreach ($comparison['item_matrix'] as $row)
                                        @php
                                            $rfqItem = $row['rfq_item'];
                                        @endphp
                                        @if (! $row['is_comparable'])
                                            @continue
                                        @endif
                                        <div class="col-12 col-xl-6">
                                            <label class="form-label">{{ $rfqItem->description }}</label>
                                            <select name="item_supplier_ids[{{ $rfqItem->id }}]" class="form-select form-select-sm">
                                                <option value="">Selecionar fornecedor</option>
                                                @foreach ($comparison['suppliers'] as $supplierSummary)
                                                    @php
                                                        $invite = $supplierSummary['invite'];
                                                        $cell = $row['cells_by_invite_id'][(int) $invite->id] ?? null;
                                                        $quoteItem = $cell['quote_item'] ?? null;
                                                        $status = $cell['status'] ?? 'no_response';
                                                    @endphp
                                                    @if (! in_array($status, ['available_exact', 'available_alternative'], true) || ! $quoteItem)
                                                        @continue
                                                    @endif
                                                    <option value="{{ $invite->supplier_id }}" @selected(old('item_supplier_ids.'.$rfqItem->id) == $invite->supplier_id)>
                                                        {{ $invite->supplier_name }} - {{ number_format((float) $quoteItem->line_total, 2, ',', '.') }} EUR{{ $quoteItem->is_alternative ? ' (Alternativo)' : '' }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    @endforeach

                                    <div class="col-12 col-xl-4">
                                        <label class="form-label">Motivo (obrigatorio se nao for o mais barato)</label>
                                        <select name="award_reason" class="form-select form-select-sm">
                                            <option value="">Sem motivo</option>
                                            @foreach ($awardReasonOptions as $option)
                                                <option value="{{ $option }}" @selected(old('award_reason') === $option)>{{ $option }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-12 col-xl-8">
                                        <label class="form-label">Notas</label>
                                        <textarea name="award_notes" rows="2" class="form-control form-control-sm">{{ old('award_notes') }}</textarea>
                                    </div>
                                    <div class="col-12">
                                        <button type="submit" class="btn btn-warning w-100">Confirmar manual por item</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    @endif
@endsection
