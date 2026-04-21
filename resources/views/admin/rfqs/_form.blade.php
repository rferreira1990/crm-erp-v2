@php
    $rfq = $rfq ?? null;
    $isEdit = isset($rfq);

    $selectedAssignedUserId = old('assigned_user_id', $rfq->assigned_user_id ?? '');
    $selectedSupplierIds = collect(old('supplier_ids', $isEdit ? $rfq->invitedSuppliers->pluck('supplier_id')->all() : []))
        ->map(fn ($id) => (int) $id)
        ->all();

    $formItems = old('items');
    if (! is_array($formItems)) {
        $formItems = $isEdit
            ? $rfq->items->map(fn ($item) => [
                'line_order' => $item->line_order,
                'line_type' => $item->line_type,
                'article_id' => $item->article_id,
                'article_code' => $item->article_code,
                'description' => $item->description,
                'unit_name' => $item->unit_name,
                'quantity' => $item->quantity,
                'internal_notes' => $item->internal_notes,
            ])->values()->all()
            : [];
    }

    if ($formItems === []) {
        $formItems[] = [
            'line_order' => 1,
            'line_type' => \App\Models\SupplierQuoteRequestItem::TYPE_ARTICLE,
            'article_id' => null,
            'article_code' => null,
            'description' => null,
            'unit_name' => null,
            'quantity' => 1,
            'internal_notes' => null,
        ];
    }
@endphp

