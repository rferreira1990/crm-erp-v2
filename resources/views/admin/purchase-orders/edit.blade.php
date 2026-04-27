@extends('layouts.admin')

@section('title', 'Editar encomenda a fornecedor')
@section('page_title', 'Editar encomenda a fornecedor')
@section('page_subtitle', $purchaseOrder->number)

@section('page_actions')
    <a href="{{ route('admin.purchase-orders.show', $purchaseOrder->id) }}" class="btn btn-phoenix-secondary btn-sm">Voltar</a>
@endsection

@section('breadcrumbs')
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.purchase-orders.index') }}">Encomendas a fornecedor</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.purchase-orders.show', $purchaseOrder->id) }}">{{ $purchaseOrder->number }}</a></li>
        <li class="breadcrumb-item active" aria-current="page">Editar</li>
    </ol>
@endsection

@section('content')
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
    @endif

    @php
        $oldItems = old('items');
        if (! is_array($oldItems) || $oldItems === []) {
            $oldItems = $purchaseOrder->items
                ->sortBy([
                    ['line_order', 'asc'],
                    ['id', 'asc'],
                ])
                ->values()
                ->map(fn ($item) => [
                    'article_id' => $item->article_id,
                    'description' => $item->description,
                    'unit_name' => $item->unit_name,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'discount_percent' => $item->discount_percent,
                    'notes' => $item->notes,
                ])
                ->all();
        }

        if (! is_array($oldItems) || $oldItems === []) {
            $oldItems = [[
                'article_id' => null,
                'description' => null,
                'unit_name' => null,
                'quantity' => '1',
                'unit_price' => null,
                'discount_percent' => '0',
                'notes' => null,
            ]];
        }
    @endphp

    <form method="POST" action="{{ route('admin.purchase-orders.update', $purchaseOrder->id) }}" id="manualPurchaseOrderForm">
        @csrf
        @method('PATCH')

        <div class="card mb-4">
            <div class="card-header bg-body-tertiary">
                <h5 class="mb-0">Dados base</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12 col-lg-6">
                        <label for="supplier_id" class="form-label">Fornecedor</label>
                        <select id="supplier_id" name="supplier_id" class="form-select @error('supplier_id') is-invalid @enderror" required>
                            <option value="">Selecionar fornecedor</option>
                            @foreach ($suppliers as $supplier)
                                <option value="{{ $supplier->id }}" @selected((string) old('supplier_id', $purchaseOrder->supplier_id) === (string) $supplier->id)>
                                    {{ $supplier->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('supplier_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12 col-md-4 col-lg-3">
                        <label for="issue_date" class="form-label">Data da encomenda</label>
                        <input
                            type="date"
                            id="issue_date"
                            name="issue_date"
                            value="{{ old('issue_date', optional($purchaseOrder->issue_date)->format('Y-m-d')) }}"
                            class="form-control @error('issue_date') is-invalid @enderror"
                            required
                        >
                        @error('issue_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12 col-md-4 col-lg-3">
                        <label for="expected_delivery_date" class="form-label">Entrega prevista</label>
                        <input
                            type="date"
                            id="expected_delivery_date"
                            name="expected_delivery_date"
                            value="{{ old('expected_delivery_date', optional($purchaseOrder->expected_delivery_date)->format('Y-m-d')) }}"
                            class="form-control @error('expected_delivery_date') is-invalid @enderror"
                        >
                        @error('expected_delivery_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12 col-md-4 col-lg-3">
                        <label for="shipping_total" class="form-label">Portes</label>
                        <input
                            type="number"
                            id="shipping_total"
                            name="shipping_total"
                            value="{{ old('shipping_total', $purchaseOrder->shipping_total) }}"
                            class="form-control @error('shipping_total') is-invalid @enderror"
                            min="0"
                            step="0.01"
                        >
                        @error('shipping_total')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12 col-lg-6">
                        <label for="supplier_notes" class="form-label">Referencia externa / notas fornecedor</label>
                        <textarea
                            id="supplier_notes"
                            name="supplier_notes"
                            rows="3"
                            class="form-control @error('supplier_notes') is-invalid @enderror"
                        >{{ old('supplier_notes', $purchaseOrder->supplier_notes) }}</textarea>
                        @error('supplier_notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12 col-lg-6">
                        <label for="internal_notes" class="form-label">Observacoes internas</label>
                        <textarea
                            id="internal_notes"
                            name="internal_notes"
                            rows="3"
                            class="form-control @error('internal_notes') is-invalid @enderror"
                        >{{ old('internal_notes', $purchaseOrder->internal_notes) }}</textarea>
                        @error('internal_notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header bg-body-tertiary d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Linhas da encomenda</h5>
                <button type="button" class="btn btn-phoenix-secondary btn-sm" id="addLineBtn">Adicionar linha</button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm fs-9 mb-0 align-middle">
                        <thead class="bg-body-tertiary">
                            <tr>
                                <th class="ps-3">Artigo</th>
                                <th>Descricao</th>
                                <th>Unid.</th>
                                <th>Qtd.</th>
                                <th>P. unitario</th>
                                <th>Desc. %</th>
                                <th>Total linha</th>
                                <th>Notas</th>
                                <th class="pe-3"></th>
                            </tr>
                        </thead>
                        <tbody id="purchaseOrderLinesBody">
                            @foreach ($oldItems as $index => $line)
                                <tr data-line-row>
                                    <td class="ps-3" style="min-width: 220px;">
                                        <select name="items[{{ $index }}][article_id]" class="form-select form-select-sm line-article-select">
                                            <option value="">Sem artigo (linha manual)</option>
                                            @foreach ($articles as $article)
                                                <option
                                                    value="{{ $article->id }}"
                                                    data-description="{{ $article->designation }}"
                                                    data-unit="{{ $article->unit?->code ?? $article->unit?->name ?? '' }}"
                                                    data-price="{{ number_format((float) ($article->cost_price ?? 0), 4, '.', '') }}"
                                                    @selected((string) ($line['article_id'] ?? '') === (string) $article->id)
                                                >
                                                    {{ $article->code }} - {{ $article->designation }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td style="min-width: 260px;">
                                        <input
                                            type="text"
                                            name="items[{{ $index }}][description]"
                                            value="{{ $line['description'] ?? '' }}"
                                            class="form-control form-control-sm"
                                            maxlength="1000"
                                        >
                                    </td>
                                    <td style="min-width: 140px;">
                                        <select name="items[{{ $index }}][unit_name]" class="form-select form-select-sm line-unit-select">
                                            <option value="">Sem unidade</option>
                                            @foreach ($units as $unit)
                                                @php
                                                    $unitLabel = trim((string) ($unit->code !== '' ? $unit->code : $unit->name));
                                                @endphp
                                                @if ($unitLabel !== '')
                                                    <option value="{{ $unitLabel }}" @selected((string) ($line['unit_name'] ?? '') === $unitLabel)>
                                                        {{ $unitLabel }} - {{ $unit->name }}
                                                    </option>
                                                @endif
                                            @endforeach
                                        </select>
                                    </td>
                                    <td style="min-width: 120px;">
                                        <input
                                            type="number"
                                            name="items[{{ $index }}][quantity]"
                                            value="{{ $line['quantity'] ?? '' }}"
                                            class="form-control form-control-sm line-quantity-input"
                                            min="0.001"
                                            step="0.001"
                                            required
                                        >
                                    </td>
                                    <td style="min-width: 130px;">
                                        <input
                                            type="number"
                                            name="items[{{ $index }}][unit_price]"
                                            value="{{ $line['unit_price'] ?? '' }}"
                                            class="form-control form-control-sm line-price-input"
                                            min="0"
                                            step="0.0001"
                                            required
                                        >
                                    </td>
                                    <td style="min-width: 110px;">
                                        <input
                                            type="number"
                                            name="items[{{ $index }}][discount_percent]"
                                            value="{{ $line['discount_percent'] ?? '0' }}"
                                            class="form-control form-control-sm line-discount-input"
                                            min="0"
                                            max="100"
                                            step="0.01"
                                        >
                                    </td>
                                    <td style="min-width: 120px;">
                                        <span class="line-total-value fw-semibold">0,00</span>
                                    </td>
                                    <td style="min-width: 180px;">
                                        <input
                                            type="text"
                                            name="items[{{ $index }}][notes]"
                                            value="{{ $line['notes'] ?? '' }}"
                                            class="form-control form-control-sm"
                                            maxlength="1000"
                                        >
                                    </td>
                                    <td class="pe-3 text-end">
                                        <button type="button" class="btn btn-phoenix-danger btn-sm remove-line-btn">Remover</button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer">
                <div class="row g-2 justify-content-end">
                    <div class="col-12 col-md-4 col-lg-3">
                        <div class="d-flex justify-content-between">
                            <span class="text-body-tertiary">Subtotal:</span>
                            <span class="fw-semibold" id="poSubtotalPreview">0,00 EUR</span>
                        </div>
                    </div>
                    <div class="col-12 col-md-4 col-lg-3">
                        <div class="d-flex justify-content-between">
                            <span class="text-body-tertiary">Total:</span>
                            <span class="fw-bold" id="poGrandTotalPreview">0,00 EUR</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex gap-2 justify-content-end">
            <a href="{{ route('admin.purchase-orders.show', $purchaseOrder->id) }}" class="btn btn-phoenix-secondary">Cancelar</a>
            <button type="submit" class="btn btn-primary">Guardar alteracoes</button>
        </div>
    </form>

    <template id="po-line-template">
        <tr data-line-row>
            <td class="ps-3" style="min-width: 220px;">
                <select name="items[__INDEX__][article_id]" class="form-select form-select-sm line-article-select">
                    <option value="">Sem artigo (linha manual)</option>
                    @foreach ($articles as $article)
                        <option
                            value="{{ $article->id }}"
                            data-description="{{ $article->designation }}"
                            data-unit="{{ $article->unit?->code ?? $article->unit?->name ?? '' }}"
                            data-price="{{ number_format((float) ($article->cost_price ?? 0), 4, '.', '') }}"
                        >
                            {{ $article->code }} - {{ $article->designation }}
                        </option>
                    @endforeach
                </select>
            </td>
            <td style="min-width: 260px;">
                <input type="text" name="items[__INDEX__][description]" class="form-control form-control-sm" maxlength="1000">
            </td>
            <td style="min-width: 100px;">
                <select name="items[__INDEX__][unit_name]" class="form-select form-select-sm line-unit-select">
                    <option value="">Sem unidade</option>
                    @foreach ($units as $unit)
                        @php
                            $unitLabel = trim((string) ($unit->code !== '' ? $unit->code : $unit->name));
                        @endphp
                        @if ($unitLabel !== '')
                            <option value="{{ $unitLabel }}">{{ $unitLabel }} - {{ $unit->name }}</option>
                        @endif
                    @endforeach
                </select>
            </td>
            <td style="min-width: 120px;">
                <input type="number" name="items[__INDEX__][quantity]" value="1" class="form-control form-control-sm line-quantity-input" min="0.001" step="0.001" required>
            </td>
            <td style="min-width: 130px;">
                <input type="number" name="items[__INDEX__][unit_price]" value="0.0000" class="form-control form-control-sm line-price-input" min="0" step="0.0001" required>
            </td>
            <td style="min-width: 110px;">
                <input type="number" name="items[__INDEX__][discount_percent]" value="0" class="form-control form-control-sm line-discount-input" min="0" max="100" step="0.01">
            </td>
            <td style="min-width: 120px;">
                <span class="line-total-value fw-semibold">0,00</span>
            </td>
            <td style="min-width: 180px;">
                <input type="text" name="items[__INDEX__][notes]" class="form-control form-control-sm" maxlength="1000">
            </td>
            <td class="pe-3 text-end">
                <button type="button" class="btn btn-phoenix-danger btn-sm remove-line-btn">Remover</button>
            </td>
        </tr>
    </template>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const linesBody = document.getElementById('purchaseOrderLinesBody');
            const addLineBtn = document.getElementById('addLineBtn');
            const template = document.getElementById('po-line-template');
            const shippingInput = document.getElementById('shipping_total');
            const subtotalPreview = document.getElementById('poSubtotalPreview');
            const grandTotalPreview = document.getElementById('poGrandTotalPreview');

            if (!linesBody || !addLineBtn || !template) {
                return;
            }

            let lineIndex = linesBody.querySelectorAll('[data-line-row]').length;

            const parseNumber = (value) => {
                const normalized = String(value ?? '').trim().replace(',', '.');
                const parsed = Number.parseFloat(normalized);
                return Number.isFinite(parsed) ? parsed : 0;
            };

            const formatMoney = (value) => {
                return value.toLocaleString('pt-PT', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
            };

            const updateLineTotal = (line) => {
                const quantityInput = line.querySelector('.line-quantity-input');
                const priceInput = line.querySelector('.line-price-input');
                const discountInput = line.querySelector('.line-discount-input');
                const totalTarget = line.querySelector('.line-total-value');
                if (!quantityInput || !priceInput || !discountInput || !totalTarget) {
                    return 0;
                }

                const gross = parseNumber(quantityInput.value) * parseNumber(priceInput.value);
                const discountPercent = Math.min(100, Math.max(0, parseNumber(discountInput.value)));
                const discountAmount = gross * (discountPercent / 100);
                const total = gross - discountAmount;
                totalTarget.textContent = formatMoney(total);

                return total;
            };

            const updateTotals = () => {
                let subtotal = 0;
                linesBody.querySelectorAll('[data-line-row]').forEach((line) => {
                    subtotal += updateLineTotal(line);
                });

                const shipping = shippingInput ? parseNumber(shippingInput.value) : 0;
                const grandTotal = subtotal + shipping;

                if (subtotalPreview) {
                    subtotalPreview.textContent = formatMoney(subtotal) + ' EUR';
                }

                if (grandTotalPreview) {
                    grandTotalPreview.textContent = formatMoney(grandTotal) + ' EUR';
                }
            };

            const bindArticleSelect = (line) => {
                const select = line.querySelector('.line-article-select');
                if (!select) {
                    return;
                }

                select.addEventListener('change', () => {
                    const selectedOption = select.options[select.selectedIndex];
                    if (!selectedOption) {
                        return;
                    }

                    const descriptionInput = line.querySelector('input[name$="[description]"]');
                    const unitInput = line.querySelector('.line-unit-select');
                    const priceInput = line.querySelector('.line-price-input');

                    if (descriptionInput && descriptionInput.value.trim() === '' && selectedOption.dataset.description) {
                        descriptionInput.value = selectedOption.dataset.description;
                    }

                    if (unitInput && selectedOption.dataset.unit) {
                        unitInput.value = selectedOption.dataset.unit;
                    }

                    if (priceInput && (priceInput.value.trim() === '' || parseNumber(priceInput.value) === 0) && selectedOption.dataset.price) {
                        priceInput.value = selectedOption.dataset.price;
                    }

                    updateTotals();
                });
            };

            const bindLineEvents = (line) => {
                bindArticleSelect(line);

                line.querySelectorAll('.line-quantity-input, .line-price-input').forEach((input) => {
                    input.addEventListener('input', updateTotals);
                });
                line.querySelectorAll('.line-discount-input').forEach((input) => {
                    input.addEventListener('input', updateTotals);
                });

                const removeBtn = line.querySelector('.remove-line-btn');
                if (removeBtn) {
                    removeBtn.addEventListener('click', () => {
                        const rows = linesBody.querySelectorAll('[data-line-row]');
                        if (rows.length === 1) {
                            return;
                        }

                        line.remove();
                        updateTotals();
                    });
                }
            };

            const addLine = () => {
                const html = template.innerHTML.replaceAll('__INDEX__', String(lineIndex++));
                linesBody.insertAdjacentHTML('beforeend', html);
                const line = linesBody.querySelector('[data-line-row]:last-child');
                if (line) {
                    bindLineEvents(line);
                }
                updateTotals();
            };

            linesBody.querySelectorAll('[data-line-row]').forEach((line) => bindLineEvents(line));

            addLineBtn.addEventListener('click', addLine);
            if (shippingInput) {
                shippingInput.addEventListener('input', updateTotals);
            }

            updateTotals();
        });
    </script>
@endpush

