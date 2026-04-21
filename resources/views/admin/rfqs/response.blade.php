@extends('layouts.admin')

@section('title', $existingQuote ? 'Editar resposta de fornecedor' : 'Registar resposta de fornecedor')
@section('page_title', $existingQuote ? 'Editar resposta de fornecedor' : 'Registar resposta de fornecedor')
@section('page_subtitle', $rfq->number.' - '.$rfqSupplier->supplier_name)

@section('breadcrumbs')
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.rfqs.index') }}">Pedidos de cotacao</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.rfqs.show', $rfq->id) }}">{{ $rfq->number }}</a></li>
        <li class="breadcrumb-item active" aria-current="page">Resposta fornecedor</li>
    </ol>
@endsection

@section('content')
    @php
        $isReadOnly = (bool) ($isReadOnly ?? false);
        $lockedLineTypes = [
            \App\Models\SupplierQuoteRequestItem::TYPE_SECTION,
            \App\Models\SupplierQuoteRequestItem::TYPE_NOTE,
        ];
    @endphp

    @if ($errors->any())
        <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
    @endif

    @if ($isReadOnly)
        <div class="alert alert-warning" role="alert">
            Este pedido ja foi adjudicado. A resposta do fornecedor esta disponivel apenas em modo leitura.
        </div>
    @endif

    <form method="POST" action="{{ route('admin.rfqs.responses.store', [$rfq->id, $rfqSupplier->id]) }}" enctype="multipart/form-data">
        @csrf

        <div class="card mb-4">
            <div class="card-header bg-body-tertiary">
                <h5 class="mb-0">Dados gerais da proposta</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12 col-md-3">
                        <label for="received_at" class="form-label">Data rececao</label>
                        <input type="datetime-local" id="received_at" name="received_at" value="{{ old('received_at', optional($existingQuote?->received_at)->format('Y-m-d\TH:i') ?? now()->format('Y-m-d\TH:i')) }}" class="form-control @error('received_at') is-invalid @enderror" required @disabled($isReadOnly)>
                        @error('received_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 col-md-3">
                        <label for="shipping_cost" class="form-label">Portes (s/IVA)</label>
                        <input type="number" id="shipping_cost" name="shipping_cost" min="0" step="0.01" value="{{ old('shipping_cost', $existingQuote->shipping_cost ?? 0) }}" class="form-control @error('shipping_cost') is-invalid @enderror" @disabled($isReadOnly)>
                        @error('shipping_cost')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 col-md-3">
                        <label for="delivery_days" class="form-label">Prazo entrega (dias)</label>
                        <input type="number" id="delivery_days" name="delivery_days" min="0" step="1" value="{{ old('delivery_days', $existingQuote->delivery_days ?? '') }}" class="form-control @error('delivery_days') is-invalid @enderror" @disabled($isReadOnly)>
                        @error('delivery_days')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 col-md-3">
                        <label for="supplier_document_date" class="form-label">Data proposta</label>
                        <input type="date" id="supplier_document_date" name="supplier_document_date" value="{{ old('supplier_document_date', optional($existingQuote?->supplier_document_date)->format('Y-m-d')) }}" class="form-control @error('supplier_document_date') is-invalid @enderror" @disabled($isReadOnly)>
                        @error('supplier_document_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 col-md-3">
                        <label for="valid_until" class="form-label">Validade proposta</label>
                        <input type="date" id="valid_until" name="valid_until" value="{{ old('valid_until', optional($existingQuote?->valid_until)->format('Y-m-d')) }}" class="form-control @error('valid_until') is-invalid @enderror" @disabled($isReadOnly)>
                        @error('valid_until')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 col-md-6">
                        <label for="supplier_document_number" class="form-label">Numero documento fornecedor</label>
                        <input type="text" id="supplier_document_number" name="supplier_document_number" value="{{ old('supplier_document_number', $existingQuote->supplier_document_number ?? '') }}" class="form-control @error('supplier_document_number') is-invalid @enderror" maxlength="120" @disabled($isReadOnly)>
                        @error('supplier_document_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 col-md-6">
                        <label for="commercial_discount_text" class="form-label">Desconto comercial</label>
                        <input type="text" id="commercial_discount_text" name="commercial_discount_text" value="{{ old('commercial_discount_text', $existingQuote->commercial_discount_text ?? '') }}" class="form-control @error('commercial_discount_text') is-invalid @enderror" maxlength="255" placeholder="Ex.: 3% pp" @disabled($isReadOnly)>
                        @error('commercial_discount_text')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 col-md-6">
                        @php
                            $selectedPaymentTerm = old('payment_terms_text', $existingQuote->payment_terms_text ?? $defaultPaymentTermText);
                            $hasSelectedInOptions = in_array($selectedPaymentTerm, $paymentTermOptions, true);
                        @endphp
                        <label for="payment_terms_text" class="form-label">Condicoes de pagamento</label>
                        <select id="payment_terms_text" name="payment_terms_text" class="form-select @error('payment_terms_text') is-invalid @enderror" @disabled($isReadOnly)>
                            @if (! $hasSelectedInOptions && $selectedPaymentTerm)
                                <option value="{{ $selectedPaymentTerm }}" selected>{{ $selectedPaymentTerm }}</option>
                            @endif
                            @foreach ($paymentTermOptions as $paymentTermOption)
                                <option value="{{ $paymentTermOption }}" @selected($selectedPaymentTerm === $paymentTermOption)>{{ $paymentTermOption }}</option>
                            @endforeach
                        </select>
                        @error('payment_terms_text')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 col-md-6">
                        <label for="supplier_document_pdf" class="form-label">PDF documento real do fornecedor</label>
                        <input type="file" id="supplier_document_pdf" name="supplier_document_pdf" accept="application/pdf" class="form-control @error('supplier_document_pdf') is-invalid @enderror" @disabled($isReadOnly)>
                        @error('supplier_document_pdf')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        @if ($existingQuote?->supplier_document_pdf_path)
                            <div class="mt-2">
                                <a href="{{ route('admin.rfqs.responses.document.download', [$rfq->id, $rfqSupplier->id]) }}" class="btn btn-phoenix-secondary btn-sm">Download documento atual</a>
                            </div>
                        @endif
                    </div>
                    <div class="col-12">
                        <label for="notes" class="form-label">Observacoes</label>
                        <textarea id="notes" name="notes" rows="3" class="form-control @error('notes') is-invalid @enderror" @disabled($isReadOnly)>{{ old('notes', $existingQuote->notes ?? '') }}</textarea>
                        @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header bg-body-tertiary">
                <h5 class="mb-0">Linhas da resposta</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm fs-9 mb-0">
                        <thead class="bg-body-tertiary">
                            <tr>
                                <th class="ps-3">Responder</th>
                                <th>Linha</th>
                                <th>Unidade</th>
                                <th>Qtd. pedido</th>
                                <th>Qtd. proposta</th>
                                <th>P. unit. (s/IVA)</th>
                                <th>Desc %</th>
                                <th>Alternativo</th>
                                <th>Descricao alternativa</th>
                                <th>Marca</th>
                                <th class="pe-3">Notas</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($rfq->items as $index => $rfqItem)
                                @php
                                    $isLocked = in_array($rfqItem->line_type, $lockedLineTypes, true);
                                    $existing = $existingItemsByRfqItem->get($rfqItem->id);
                                    $isResponded = $isLocked ? false : old("items.$index.is_responded", $existing !== null);
                                    $isAlternative = $isLocked ? false : old("items.$index.is_alternative", $existing?->is_alternative ?? false);
                                @endphp
                                <tr class="response-row">
                                    <td class="ps-3">
                                        <input type="hidden" name="items[{{ $index }}][supplier_quote_request_item_id]" value="{{ $rfqItem->id }}">
                                        <input type="hidden" name="items[{{ $index }}][is_responded]" value="0">
                                        <input type="hidden" class="is-available-input" name="items[{{ $index }}][is_available]" value="{{ $isResponded ? 1 : 0 }}">
                                        @if ($isLocked)
                                            <span class="text-body-tertiary">-</span>
                                        @else
                                            <input class="form-check-input is-responded-input" type="checkbox" name="items[{{ $index }}][is_responded]" value="1" @checked($isResponded) @disabled($isReadOnly)>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="fw-semibold">{{ $rfqItem->description }}</div>
                                        <div class="text-body-tertiary fs-10">
                                            {{ \App\Models\SupplierQuoteRequestItem::lineTypeLabels()[$rfqItem->line_type] ?? $rfqItem->line_type }} | {{ $rfqItem->article_code ?: '-' }}
                                            @if ($isLocked)
                                                | Bloqueado
                                            @endif
                                        </div>
                                    </td>
                                    <td>
                                        {{ $rfqItem->unit_name ?: '-' }}
                                    </td>
                                    <td>
                                        {{ number_format((float) $rfqItem->quantity, 3, ',', '.') }}
                                    </td>
                                    <td>
                                        <input type="number" min="0" step="0.001" name="items[{{ $index }}][quantity]" value="{{ old("items.$index.quantity", $existing?->quantity ?? $rfqItem->quantity) }}" class="form-control form-control-sm response-field @error("items.$index.quantity") is-invalid @enderror" @disabled($isLocked || $isReadOnly)>
                                        @error("items.$index.quantity")<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                    </td>
                                    <td>
                                        <input type="number" min="0" step="0.0001" name="items[{{ $index }}][unit_price]" value="{{ old("items.$index.unit_price", $existing?->unit_price ?? '') }}" class="form-control form-control-sm response-field @error("items.$index.unit_price") is-invalid @enderror" @disabled($isLocked || $isReadOnly)>
                                        @error("items.$index.unit_price")<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                    </td>
                                    <td>
                                        <input type="number" min="0" max="100" step="0.01" name="items[{{ $index }}][discount_percent]" value="{{ old("items.$index.discount_percent", $existing?->discount_percent ?? 0) }}" class="form-control form-control-sm response-field @error("items.$index.discount_percent") is-invalid @enderror" @disabled($isLocked || $isReadOnly)>
                                        @error("items.$index.discount_percent")<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                    </td>
                                    <td>
                                        <input type="hidden" name="items[{{ $index }}][is_alternative]" value="0">
                                        <input class="form-check-input response-field" type="checkbox" name="items[{{ $index }}][is_alternative]" value="1" @checked($isAlternative) @disabled($isLocked || $isReadOnly)>
                                    </td>
                                    <td>
                                        <textarea rows="2" name="items[{{ $index }}][alternative_description]" class="form-control form-control-sm response-field @error("items.$index.alternative_description") is-invalid @enderror" @disabled($isLocked || $isReadOnly)>{{ old("items.$index.alternative_description", $existing?->alternative_description ?? '') }}</textarea>
                                        @error("items.$index.alternative_description")<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                    </td>
                                    <td>
                                        <input type="text" maxlength="120" name="items[{{ $index }}][brand]" value="{{ old("items.$index.brand", $existing?->brand ?? '') }}" class="form-control form-control-sm response-field @error("items.$index.brand") is-invalid @enderror" @disabled($isLocked || $isReadOnly)>
                                        @error("items.$index.brand")<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                    </td>
                                    <td class="pe-3">
                                        <textarea rows="2" name="items[{{ $index }}][notes]" class="form-control form-control-sm response-field @error("items.$index.notes") is-invalid @enderror" @disabled($isLocked || $isReadOnly)>{{ old("items.$index.notes", $existing?->notes ?? '') }}</textarea>
                                        @error("items.$index.notes")<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="11" class="text-center py-4 text-body-tertiary">Sem linhas.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header bg-body-tertiary">
                <h5 class="mb-0">Resumo automatico da proposta</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12 col-md-3">
                        <div class="text-body-tertiary fs-9">Subtotal linhas (s/IVA)</div>
                        <div class="fw-semibold" id="proposal_subtotal_preview">0,00 EUR</div>
                    </div>
                    <div class="col-12 col-md-3">
                        <div class="text-body-tertiary fs-9">Descontos de linha</div>
                        <div class="fw-semibold" id="proposal_line_discount_preview">0,00 EUR</div>
                    </div>
                    <div class="col-12 col-md-3">
                        <div class="text-body-tertiary fs-9">Desconto comercial</div>
                        <div class="fw-semibold" id="proposal_commercial_discount_preview">0,00 EUR</div>
                    </div>
                    <div class="col-12 col-md-3">
                        <div class="text-body-tertiary fs-9">Total proposta (s/IVA)</div>
                        <div class="fw-bold" id="proposal_total_preview">0,00 EUR</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex gap-2 justify-content-end">
            <a href="{{ route('admin.rfqs.show', $rfq->id) }}" class="btn btn-phoenix-secondary">Cancelar</a>
            @if (! $isReadOnly)
                <button type="submit" class="btn btn-primary">Guardar resposta</button>
            @endif
        </div>
    </form>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const isReadOnly = @json($isReadOnly);
            const rows = document.querySelectorAll('tr.response-row');
            const shippingInput = document.getElementById('shipping_cost');
            const commercialDiscountInput = document.getElementById('commercial_discount_text');
            const subtotalPreview = document.getElementById('proposal_subtotal_preview');
            const lineDiscountPreview = document.getElementById('proposal_line_discount_preview');
            const commercialDiscountPreview = document.getElementById('proposal_commercial_discount_preview');
            const totalPreview = document.getElementById('proposal_total_preview');

            const parseNumber = (value) => {
                if (value === null || value === undefined) return 0;
                const normalized = String(value).replace(',', '.').trim();
                const parsed = parseFloat(normalized);
                return Number.isFinite(parsed) ? parsed : 0;
            };

            const parseCommercialDiscountPercent = (value) => {
                if (!value) return 0;
                const match = String(value).match(/(\d+(?:[.,]\d+)?)/);
                if (!match) return 0;
                const percent = parseNumber(match[1]);
                if (percent < 0) return 0;
                if (percent > 100) return 100;
                return percent;
            };

            const formatCurrency = (value) => {
                return new Intl.NumberFormat('pt-PT', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                }).format(value) + ' EUR';
            };

            const updateTotals = () => {
                let subtotal = 0;
                let lineDiscountTotal = 0;
                let linesNetTotal = 0;

                rows.forEach((row) => {
                    const isResponded = row.querySelector('.is-responded-input')?.checked ?? false;
                    if (!isResponded) {
                        return;
                    }

                    const quantityInput = row.querySelector('input[name$="[quantity]"]');
                    const unitPriceInput = row.querySelector('input[name$="[unit_price]"]');
                    const discountInput = row.querySelector('input[name$="[discount_percent]"]');

                    const quantity = parseNumber(quantityInput?.value ?? 0);
                    const unitPrice = parseNumber(unitPriceInput?.value ?? 0);
                    const discountPercent = Math.min(100, Math.max(0, parseNumber(discountInput?.value ?? 0)));

                    const lineSubtotal = quantity * unitPrice;
                    const lineDiscount = lineSubtotal * (discountPercent / 100);
                    const lineNet = lineSubtotal - lineDiscount;

                    subtotal += lineSubtotal;
                    lineDiscountTotal += lineDiscount;
                    linesNetTotal += lineNet;
                });

                const commercialDiscountPercent = parseCommercialDiscountPercent(commercialDiscountInput?.value ?? '');
                const commercialDiscountTotal = linesNetTotal * (commercialDiscountPercent / 100);
                const shippingCost = parseNumber(shippingInput?.value ?? 0);
                const total = Math.max(0, linesNetTotal - commercialDiscountTotal) + shippingCost;

                if (subtotalPreview) subtotalPreview.textContent = formatCurrency(subtotal);
                if (lineDiscountPreview) lineDiscountPreview.textContent = formatCurrency(lineDiscountTotal);
                if (commercialDiscountPreview) commercialDiscountPreview.textContent = formatCurrency(commercialDiscountTotal);
                if (totalPreview) totalPreview.textContent = formatCurrency(total);
            };

            const syncRow = (row) => {
                if (isReadOnly) {
                    return;
                }

                const isResponded = row.querySelector('.is-responded-input')?.checked ?? false;
                const isAvailableInput = row.querySelector('.is-available-input');
                const fields = row.querySelectorAll('.response-field');

                if (isAvailableInput) {
                    isAvailableInput.value = isResponded ? '1' : '0';
                }

                fields.forEach((field) => {
                    if (!isResponded) {
                        field.disabled = true;
                        return;
                    }

                    field.disabled = false;
                });

                updateTotals();
            };

            rows.forEach((row) => {
                const respondedInput = row.querySelector('.is-responded-input');
                if (respondedInput) respondedInput.addEventListener('change', () => syncRow(row));

                row.querySelectorAll('input[name$="[quantity]"], input[name$="[unit_price]"], input[name$="[discount_percent]"]')
                    .forEach((input) => input.addEventListener('input', updateTotals));

                syncRow(row);
            });

            if (shippingInput) shippingInput.addEventListener('input', updateTotals);
            if (commercialDiscountInput) commercialDiscountInput.addEventListener('input', updateTotals);
            updateTotals();
        });
    </script>
@endpush
