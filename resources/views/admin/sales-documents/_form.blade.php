@php
    /** @var bool $isEdit */
    $isEdit = $isEdit ?? false;
    $sourceTypeValue = old('source_type', $defaults['source_type'] ?? $sourceType ?? \App\Models\SalesDocument::SOURCE_MANUAL);
    $quoteIdValue = old('quote_id', $defaults['quote_id'] ?? null);
    $constructionSiteIdValue = old('construction_site_id', $defaults['construction_site_id'] ?? null);
    $customerIdValue = old('customer_id', $defaults['customer_id'] ?? null);
    $customerContactIdValue = old('customer_contact_id', $defaults['customer_contact_id'] ?? null);
    $issueDateValue = old('issue_date', $defaults['issue_date'] ?? now()->toDateString());
    $dueDateValue = old('due_date', $defaults['due_date'] ?? null);
    $notesValue = old('notes', $defaults['notes'] ?? null);

    $itemsInput = old('items', $defaults['items'] ?? []);
    if (! is_array($itemsInput) || $itemsInput === []) {
        $itemsInput = [[
            'article_id' => null,
            'description' => null,
            'unit_id' => null,
            'unit_name_snapshot' => null,
            'quantity' => '1.000',
            'unit_price' => '0.0000',
            'discount_percent' => '0.00',
            'tax_rate' => '0.00',
        ]];
    }
@endphp

@if ($errors->any())
    <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
@endif