<div class="card mb-4">
    <div class="card-header bg-body-tertiary">
        <h5 class="mb-0">Cabecalho do pedido</h5>
    </div>
    <div class="card-body">
        <div class="row g-3">
            @if ($isEdit)
                <div class="col-12 col-md-3">
                    <label class="form-label">Numero</label>
                    <input type="text" class="form-control" value="{{ $rfq->number }}" readonly>
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label">Estado</label>
                    <input type="text" class="form-control" value="{{ $rfq->statusLabel() }}" readonly>
                </div>
            @endif
            <div class="col-12 col-md-{{ $isEdit ? '3' : '4' }}">
                <label for="issue_date" class="form-label">Data emissao</label>
                <input type="date" id="issue_date" name="issue_date" value="{{ old('issue_date', optional($rfq->issue_date ?? null)->format('Y-m-d') ?? ($defaults['issue_date'] ?? now()->format('Y-m-d'))) }}" class="form-control @error('issue_date') is-invalid @enderror" required>
                @error('issue_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12 col-md-3">
                <label for="response_deadline" class="form-label">Prazo resposta</label>
                <input type="date" id="response_deadline" name="response_deadline" value="{{ old('response_deadline', optional($rfq->response_deadline ?? null)->format('Y-m-d')) }}" class="form-control @error('response_deadline') is-invalid @enderror">
                @error('response_deadline')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12 col-md-6">
                <label for="title" class="form-label">Titulo</label>
                <input type="text" id="title" name="title" value="{{ old('title', $rfq->title ?? '') }}" class="form-control @error('title') is-invalid @enderror" maxlength="190">
                @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12 col-md-3">
                <label for="assigned_user_id" class="form-label">Responsavel</label>
                <select id="assigned_user_id" name="assigned_user_id" class="form-select @error('assigned_user_id') is-invalid @enderror">
                    <option value="">Sem responsavel</option>
                    @foreach (($assignedUserOptions ?? []) as $assignedUserOption)
                        <option value="{{ $assignedUserOption->id }}" @selected((string) $selectedAssignedUserId === (string) $assignedUserOption->id)>
                            {{ $assignedUserOption->name }}
                        </option>
                    @endforeach
                </select>
                @error('assigned_user_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12 col-md-3">
                <label for="estimated_total" class="form-label">Total estimado</label>
                <input type="number" id="estimated_total" name="estimated_total" step="0.01" min="0" value="{{ old('estimated_total', $rfq->estimated_total ?? '') }}" class="form-control @error('estimated_total') is-invalid @enderror">
                @error('estimated_total')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12 col-md-3">
                <div class="form-check mt-4">
                    <input type="hidden" name="is_active" value="0">
                    <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" @checked(old('is_active', $isEdit ? $rfq->is_active : ($defaults['is_active'] ?? true)))>
                    <label class="form-check-label" for="is_active">Pedido ativo</label>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header bg-body-tertiary d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Linhas do pedido</h5>
        <button type="button" class="btn btn-phoenix-secondary btn-sm" id="add-rfq-item-row">Adicionar linha</button>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm fs-9 mb-0">
                <thead class="bg-body-tertiary">
                    <tr>
                        <th class="ps-3" style="width: 55px;">#</th>
                        <th style="min-width: 140px;">Tipo</th>
                        <th style="min-width: 260px;">Artigo</th>
                        <th style="min-width: 260px;">Descricao</th>
                        <th style="min-width: 120px;">Unidade</th>
                        <th style="min-width: 120px;">Qtd.</th>
                        <th style="min-width: 220px;">Notas internas</th>
                        <th class="pe-3 text-end" style="width: 90px;">Acao</th>
                    </tr>
                </thead>
                <tbody id="rfq-items-body">
                    @foreach ($formItems as $rowIndex => $item)
                        <tr class="rfq-item-row">
                            <td class="ps-3 align-middle">
                                <span class="row-index">{{ $loop->iteration }}</span>
                                <input type="hidden" class="line-order-input" name="items[{{ $rowIndex }}][line_order]" value="{{ old("items.$rowIndex.line_order", $item['line_order'] ?? $loop->iteration) }}">
                            </td>
                            <td>
                                <select name="items[{{ $rowIndex }}][line_type]" class="form-select form-select-sm line-type-select @error("items.$rowIndex.line_type") is-invalid @enderror">
                                    @foreach (($lineTypeOptions ?? []) as $lineTypeKey => $lineTypeLabel)
                                        <option value="{{ $lineTypeKey }}" @selected((string) old("items.$rowIndex.line_type", $item['line_type'] ?? '') === (string) $lineTypeKey)>{{ $lineTypeLabel }}</option>
                                    @endforeach
                                </select>
                                @error("items.$rowIndex.line_type")<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </td>
                            <td>
                                <select name="items[{{ $rowIndex }}][article_id]" class="form-select form-select-sm article-select @error("items.$rowIndex.article_id") is-invalid @enderror">
                                    <option value="">Sem artigo</option>
                                    @foreach (($articleOptions ?? []) as $articleOption)
                                        <option value="{{ $articleOption->id }}" data-code="{{ $articleOption->code }}" data-designation="{{ $articleOption->designation }}" @selected((string) old("items.$rowIndex.article_id", $item['article_id'] ?? '') === (string) $articleOption->id)>
                                            {{ $articleOption->code }} - {{ $articleOption->designation }}
                                        </option>
                                    @endforeach
                                </select>
                                @error("items.$rowIndex.article_id")<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                <input type="hidden" name="items[{{ $rowIndex }}][article_code]" value="{{ old("items.$rowIndex.article_code", $item['article_code'] ?? '') }}" class="article-code-input">
                            </td>
                            <td>
                                <textarea name="items[{{ $rowIndex }}][description]" rows="2" class="form-control form-control-sm description-input @error("items.$rowIndex.description") is-invalid @enderror">{{ old("items.$rowIndex.description", $item['description'] ?? '') }}</textarea>
                                @error("items.$rowIndex.description")<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </td>
                            <td>
                                <input type="text" name="items[{{ $rowIndex }}][unit_name]" value="{{ old("items.$rowIndex.unit_name", $item['unit_name'] ?? '') }}" class="form-control form-control-sm @error("items.$rowIndex.unit_name") is-invalid @enderror">
                                @error("items.$rowIndex.unit_name")<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </td>
                            <td>
                                <input type="number" min="0" step="0.001" name="items[{{ $rowIndex }}][quantity]" value="{{ old("items.$rowIndex.quantity", $item['quantity'] ?? 1) }}" class="form-control form-control-sm @error("items.$rowIndex.quantity") is-invalid @enderror">
                                @error("items.$rowIndex.quantity")<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </td>
                            <td>
                                <textarea name="items[{{ $rowIndex }}][internal_notes]" rows="2" class="form-control form-control-sm @error("items.$rowIndex.internal_notes") is-invalid @enderror">{{ old("items.$rowIndex.internal_notes", $item['internal_notes'] ?? '') }}</textarea>
                                @error("items.$rowIndex.internal_notes")<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
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

<div class="card mb-4">
    <div class="card-header bg-body-tertiary">
        <h5 class="mb-0">Fornecedores convidados</h5>
    </div>
    <div class="card-body">
        @error('supplier_ids')
            <div class="alert alert-danger py-2">{{ $message }}</div>
        @enderror
        <div class="row g-3">
            @foreach (($suppliers ?? []) as $supplier)
                <div class="col-12 col-md-6 col-xl-4">
                    <div class="border rounded p-3 h-100">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" value="{{ $supplier->id }}" id="supplier_{{ $supplier->id }}" name="supplier_ids[]" @checked(in_array((int) $supplier->id, $selectedSupplierIds, true))>
                            <label class="form-check-label fw-semibold" for="supplier_{{ $supplier->id }}">
                                {{ $supplier->name }}
                            </label>
                        </div>
                        <div class="text-body-tertiary fs-9 mt-1">{{ $supplier->email ?: 'Sem email' }}</div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header bg-body-tertiary">
        <h5 class="mb-0">Notas</h5>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-12 col-md-6">
                <label for="supplier_notes" class="form-label">Notas para fornecedor</label>
                <textarea id="supplier_notes" name="supplier_notes" rows="4" class="form-control @error('supplier_notes') is-invalid @enderror">{{ old('supplier_notes', $rfq->supplier_notes ?? '') }}</textarea>
                @error('supplier_notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12 col-md-6">
                <label for="internal_notes" class="form-label">Notas internas</label>
                <textarea id="internal_notes" name="internal_notes" rows="4" class="form-control @error('internal_notes') is-invalid @enderror">{{ old('internal_notes', $rfq->internal_notes ?? '') }}</textarea>
                @error('internal_notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>
</div>

<div class="d-flex gap-2 justify-content-end">
    <a href="{{ route('admin.rfqs.index') }}" class="btn btn-phoenix-secondary">Cancelar</a>
    <button type="submit" class="btn btn-primary">{{ $isEdit ? 'Guardar alteracoes' : 'Criar pedido de cotacao' }}</button>
</div>

<template id="rfq-item-row-template">
    <tr class="rfq-item-row">
        <td class="ps-3 align-middle">
            <span class="row-index">1</span>
            <input type="hidden" class="line-order-input" name="items[__INDEX__][line_order]" value="1">
        </td>
        <td>
            <select name="items[__INDEX__][line_type]" class="form-select form-select-sm line-type-select">
                @foreach (($lineTypeOptions ?? []) as $lineTypeKey => $lineTypeLabel)
                    <option value="{{ $lineTypeKey }}">{{ $lineTypeLabel }}</option>
                @endforeach
            </select>
        </td>
        <td>
            <select name="items[__INDEX__][article_id]" class="form-select form-select-sm article-select">
                <option value="">Sem artigo</option>
                @foreach (($articleOptions ?? []) as $articleOption)
                    <option value="{{ $articleOption->id }}" data-code="{{ $articleOption->code }}" data-designation="{{ $articleOption->designation }}">
                        {{ $articleOption->code }} - {{ $articleOption->designation }}
                    </option>
                @endforeach
            </select>
            <input type="hidden" name="items[__INDEX__][article_code]" class="article-code-input">
        </td>
        <td><textarea name="items[__INDEX__][description]" rows="2" class="form-control form-control-sm description-input"></textarea></td>
        <td><input type="text" name="items[__INDEX__][unit_name]" class="form-control form-control-sm"></td>
        <td><input type="number" min="0" step="0.001" name="items[__INDEX__][quantity]" value="1" class="form-control form-control-sm"></td>
        <td><textarea name="items[__INDEX__][internal_notes]" rows="2" class="form-control form-control-sm"></textarea></td>
        <td class="pe-3 text-end align-middle">
            <button type="button" class="btn btn-phoenix-danger btn-sm remove-row-btn">Remover</button>
        </td>
    </tr>
</template>

@once
    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const tbody = document.getElementById('rfq-items-body');
                const addButton = document.getElementById('add-rfq-item-row');
                const template = document.getElementById('rfq-item-row-template');

                if (!tbody || !addButton || !template) {
                    return;
                }

                const syncRowOrder = () => {
                    Array.from(tbody.querySelectorAll('tr.rfq-item-row')).forEach((row, idx) => {
                        const position = idx + 1;
                        const indexLabel = row.querySelector('.row-index');
                        const lineOrderInput = row.querySelector('.line-order-input');
                        if (indexLabel) indexLabel.textContent = position;
                        if (lineOrderInput) lineOrderInput.value = position;
                    });
                };

                const syncLineType = (row) => {
                    const typeSelect = row.querySelector('.line-type-select');
                    const articleSelect = row.querySelector('.article-select');
                    const quantityInput = row.querySelector('input[name$="[quantity]"]');

                    if (!typeSelect) {
                        return;
                    }

                    const type = typeSelect.value;
                    const isArticle = type === 'article';
                    const isQuantified = type === 'article' || type === 'text';

                    if (articleSelect) {
                        articleSelect.disabled = !isArticle;
                        if (!isArticle) {
                            articleSelect.value = '';
                        }
                    }

                    if (quantityInput) {
                        quantityInput.disabled = !isQuantified;
                        if (!isQuantified) {
                            quantityInput.value = 1;
                        }
                    }

                    row.classList.toggle('table-warning', type === 'section');
                    row.classList.toggle('table-light', type === 'note');
                };

                const syncArticleData = (row) => {
                    const typeSelect = row.querySelector('.line-type-select');
                    const articleSelect = row.querySelector('.article-select');
                    if (!typeSelect || !articleSelect || typeSelect.value !== 'article') {
                        return;
                    }

                    const selected = articleSelect.options[articleSelect.selectedIndex];
                    const descriptionInput = row.querySelector('.description-input');
                    const articleCodeInput = row.querySelector('.article-code-input');

                    if (selected && selected.value) {
                        if (descriptionInput && descriptionInput.value.trim() === '') {
                            descriptionInput.value = selected.dataset.designation || '';
                        }
                        if (articleCodeInput) {
                            articleCodeInput.value = selected.dataset.code || '';
                        }
                    } else if (articleCodeInput) {
                        articleCodeInput.value = '';
                    }
                };

                const bindRowEvents = (row) => {
                    const removeButton = row.querySelector('.remove-row-btn');
                    if (removeButton) {
                        removeButton.addEventListener('click', function () {
                            if (tbody.querySelectorAll('tr.rfq-item-row').length <= 1) {
                                return;
                            }

                            row.remove();
                            syncRowOrder();
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
                        });
                    }

                    syncLineType(row);
                };

                addButton.addEventListener('click', function () {
                    const nextIndex = tbody.querySelectorAll('tr.rfq-item-row').length;
                    const html = template.innerHTML.replaceAll('__INDEX__', nextIndex);
                    tbody.insertAdjacentHTML('beforeend', html);
                    const newRow = tbody.querySelectorAll('tr.rfq-item-row')[nextIndex];
                    bindRowEvents(newRow);
                    syncRowOrder();
                });

                Array.from(tbody.querySelectorAll('tr.rfq-item-row')).forEach((row) => bindRowEvents(row));
                syncRowOrder();
            });
        </script>
    @endpush
@endonce

