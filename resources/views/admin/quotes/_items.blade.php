@php
    $formItems = old('items');

    if (! is_array($formItems)) {
        $formItems = isset($quote)
            ? $quote->items->map(function ($item) {
                return [
                    'sort_order' => $item->sort_order,
                    'line_type' => $item->line_type,
                    'article_id' => $item->article_id,
                    'description' => $item->description,
                    'internal_description' => $item->internal_description,
                    'quantity' => $item->quantity,
                    'unit_id' => $item->unit_id,
                    'unit_price' => $item->unit_price,
                    'discount_percent' => $item->discount_percent,
                    'vat_rate_id' => $item->vat_rate_id,
                    'vat_exemption_reason_id' => $item->vat_exemption_reason_id,
                ];
            })->values()->all()
            : [];
    }

    if ($formItems === []) {
        $formItems[] = [
            'sort_order' => 1,
            'line_type' => \App\Models\QuoteItem::TYPE_ARTICLE,
            'article_id' => null,
            'description' => null,
            'internal_description' => null,
            'quantity' => 1,
            'unit_id' => null,
            'unit_price' => null,
            'discount_percent' => null,
            'vat_rate_id' => null,
            'vat_exemption_reason_id' => null,
        ];
    }
@endphp

<div class="card mb-4">
    <div class="card-header bg-body-tertiary d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Linhas do orcamento</h5>
        <button type="button" class="btn btn-phoenix-secondary btn-sm" id="add-quote-item-row">Adicionar linha</button>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm fs-9 mb-0">
                <thead class="bg-body-tertiary">
                    <tr>
                        <th class="ps-3" style="width: 50px;">#</th>
                        <th style="min-width: 140px;">Tipo</th>
                        <th style="min-width: 220px;">Artigo</th>
                        <th style="min-width: 260px;">Descricao</th>
                        <th style="min-width: 120px;">Qtd.</th>
                        <th style="min-width: 130px;">Unid.</th>
                        <th style="min-width: 140px;">P. unitario</th>
                        <th style="min-width: 120px;">Desc. %</th>
                        <th style="min-width: 220px;">IVA</th>
                        <th style="min-width: 220px;">Motivo isencao</th>
                        <th class="pe-3 text-end" style="width: 90px;">Acao</th>
                    </tr>
                </thead>
                <tbody id="quote-items-body">
                    @foreach ($formItems as $rowIndex => $item)
                        @php
                            $lineType = $item['line_type'] ?? \App\Models\QuoteItem::TYPE_ARTICLE;
                        @endphp
                        <tr class="quote-item-row">
                            <td class="ps-3 align-middle">
                                <span class="row-index">{{ $loop->iteration }}</span>
                                <input type="hidden" name="items[{{ $rowIndex }}][sort_order]" value="{{ old("items.$rowIndex.sort_order", $item['sort_order'] ?? $loop->iteration) }}" class="sort-order-input">
                            </td>
                            <td>
                                <select name="items[{{ $rowIndex }}][line_type]" class="form-select form-select-sm line-type-select @error("items.$rowIndex.line_type") is-invalid @enderror">
                                    @foreach ($lineTypeOptions as $lineTypeKey => $lineTypeLabel)
                                        <option value="{{ $lineTypeKey }}" @selected((string) $lineType === (string) $lineTypeKey)>{{ $lineTypeLabel }}</option>
                                    @endforeach
                                </select>
                                @error("items.$rowIndex.line_type")<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </td>
                            <td>
                                <select name="items[{{ $rowIndex }}][article_id]" class="form-select form-select-sm article-select @error("items.$rowIndex.article_id") is-invalid @enderror">
                                    <option value="">Sem artigo</option>
                                    @foreach (($articleOptions ?? []) as $articleOption)
                                        <option
                                            value="{{ $articleOption->id }}"
                                            data-designation="{{ $articleOption->designation }}"
                                            data-unit-id="{{ $articleOption->unit_id }}"
                                            data-sale-price="{{ $articleOption->sale_price }}"
                                            data-discount="{{ $articleOption->direct_discount }}"
                                            data-vat-rate-id="{{ $articleOption->vat_rate_id }}"
                                            data-vat-reason-id="{{ $articleOption->vat_exemption_reason_id }}"
                                            @selected((string) old("items.$rowIndex.article_id", $item['article_id'] ?? '') === (string) $articleOption->id)
                                        >
                                            {{ $articleOption->code }} - {{ $articleOption->designation }}
                                        </option>
                                    @endforeach
                                </select>
                                @error("items.$rowIndex.article_id")<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </td>
                            <td>
                                <textarea name="items[{{ $rowIndex }}][description]" rows="2" class="form-control form-control-sm description-input @error("items.$rowIndex.description") is-invalid @enderror">{{ old("items.$rowIndex.description", $item['description'] ?? '') }}</textarea>
                                @error("items.$rowIndex.description")<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </td>
                            <td>
                                <input type="number" name="items[{{ $rowIndex }}][quantity]" class="form-control form-control-sm quantity-input @error("items.$rowIndex.quantity") is-invalid @enderror" min="0" step="0.001" value="{{ old("items.$rowIndex.quantity", $item['quantity'] ?? 1) }}">
                                @error("items.$rowIndex.quantity")<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </td>
                            <td>
                                <select name="items[{{ $rowIndex }}][unit_id]" class="form-select form-select-sm unit-select @error("items.$rowIndex.unit_id") is-invalid @enderror">
                                    <option value="">Sem unidade</option>
                                    @foreach (($unitOptions ?? []) as $unitOption)
                                        <option value="{{ $unitOption->id }}" @selected((string) old("items.$rowIndex.unit_id", $item['unit_id'] ?? '') === (string) $unitOption->id)>
                                            {{ $unitOption->code }} - {{ $unitOption->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error("items.$rowIndex.unit_id")<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </td>
                            <td>
                                <input type="number" name="items[{{ $rowIndex }}][unit_price]" class="form-control form-control-sm unit-price-input @error("items.$rowIndex.unit_price") is-invalid @enderror" min="0" step="0.0001" value="{{ old("items.$rowIndex.unit_price", $item['unit_price'] ?? '') }}">
                                @error("items.$rowIndex.unit_price")<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </td>
                            <td>
                                <input type="number" name="items[{{ $rowIndex }}][discount_percent]" class="form-control form-control-sm discount-input @error("items.$rowIndex.discount_percent") is-invalid @enderror" min="0" max="100" step="0.01" value="{{ old("items.$rowIndex.discount_percent", $item['discount_percent'] ?? '') }}">
                                @error("items.$rowIndex.discount_percent")<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </td>
                            <td>
                                <select name="items[{{ $rowIndex }}][vat_rate_id]" class="form-select form-select-sm vat-rate-select @error("items.$rowIndex.vat_rate_id") is-invalid @enderror">
                                    <option value="">Sem taxa</option>
                                    @foreach (($vatRateOptions ?? []) as $vatRateOption)
                                        <option
                                            value="{{ $vatRateOption->id }}"
                                            data-is-exempt="{{ $vatRateOption->is_exempt ? '1' : '0' }}"
                                            data-rate="{{ $vatRateOption->rate }}"
                                            @selected((string) old("items.$rowIndex.vat_rate_id", $item['vat_rate_id'] ?? '') === (string) $vatRateOption->id)
                                        >
                                            {{ $vatRateOption->name }} ({{ number_format((float) $vatRateOption->rate, 2) }}%)
                                        </option>
                                    @endforeach
                                </select>
                                @error("items.$rowIndex.vat_rate_id")<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </td>
                            <td>
                                <select name="items[{{ $rowIndex }}][vat_exemption_reason_id]" class="form-select form-select-sm vat-reason-select @error("items.$rowIndex.vat_exemption_reason_id") is-invalid @enderror">
                                    <option value="">Sem motivo</option>
                                    @foreach (($vatExemptionReasonOptions ?? []) as $reasonOption)
                                        <option value="{{ $reasonOption->id }}" @selected((string) old("items.$rowIndex.vat_exemption_reason_id", $item['vat_exemption_reason_id'] ?? '') === (string) $reasonOption->id)>
                                            {{ $reasonOption->code }} - {{ $reasonOption->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error("items.$rowIndex.vat_exemption_reason_id")<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </td>
                            <td class="pe-3 text-end align-middle">
                                <button type="button" class="btn btn-phoenix-danger btn-sm remove-row-btn">Remover</button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

<template id="quote-item-row-template">
    <tr class="quote-item-row">
        <td class="ps-3 align-middle">
            <span class="row-index">1</span>
            <input type="hidden" name="items[__INDEX__][sort_order]" value="1" class="sort-order-input">
        </td>
        <td>
            <select name="items[__INDEX__][line_type]" class="form-select form-select-sm line-type-select">
                @foreach ($lineTypeOptions as $lineTypeKey => $lineTypeLabel)
                    <option value="{{ $lineTypeKey }}">{{ $lineTypeLabel }}</option>
                @endforeach
            </select>
        </td>
        <td>
            <select name="items[__INDEX__][article_id]" class="form-select form-select-sm article-select">
                <option value="">Sem artigo</option>
                @foreach (($articleOptions ?? []) as $articleOption)
                    <option
                        value="{{ $articleOption->id }}"
                        data-designation="{{ $articleOption->designation }}"
                        data-unit-id="{{ $articleOption->unit_id }}"
                        data-sale-price="{{ $articleOption->sale_price }}"
                        data-discount="{{ $articleOption->direct_discount }}"
                        data-vat-rate-id="{{ $articleOption->vat_rate_id }}"
                        data-vat-reason-id="{{ $articleOption->vat_exemption_reason_id }}"
                    >
                        {{ $articleOption->code }} - {{ $articleOption->designation }}
                    </option>
                @endforeach
            </select>
        </td>
        <td><textarea name="items[__INDEX__][description]" rows="2" class="form-control form-control-sm description-input"></textarea></td>
        <td><input type="number" name="items[__INDEX__][quantity]" class="form-control form-control-sm quantity-input" min="0" step="0.001" value="1"></td>
        <td>
            <select name="items[__INDEX__][unit_id]" class="form-select form-select-sm unit-select">
                <option value="">Sem unidade</option>
                @foreach (($unitOptions ?? []) as $unitOption)
                    <option value="{{ $unitOption->id }}">{{ $unitOption->code }} - {{ $unitOption->name }}</option>
                @endforeach
            </select>
        </td>
        <td><input type="number" name="items[__INDEX__][unit_price]" class="form-control form-control-sm unit-price-input" min="0" step="0.0001"></td>
        <td><input type="number" name="items[__INDEX__][discount_percent]" class="form-control form-control-sm discount-input" min="0" max="100" step="0.01"></td>
        <td>
            <select name="items[__INDEX__][vat_rate_id]" class="form-select form-select-sm vat-rate-select">
                <option value="">Sem taxa</option>
                @foreach (($vatRateOptions ?? []) as $vatRateOption)
                    <option value="{{ $vatRateOption->id }}" data-is-exempt="{{ $vatRateOption->is_exempt ? '1' : '0' }}" data-rate="{{ $vatRateOption->rate }}">
                        {{ $vatRateOption->name }} ({{ number_format((float) $vatRateOption->rate, 2) }}%)
                    </option>
                @endforeach
            </select>
        </td>
        <td>
            <select name="items[__INDEX__][vat_exemption_reason_id]" class="form-select form-select-sm vat-reason-select">
                <option value="">Sem motivo</option>
                @foreach (($vatExemptionReasonOptions ?? []) as $reasonOption)
                    <option value="{{ $reasonOption->id }}">{{ $reasonOption->code }} - {{ $reasonOption->name }}</option>
                @endforeach
            </select>
        </td>
        <td class="pe-3 text-end align-middle">
            <button type="button" class="btn btn-phoenix-danger btn-sm remove-row-btn">Remover</button>
        </td>
    </tr>
</template>

@once
    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const tbody = document.getElementById('quote-items-body');
                const addButton = document.getElementById('add-quote-item-row');
                const template = document.getElementById('quote-item-row-template');

                if (!tbody || !addButton || !template) {
                    return;
                }

                const syncRowOrder = () => {
                    Array.from(tbody.querySelectorAll('tr.quote-item-row')).forEach((row, idx) => {
                        const position = idx + 1;
                        const indexLabel = row.querySelector('.row-index');
                        const sortOrderInput = row.querySelector('.sort-order-input');
                        if (indexLabel) indexLabel.textContent = position;
                        if (sortOrderInput) sortOrderInput.value = position;
                    });
                };

                const parseNumber = (value) => {
                    const normalized = String(value ?? '').replace(',', '.').trim();
                    if (normalized === '') {
                        return 0;
                    }

                    const parsed = Number.parseFloat(normalized);
                    return Number.isFinite(parsed) ? parsed : 0;
                };

                const formatMoney = (amount, currencyCode) => {
                    const formatter = new Intl.NumberFormat('pt-PT', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    });

                    return `${formatter.format(amount)} ${currencyCode}`;
                };

                const updatePreviewTotals = () => {
                    const subtotalInput = document.getElementById('quote-preview-subtotal');
                    const discountTotalInput = document.getElementById('quote-preview-discount-total');
                    const taxTotalInput = document.getElementById('quote-preview-tax-total');
                    const grandTotalInput = document.getElementById('quote-preview-grand-total');

                    if (!subtotalInput || !discountTotalInput || !taxTotalInput || !grandTotalInput) {
                        return;
                    }

                    const currencyInput = document.getElementById('currency');
                    const currencyCode = (currencyInput?.value || 'EUR').toUpperCase();

                    let subtotal = 0;
                    let discountTotal = 0;
                    let taxTotal = 0;
                    let grandTotal = 0;

                    Array.from(tbody.querySelectorAll('tr.quote-item-row')).forEach((row) => {
                        const lineType = row.querySelector('.line-type-select')?.value || 'article';
                        if (lineType === 'section' || lineType === 'note') {
                            return;
                        }

                        const quantity = parseNumber(row.querySelector('.quantity-input')?.value);
                        const unitPrice = parseNumber(row.querySelector('.unit-price-input')?.value);
                        const discountPercent = Math.max(0, Math.min(100, parseNumber(row.querySelector('.discount-input')?.value)));

                        const lineSubtotal = Math.max(0, quantity * unitPrice);
                        const lineDiscount = Math.max(0, lineSubtotal * (discountPercent / 100));
                        const taxableBase = Math.max(0, lineSubtotal - lineDiscount);

                        const vatSelect = row.querySelector('.vat-rate-select');
                        const vatOption = vatSelect?.options?.[vatSelect.selectedIndex] ?? null;
                        const vatPercent = parseNumber(vatOption?.dataset?.rate);
                        const isExempt = vatOption?.dataset?.isExempt === '1';
                        const lineTax = isExempt ? 0 : Math.max(0, taxableBase * (vatPercent / 100));
                        const lineTotal = taxableBase + lineTax;

                        subtotal += lineSubtotal;
                        discountTotal += lineDiscount;
                        taxTotal += lineTax;
                        grandTotal += lineTotal;
                    });

                    subtotalInput.value = formatMoney(subtotal, currencyCode);
                    discountTotalInput.value = formatMoney(discountTotal, currencyCode);
                    taxTotalInput.value = formatMoney(taxTotal, currencyCode);
                    grandTotalInput.value = formatMoney(grandTotal, currencyCode);
                };

                const syncVatReasonField = (row) => {
                    const vatRateSelect = row.querySelector('.vat-rate-select');
                    const reasonSelect = row.querySelector('.vat-reason-select');
                    if (!vatRateSelect || !reasonSelect) {
                        return;
                    }

                    const selected = vatRateSelect.options[vatRateSelect.selectedIndex];
                    const isExempt = selected ? selected.dataset.isExempt === '1' : false;
                    reasonSelect.disabled = !isExempt;
                    reasonSelect.required = isExempt;

                    if (!isExempt) {
                        reasonSelect.value = '';
                    }
                };

                const syncLineType = (row) => {
                    const typeSelect = row.querySelector('.line-type-select');
                    if (!typeSelect) {
                        return;
                    }

                    const type = typeSelect.value;
                    const articleSelect = row.querySelector('.article-select');
                    const quantityInput = row.querySelector('.quantity-input');
                    const unitSelect = row.querySelector('.unit-select');
                    const unitPriceInput = row.querySelector('.unit-price-input');
                    const discountInput = row.querySelector('.discount-input');
                    const vatRateSelect = row.querySelector('.vat-rate-select');

                    const isCommercial = type === 'article' || type === 'text';

                    if (articleSelect) articleSelect.disabled = type !== 'article';
                    if (quantityInput) quantityInput.disabled = !isCommercial;
                    if (unitSelect) unitSelect.disabled = !isCommercial;
                    if (unitPriceInput) unitPriceInput.disabled = !isCommercial;
                    if (discountInput) discountInput.disabled = !isCommercial;
                    if (vatRateSelect) vatRateSelect.disabled = !isCommercial;

                    if (!isCommercial) {
                        if (quantityInput) quantityInput.value = 1;
                        if (unitPriceInput) unitPriceInput.value = 0;
                        if (discountInput) discountInput.value = '';
                        if (unitSelect) unitSelect.value = '';
                        if (vatRateSelect) vatRateSelect.value = '';
                    }

                    row.classList.toggle('table-warning', type === 'section');
                    row.classList.toggle('table-light', type === 'note');

                    syncVatReasonField(row);
                    updatePreviewTotals();
                };

                const syncArticleData = (row) => {
                    const typeSelect = row.querySelector('.line-type-select');
                    const articleSelect = row.querySelector('.article-select');
                    if (!typeSelect || !articleSelect || typeSelect.value !== 'article') {
                        return;
                    }

                    const selected = articleSelect.options[articleSelect.selectedIndex];
                    if (!selected || !selected.value) {
                        return;
                    }

                    const descriptionInput = row.querySelector('.description-input');
                    const unitSelect = row.querySelector('.unit-select');
                    const unitPriceInput = row.querySelector('.unit-price-input');
                    const discountInput = row.querySelector('.discount-input');
                    const vatRateSelect = row.querySelector('.vat-rate-select');
                    const vatReasonSelect = row.querySelector('.vat-reason-select');

                    if (descriptionInput && descriptionInput.value.trim() === '') {
                        descriptionInput.value = selected.dataset.designation || '';
                    }

                    if (unitSelect && selected.dataset.unitId) {
                        unitSelect.value = selected.dataset.unitId;
                    }

                    if (unitPriceInput && unitPriceInput.value === '' && selected.dataset.salePrice) {
                        unitPriceInput.value = selected.dataset.salePrice;
                    }

                    if (discountInput && discountInput.value === '' && selected.dataset.discount) {
                        discountInput.value = selected.dataset.discount;
                    }

                    if (vatRateSelect && selected.dataset.vatRateId) {
                        vatRateSelect.value = selected.dataset.vatRateId;
                    }

                    if (vatReasonSelect && selected.dataset.vatReasonId) {
                        vatReasonSelect.value = selected.dataset.vatReasonId;
                    }

                    syncVatReasonField(row);
                    updatePreviewTotals();
                };

                const bindRowEvents = (row) => {
                    const removeButton = row.querySelector('.remove-row-btn');
                    if (removeButton) {
                        removeButton.addEventListener('click', function () {
                            if (tbody.querySelectorAll('tr.quote-item-row').length <= 1) {
                                return;
                            }
                            row.remove();
                            syncRowOrder();
                            updatePreviewTotals();
                        });
                    }

                    const lineTypeSelect = row.querySelector('.line-type-select');
                    if (lineTypeSelect) {
                        lineTypeSelect.addEventListener('change', function () {
                            syncLineType(row);
                        });
                    }

                    const articleSelect = row.querySelector('.article-select');
                    if (articleSelect) {
                        articleSelect.addEventListener('change', function () {
                            syncArticleData(row);
                            updatePreviewTotals();
                        });
                    }

                    const vatRateSelect = row.querySelector('.vat-rate-select');
                    if (vatRateSelect) {
                        vatRateSelect.addEventListener('change', function () {
                            syncVatReasonField(row);
                            updatePreviewTotals();
                        });
                    }

                    ['.quantity-input', '.unit-price-input', '.discount-input', '.vat-reason-select', '.description-input'].forEach((selector) => {
                        const input = row.querySelector(selector);
                        if (!input) {
                            return;
                        }

                        input.addEventListener('input', updatePreviewTotals);
                        input.addEventListener('change', updatePreviewTotals);
                    });

                    syncLineType(row);
                    syncVatReasonField(row);
                    updatePreviewTotals();
                };

                addButton.addEventListener('click', function () {
                    const nextIndex = tbody.querySelectorAll('tr.quote-item-row').length;
                    const html = template.innerHTML.replaceAll('__INDEX__', nextIndex);
                    tbody.insertAdjacentHTML('beforeend', html);
                    const newRow = tbody.querySelectorAll('tr.quote-item-row')[nextIndex];
                    bindRowEvents(newRow);
                    syncRowOrder();
                });

                Array.from(tbody.querySelectorAll('tr.quote-item-row')).forEach((row) => bindRowEvents(row));
                syncRowOrder();
                updatePreviewTotals();

                const currencyInput = document.getElementById('currency');
                if (currencyInput) {
                    currencyInput.addEventListener('input', updatePreviewTotals);
                    currencyInput.addEventListener('change', updatePreviewTotals);
                }
            });
        </script>
    @endpush
@endonce