<form method="POST" action="{{ $formAction }}" id="salesDocumentForm">
    @csrf
    @if ($isEdit)
        @method('PATCH')
    @endif

    <input type="hidden" name="source_type" value="{{ $sourceTypeValue }}">
    <input type="hidden" name="quote_id" value="{{ $quoteIdValue }}">
    <input type="hidden" name="construction_site_id" value="{{ $constructionSiteIdValue }}">

    <div class="card mb-4">
        <div class="card-header bg-body-tertiary">
            <h5 class="mb-0">Dados base</h5>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-12 col-lg-4">
                    <label class="form-label">Origem</label>
                    <input type="text" class="form-control" value="{{ $sourceLabels[$sourceTypeValue] ?? $sourceTypeValue }}" readonly>
                </div>
                <div class="col-12 col-lg-4">
                    <label class="form-label">Orcamento origem</label>
                    <input type="text" class="form-control" value="{{ $quoteIdValue ? ('#'.$quoteIdValue) : '-' }}" readonly>
                </div>
                <div class="col-12 col-lg-4">
                    <label class="form-label">Obra origem</label>
                    <input type="text" class="form-control" value="{{ $constructionSiteIdValue ? ('#'.$constructionSiteIdValue) : '-' }}" readonly>
                </div>

                <div class="col-12 col-lg-6">
                    <label for="customer_id" class="form-label">Cliente</label>
                    <select id="customer_id" name="customer_id" class="form-select @error('customer_id') is-invalid @enderror" required>
                        <option value="">Selecionar cliente</option>
                        @foreach ($customers as $customer)
                            <option value="{{ $customer->id }}" @selected((string) $customerIdValue === (string) $customer->id)>{{ $customer->name }}</option>
                        @endforeach
                    </select>
                    @error('customer_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-12 col-lg-6">
                    <label for="customer_contact_id" class="form-label">Contacto</label>
                    <select id="customer_contact_id" name="customer_contact_id" class="form-select @error('customer_contact_id') is-invalid @enderror">
                        <option value="">Sem contacto</option>
                        @foreach ($contacts as $contact)
                            <option value="{{ $contact->id }}" @selected((string) $customerContactIdValue === (string) $contact->id)>
                                {{ $contact->name }}{{ $contact->email ? ' ('.$contact->email.')' : '' }}
                            </option>
                        @endforeach
                    </select>
                    @error('customer_contact_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-12 col-md-6 col-lg-3">
                    <label for="issue_date" class="form-label">Data documento</label>
                    <input type="date" id="issue_date" name="issue_date" value="{{ $issueDateValue }}" class="form-control @error('issue_date') is-invalid @enderror" required>
                    @error('issue_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-12 col-md-6 col-lg-3">
                    <label for="due_date" class="form-label">Vencimento</label>
                    <input type="date" id="due_date" name="due_date" value="{{ $dueDateValue }}" class="form-control @error('due_date') is-invalid @enderror">
                    @error('due_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-12 col-lg-6">
                    <label for="notes" class="form-label">Notas</label>
                    <textarea id="notes" name="notes" rows="3" class="form-control @error('notes') is-invalid @enderror">{{ $notesValue }}</textarea>
                    @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header bg-body-tertiary d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Linhas</h5>
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
                            <th>Taxa %</th>
                            <th>Total linha</th>
                            <th class="pe-3"></th>
                        </tr>
                    </thead>
                    <tbody id="salesDocumentLinesBody">
                        @foreach ($itemsInput as $index => $line)
                            <tr data-line-row>
                                <td class="ps-3" style="min-width: 220px;">
                                    <select name="items[{{ $index }}][article_id]" class="form-select form-select-sm line-article-select">
                                        <option value="">Sem artigo (linha manual)</option>
                                        @foreach ($articles as $article)
                                            <option
                                                value="{{ $article->id }}"
                                                data-description="{{ $article->designation }}"
                                                data-unit-id="{{ $article->unit?->id ?? '' }}"
                                                data-unit-name="{{ $article->unit?->code ?? $article->unit?->name ?? '' }}"
                                                data-price="{{ number_format((float) ($article->sale_price ?? 0), 4, '.', '') }}"
                                                @selected((string) ($line['article_id'] ?? '') === (string) $article->id)
                                            >
                                                {{ $article->code }} - {{ $article->designation }}
                                            </option>
                                        @endforeach
                                    </select>
                                </td>
                                <td style="min-width: 260px;">
                                    <input type="text" name="items[{{ $index }}][description]" value="{{ $line['description'] ?? '' }}" class="form-control form-control-sm" maxlength="1000">
                                </td>
                                <td style="min-width: 170px;">
                                    <select name="items[{{ $index }}][unit_id]" class="form-select form-select-sm line-unit-select">
                                        <option value="">Sem unidade</option>
                                        @foreach ($units as $unit)
                                            <option value="{{ $unit->id }}" @selected((string) ($line['unit_id'] ?? '') === (string) $unit->id)>{{ $unit->code ?: $unit->name }} - {{ $unit->name }}</option>
                                        @endforeach
                                    </select>
                                    <input type="hidden" name="items[{{ $index }}][unit_name_snapshot]" value="{{ $line['unit_name_snapshot'] ?? '' }}" class="line-unit-name-input">
                                </td>
                                <td style="min-width: 120px;">
                                    <input type="number" name="items[{{ $index }}][quantity]" value="{{ $line['quantity'] ?? '' }}" class="form-control form-control-sm line-quantity-input" min="0.001" step="0.001" required>
                                </td>
                                <td style="min-width: 130px;">
                                    <input type="number" name="items[{{ $index }}][unit_price]" value="{{ $line['unit_price'] ?? '' }}" class="form-control form-control-sm line-price-input" min="0" step="0.0001" required>
                                </td>
                                <td style="min-width: 110px;">
                                    <input type="number" name="items[{{ $index }}][discount_percent]" value="{{ $line['discount_percent'] ?? '0' }}" class="form-control form-control-sm line-discount-input" min="0" max="100" step="0.01">
                                </td>
                                <td style="min-width: 110px;">
                                    <input type="number" name="items[{{ $index }}][tax_rate]" value="{{ $line['tax_rate'] ?? '0' }}" class="form-control form-control-sm line-tax-input" min="0" max="100" step="0.01">
                                </td>
                                <td style="min-width: 120px;">
                                    <span class="line-total-value fw-semibold">0,00</span>
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
                        <span class="fw-semibold" id="documentSubtotalPreview">0,00 EUR</span>
                    </div>
                </div>
                <div class="col-12 col-md-4 col-lg-3">
                    <div class="d-flex justify-content-between">
                        <span class="text-body-tertiary">Total:</span>
                        <span class="fw-bold" id="documentGrandTotalPreview">0,00 EUR</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2 justify-content-end">
        <a href="{{ route('admin.sales-documents.index') }}" class="btn btn-phoenix-secondary">Cancelar</a>
        <button type="submit" class="btn btn-primary">{{ $isEdit ? 'Guardar alteracoes' : 'Guardar documento' }}</button>
    </div>
</form>

<template id="sales-document-line-template">
    <tr data-line-row>
        <td class="ps-3" style="min-width: 220px;">
            <select name="items[__INDEX__][article_id]" class="form-select form-select-sm line-article-select">
                <option value="">Sem artigo (linha manual)</option>
                @foreach ($articles as $article)
                    <option
                        value="{{ $article->id }}"
                        data-description="{{ $article->designation }}"
                        data-unit-id="{{ $article->unit?->id ?? '' }}"
                        data-unit-name="{{ $article->unit?->code ?? $article->unit?->name ?? '' }}"
                        data-price="{{ number_format((float) ($article->sale_price ?? 0), 4, '.', '') }}"
                    >
                        {{ $article->code }} - {{ $article->designation }}
                    </option>
                @endforeach
            </select>
        </td>
        <td style="min-width: 260px;">
            <input type="text" name="items[__INDEX__][description]" class="form-control form-control-sm" maxlength="1000">
        </td>
        <td style="min-width: 170px;">
            <select name="items[__INDEX__][unit_id]" class="form-select form-select-sm line-unit-select">
                <option value="">Sem unidade</option>
                @foreach ($units as $unit)
                    <option value="{{ $unit->id }}">{{ $unit->code ?: $unit->name }} - {{ $unit->name }}</option>
                @endforeach
            </select>
            <input type="hidden" name="items[__INDEX__][unit_name_snapshot]" value="" class="line-unit-name-input">
        </td>
        <td style="min-width: 120px;">
            <input type="number" name="items[__INDEX__][quantity]" value="1.000" class="form-control form-control-sm line-quantity-input" min="0.001" step="0.001" required>
        </td>
        <td style="min-width: 130px;">
            <input type="number" name="items[__INDEX__][unit_price]" value="0.0000" class="form-control form-control-sm line-price-input" min="0" step="0.0001" required>
        </td>
        <td style="min-width: 110px;">
            <input type="number" name="items[__INDEX__][discount_percent]" value="0.00" class="form-control form-control-sm line-discount-input" min="0" max="100" step="0.01">
        </td>
        <td style="min-width: 110px;">
            <input type="number" name="items[__INDEX__][tax_rate]" value="0.00" class="form-control form-control-sm line-tax-input" min="0" max="100" step="0.01">
        </td>
        <td style="min-width: 120px;">
            <span class="line-total-value fw-semibold">0,00</span>
        </td>
        <td class="pe-3 text-end">
            <button type="button" class="btn btn-phoenix-danger btn-sm remove-line-btn">Remover</button>
        </td>
    </tr>
</template>

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const linesBody = document.getElementById('salesDocumentLinesBody');
            const addLineBtn = document.getElementById('addLineBtn');
            const template = document.getElementById('sales-document-line-template');
            const subtotalPreview = document.getElementById('documentSubtotalPreview');
            const grandTotalPreview = document.getElementById('documentGrandTotalPreview');
            const customerSelect = document.getElementById('customer_id');
            const contactSelect = document.getElementById('customer_contact_id');
            const contactsByCustomer = @json($contactsByCustomer);

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

            const syncUnitSnapshot = (line) => {
                const unitSelect = line.querySelector('.line-unit-select');
                const unitNameInput = line.querySelector('.line-unit-name-input');
                if (!unitSelect || !unitNameInput) {
                    return;
                }

                const selected = unitSelect.options[unitSelect.selectedIndex];
                if (!selected || !selected.value) {
                    unitNameInput.value = '';
                    return;
                }

                const label = String(selected.textContent ?? '').split(' - ')[0].trim();
                unitNameInput.value = label;
            };

            const updateLineTotal = (line) => {
                const quantityInput = line.querySelector('.line-quantity-input');
                const priceInput = line.querySelector('.line-price-input');
                const discountInput = line.querySelector('.line-discount-input');
                const taxInput = line.querySelector('.line-tax-input');
                const totalTarget = line.querySelector('.line-total-value');
                if (!quantityInput || !priceInput || !discountInput || !taxInput || !totalTarget) {
                    return { subtotal: 0, total: 0 };
                }

                const quantity = parseNumber(quantityInput.value);
                const unitPrice = parseNumber(priceInput.value);
                const discountPercent = Math.min(100, Math.max(0, parseNumber(discountInput.value)));
                const taxRate = Math.min(100, Math.max(0, parseNumber(taxInput.value)));

                const lineSubtotal = quantity * unitPrice;
                const lineDiscount = lineSubtotal * (discountPercent / 100);
                const taxableBase = lineSubtotal - lineDiscount;
                const lineTax = taxableBase * (taxRate / 100);
                const lineTotal = taxableBase + lineTax;
                totalTarget.textContent = formatMoney(lineTotal);

                return {
                    subtotal: lineSubtotal,
                    total: lineTotal
                };
            };

            const updateTotals = () => {
                let subtotal = 0;
                let total = 0;
                linesBody.querySelectorAll('[data-line-row]').forEach((line) => {
                    const lineAmounts = updateLineTotal(line);
                    subtotal += lineAmounts.subtotal;
                    total += lineAmounts.total;
                    syncUnitSnapshot(line);
                });

                if (subtotalPreview) {
                    subtotalPreview.textContent = formatMoney(subtotal) + ' EUR';
                }
                if (grandTotalPreview) {
                    grandTotalPreview.textContent = formatMoney(total) + ' EUR';
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
                    const unitSelect = line.querySelector('.line-unit-select');
                    const priceInput = line.querySelector('.line-price-input');

                    if (descriptionInput && descriptionInput.value.trim() === '' && selectedOption.dataset.description) {
                        descriptionInput.value = selectedOption.dataset.description;
                    }

                    if (unitSelect && selectedOption.dataset.unitId) {
                        unitSelect.value = selectedOption.dataset.unitId;
                    }

                    if (priceInput && (priceInput.value.trim() === '' || parseNumber(priceInput.value) === 0) && selectedOption.dataset.price) {
                        priceInput.value = selectedOption.dataset.price;
                    }

                    updateTotals();
                });
            };

            const bindLineEvents = (line) => {
                bindArticleSelect(line);

                line.querySelectorAll('.line-quantity-input, .line-price-input, .line-discount-input, .line-tax-input, .line-unit-select')
                    .forEach((input) => {
                        input.addEventListener('input', updateTotals);
                        input.addEventListener('change', updateTotals);
                    });

                const removeBtn = line.querySelector('.remove-line-btn');
                if (removeBtn) {
                    removeBtn.addEventListener('click', () => {
                        const rows = linesBody.querySelectorAll('[data-line-row]');
                        if (rows.length <= 1) {
                            return;
                        }

                        line.remove();
                        updateTotals();
                    });
                }
            };

            const addLine = () => {
                const html = template.innerHTML.replaceAll('__INDEX__', String(lineIndex++));
                const wrapper = document.createElement('tbody');
                wrapper.innerHTML = html.trim();
                const line = wrapper.querySelector('[data-line-row]');
                if (!line) {
                    return;
                }

                linesBody.appendChild(line);
                bindLineEvents(line);
                updateTotals();
            };

            const rebuildContacts = () => {
                if (!customerSelect || !contactSelect) {
                    return;
                }

                const selectedCustomer = customerSelect.value;
                const previous = contactSelect.value;
                const contacts = contactsByCustomer[selectedCustomer] ?? [];

                contactSelect.innerHTML = '<option value="">Sem contacto</option>';
                contacts.forEach((contact) => {
                    const option = document.createElement('option');
                    option.value = String(contact.id);
                    option.textContent = contact.email ? `${contact.name} (${contact.email})` : contact.name;
                    if (String(contact.id) === String(previous)) {
                        option.selected = true;
                    }
                    contactSelect.appendChild(option);
                });
            };

            addLineBtn.addEventListener('click', addLine);
            linesBody.querySelectorAll('[data-line-row]').forEach((line) => bindLineEvents(line));
            if (customerSelect) {
                customerSelect.addEventListener('change', rebuildContacts);
            }

            rebuildContacts();
            updateTotals();
        });
    </script>
@endpush

