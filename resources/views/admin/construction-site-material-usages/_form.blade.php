@php
    $usageDateValue = old('usage_date', isset($usage) ? optional($usage->usage_date)->format('Y-m-d') : now()->toDateString());
    $notesValue = old('notes', isset($usage) ? $usage->notes : null);
    $articleLookup = $articleOptions->keyBy('id');

    $rawItems = old('items');
    if (!is_array($rawItems)) {
        if (isset($usage)) {
            $rawItems = $usage->items
                ->map(fn ($item) => [
                    'article_id' => (int) $item->article_id,
                    'quantity' => number_format((float) $item->quantity, 3, '.', ''),
                    'unit_cost' => $item->unit_cost !== null ? number_format((float) $item->unit_cost, 4, '.', '') : null,
                    'notes' => $item->notes,
                ])
                ->all();
        } else {
            $rawItems = [[
                'article_id' => null,
                'quantity' => '1.000',
                'unit_cost' => null,
                'notes' => null,
            ]];
        }
    }

    if ($rawItems === []) {
        $rawItems = [[
            'article_id' => null,
            'quantity' => '1.000',
            'unit_cost' => null,
            'notes' => null,
        ]];
    }

    $items = array_values($rawItems);
@endphp

<form method="POST" action="{{ $formAction }}" class="row g-4">
    @csrf
    @if (strtoupper($formMethod) === 'PATCH')
        @method('PATCH')
    @endif

    <div class="col-12">
        <div class="card">
            <div class="card-header bg-body-tertiary">
                <h5 class="mb-0">Dados do consumo</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12 col-md-4">
                        <label class="form-label" for="usage_date">Data do consumo</label>
                        <input type="date" id="usage_date" name="usage_date" value="{{ $usageDateValue }}" class="form-control @error('usage_date') is-invalid @enderror" required>
                        @error('usage_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="notes">Notas</label>
                        <textarea id="notes" name="notes" rows="3" class="form-control @error('notes') is-invalid @enderror" maxlength="5000">{{ $notesValue }}</textarea>
                        @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card">
            <div class="card-header bg-body-tertiary d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Linhas de consumo</h5>
                <button type="button" id="addMaterialUsageLineBtn" class="btn btn-phoenix-secondary btn-sm">Adicionar linha</button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm fs-9 mb-0 align-middle">
                        <thead class="bg-body-tertiary">
                            <tr>
                                <th class="ps-3">#</th>
                                <th>Artigo</th>
                                <th>Codigo</th>
                                <th>Descricao</th>
                                <th>Unidade</th>
                                <th>Stock</th>
                                <th>Quantidade</th>
                                <th>Custo unit.</th>
                                <th>Notas</th>
                                <th class="pe-3 text-end">Acoes</th>
                            </tr>
                        </thead>
                        <tbody id="materialUsageItemsTbody">
                            @foreach ($items as $index => $item)
                                @php
                                    $selectedArticleId = isset($item['article_id']) ? (int) $item['article_id'] : 0;
                                    $selectedArticle = $selectedArticleId > 0 ? $articleLookup->get($selectedArticleId) : null;
                                @endphp
                                <tr class="material-usage-item-row">
                                    <td class="ps-3 row-index">{{ $index + 1 }}</td>
                                    <td style="min-width: 230px;">
                                        <select name="items[{{ $index }}][article_id]" class="form-select form-select-sm article-select @error('items.'.$index.'.article_id') is-invalid @enderror" required>
                                            <option value="">Selecionar artigo</option>
                                            @foreach ($articleOptions as $article)
                                                <option
                                                    value="{{ $article->id }}"
                                                    data-code="{{ $article->code }}"
                                                    data-description="{{ $article->designation }}"
                                                    data-unit="{{ $article->unit?->name }}"
                                                    data-stock="{{ number_format((float) ($article->stock_quantity ?? 0), 3, '.', '') }}"
                                                    data-cost="{{ $article->cost_price !== null ? number_format((float) $article->cost_price, 4, '.', '') : '' }}"
                                                    @selected($selectedArticleId === (int) $article->id)
                                                >
                                                    {{ $article->code }} - {{ $article->designation }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('items.'.$index.'.article_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                    </td>
                                    <td class="article-code">{{ $selectedArticle?->code ?? '-' }}</td>
                                    <td class="article-description">{{ $selectedArticle?->designation ?? '-' }}</td>
                                    <td class="article-unit">{{ $selectedArticle?->unit?->name ?? '-' }}</td>
                                    <td class="article-stock">{{ $selectedArticle ? number_format((float) ($selectedArticle->stock_quantity ?? 0), 3, ',', '.') : '-' }}</td>
                                    <td style="min-width: 120px;">
                                        <input type="number" step="0.001" min="0.001" name="items[{{ $index }}][quantity]" value="{{ old('items.'.$index.'.quantity', $item['quantity'] ?? '1.000') }}" class="form-control form-control-sm @error('items.'.$index.'.quantity') is-invalid @enderror" required>
                                        @error('items.'.$index.'.quantity')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                    </td>
                                    <td style="min-width: 140px;">
                                        <input type="number" step="0.0001" min="0" name="items[{{ $index }}][unit_cost]" value="{{ old('items.'.$index.'.unit_cost', $item['unit_cost'] ?? null) }}" class="form-control form-control-sm @error('items.'.$index.'.unit_cost') is-invalid @enderror">
                                        @error('items.'.$index.'.unit_cost')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                    </td>
                                    <td style="min-width: 180px;">
                                        <input type="text" name="items[{{ $index }}][notes]" value="{{ old('items.'.$index.'.notes', $item['notes'] ?? null) }}" class="form-control form-control-sm @error('items.'.$index.'.notes') is-invalid @enderror" maxlength="1000">
                                        @error('items.'.$index.'.notes')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                    </td>
                                    <td class="pe-3 text-end">
                                        <button type="button" class="btn btn-phoenix-danger btn-sm remove-material-usage-line-btn">Remover</button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 d-flex justify-content-end gap-2">
        <a href="{{ $cancelUrl }}" class="btn btn-phoenix-secondary">Cancelar</a>
        <button type="submit" class="btn btn-primary">{{ $submitLabel }}</button>
    </div>
</form>

<template id="materialUsageItemRowTemplate">
    <tr class="material-usage-item-row">
        <td class="ps-3 row-index">__ROW__</td>
        <td style="min-width: 230px;">
            <select name="items[__INDEX__][article_id]" class="form-select form-select-sm article-select" required>
                <option value="">Selecionar artigo</option>
                @foreach ($articleOptions as $article)
                    <option
                        value="{{ $article->id }}"
                        data-code="{{ $article->code }}"
                        data-description="{{ $article->designation }}"
                        data-unit="{{ $article->unit?->name }}"
                        data-stock="{{ number_format((float) ($article->stock_quantity ?? 0), 3, '.', '') }}"
                        data-cost="{{ $article->cost_price !== null ? number_format((float) $article->cost_price, 4, '.', '') : '' }}"
                    >
                        {{ $article->code }} - {{ $article->designation }}
                    </option>
                @endforeach
            </select>
        </td>
        <td class="article-code">-</td>
        <td class="article-description">-</td>
        <td class="article-unit">-</td>
        <td class="article-stock">-</td>
        <td style="min-width: 120px;">
            <input type="number" step="0.001" min="0.001" name="items[__INDEX__][quantity]" value="1.000" class="form-control form-control-sm" required>
        </td>
        <td style="min-width: 140px;">
            <input type="number" step="0.0001" min="0" name="items[__INDEX__][unit_cost]" class="form-control form-control-sm">
        </td>
        <td style="min-width: 180px;">
            <input type="text" name="items[__INDEX__][notes]" class="form-control form-control-sm" maxlength="1000">
        </td>
        <td class="pe-3 text-end">
            <button type="button" class="btn btn-phoenix-danger btn-sm remove-material-usage-line-btn">Remover</button>
        </td>
    </tr>
</template>

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const tbody = document.getElementById('materialUsageItemsTbody');
            const addButton = document.getElementById('addMaterialUsageLineBtn');
            const template = document.getElementById('materialUsageItemRowTemplate');

            if (!tbody || !addButton || !template) {
                return;
            }

            function updateRowIndex() {
                const rows = tbody.querySelectorAll('.material-usage-item-row');
                rows.forEach((row, idx) => {
                    const indexCell = row.querySelector('.row-index');
                    if (indexCell) {
                        indexCell.textContent = String(idx + 1);
                    }
                });
            }

            function updateArticleSnapshot(row) {
                const select = row.querySelector('.article-select');
                if (!select) {
                    return;
                }

                const selectedOption = select.options[select.selectedIndex];
                const code = selectedOption ? selectedOption.getAttribute('data-code') : null;
                const description = selectedOption ? selectedOption.getAttribute('data-description') : null;
                const unit = selectedOption ? selectedOption.getAttribute('data-unit') : null;
                const stock = selectedOption ? selectedOption.getAttribute('data-stock') : null;
                const cost = selectedOption ? selectedOption.getAttribute('data-cost') : null;

                const codeCell = row.querySelector('.article-code');
                const descriptionCell = row.querySelector('.article-description');
                const unitCell = row.querySelector('.article-unit');
                const stockCell = row.querySelector('.article-stock');
                const costInput = row.querySelector('input[name$="[unit_cost]"]');

                if (codeCell) {
                    codeCell.textContent = code && code.trim() !== '' ? code : '-';
                }

                if (descriptionCell) {
                    descriptionCell.textContent = description && description.trim() !== '' ? description : '-';
                }

                if (unitCell) {
                    unitCell.textContent = unit && unit.trim() !== '' ? unit : '-';
                }

                if (stockCell) {
                    const parsedStock = stock && stock.trim() !== '' ? Number.parseFloat(stock) : NaN;
                    stockCell.textContent = Number.isFinite(parsedStock)
                        ? parsedStock.toLocaleString('pt-PT', { minimumFractionDigits: 3, maximumFractionDigits: 3 })
                        : '-';
                }

                if (costInput && (costInput.value === '' || costInput.value === null) && cost && cost.trim() !== '') {
                    costInput.value = cost;
                }
            }

            function bindRowEvents(row) {
                const removeButton = row.querySelector('.remove-material-usage-line-btn');
                if (removeButton) {
                    removeButton.addEventListener('click', function () {
                        const rows = tbody.querySelectorAll('.material-usage-item-row');
                        if (rows.length <= 1) {
                            return;
                        }

                        row.remove();
                        updateRowIndex();
                    });
                }

                const articleSelect = row.querySelector('.article-select');
                if (articleSelect) {
                    articleSelect.addEventListener('change', function () {
                        updateArticleSnapshot(row);
                    });
                }
            }

            function addRow() {
                const nextIndex = tbody.querySelectorAll('.material-usage-item-row').length;
                const html = template.innerHTML
                    .replaceAll('__INDEX__', String(nextIndex))
                    .replaceAll('__ROW__', String(nextIndex + 1));
                tbody.insertAdjacentHTML('beforeend', html);
                const row = tbody.querySelectorAll('.material-usage-item-row')[nextIndex];
                if (!row) {
                    return;
                }

                bindRowEvents(row);
                updateArticleSnapshot(row);
                updateRowIndex();
            }

            tbody.querySelectorAll('.material-usage-item-row').forEach(function (row) {
                bindRowEvents(row);
                updateArticleSnapshot(row);
            });

            addButton.addEventListener('click', addRow);
            updateRowIndex();
        });
    </script>
@endpush
