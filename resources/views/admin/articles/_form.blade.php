@php
    $isEdit = isset($article);
    $selectedCategoryId = old('category_id', $article->category_id ?? ($defaults['category_id'] ?? ''));
    $selectedUnitId = old('unit_id', $article->unit_id ?? ($defaults['unit_id'] ?? ''));
    $defaultVatRateId = null;

    if (! $isEdit && isset($vatRateOptions)) {
        $defaultVatRate = collect($vatRateOptions)->first(function ($rate): bool {
            return ! $rate->is_exempt
                && (float) $rate->rate === 23.0;
        });

        $defaultVatRateId = $defaultVatRate?->id;
    }

    $selectedVatRateId = old('vat_rate_id', $article->vat_rate_id ?? $defaultVatRateId ?? '');
@endphp

<div class="card theme-wizard mb-5" id="articleWizard">
    <div class="card-header bg-body-highlight pt-3 pb-2 border-bottom-0">
        <ul class="nav justify-content-between nav-wizard nav-wizard-success" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active fw-semibold" type="button" data-step-index="0" aria-selected="true" aria-controls="article-wizard-tab1" role="tab">
                    <div class="text-center d-inline-block">
                        <span class="nav-item-circle-parent"><span class="nav-item-circle"><span class="fas fa-tag"></span></span></span>
                        <span class="d-none d-md-block mt-1 fs-9">Identificacao</span>
                    </div>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link fw-semibold" type="button" data-step-index="1" aria-selected="false" tabindex="-1" aria-controls="article-wizard-tab2" role="tab">
                    <div class="text-center d-inline-block">
                        <span class="nav-item-circle-parent"><span class="nav-item-circle"><span class="fas fa-sitemap"></span></span></span>
                        <span class="d-none d-md-block mt-1 fs-9">Classificacao</span>
                    </div>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link fw-semibold" type="button" data-step-index="2" aria-selected="false" tabindex="-1" aria-controls="article-wizard-tab3" role="tab">
                    <div class="text-center d-inline-block">
                        <span class="nav-item-circle-parent"><span class="nav-item-circle"><span class="fas fa-euro-sign"></span></span></span>
                        <span class="d-none d-md-block mt-1 fs-9">Precos e Stock</span>
                    </div>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link fw-semibold" type="button" data-step-index="3" aria-selected="false" tabindex="-1" aria-controls="article-wizard-tab4" role="tab">
                    <div class="text-center d-inline-block">
                        <span class="nav-item-circle-parent"><span class="nav-item-circle"><span class="fas fa-file-alt"></span></span></span>
                        <span class="d-none d-md-block mt-1 fs-9">Notas e Ficheiros</span>
                    </div>
                </button>
            </li>
        </ul>
    </div>

    <div class="card-body pt-4 pb-0">
        <div class="tab-content">
            <div class="tab-pane active" role="tabpanel" id="article-wizard-tab1" aria-labelledby="article-wizard-tab1">
                <div class="row g-3">
                    <div class="col-12 col-md-8">
                        <label for="designation" class="form-label">Descricao</label>
                        <input
                            type="text"
                            id="designation"
                            name="designation"
                            value="{{ old('designation', $article->designation ?? '') }}"
                            class="form-control @error('designation') is-invalid @enderror"
                            maxlength="190"
                            required
                        >
                        @error('designation')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12 col-md-4">
                        <label for="abbreviation" class="form-label">Abreviatura</label>
                        <input type="text" id="abbreviation" name="abbreviation" value="{{ old('abbreviation', $article->abbreviation ?? '') }}" class="form-control @error('abbreviation') is-invalid @enderror" maxlength="50">
                        @error('abbreviation')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12 col-md-3">
                        <label for="ean" class="form-label">EAN</label>
                        <input type="text" id="ean" name="ean" value="{{ old('ean', $article->ean ?? '') }}" class="form-control @error('ean') is-invalid @enderror" maxlength="20" placeholder="8 a 14 digitos">
                        @error('ean')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12 col-md-3">
                        <label for="supplier_id" class="form-label">Fornecedor (ID)</label>
                        <input type="number" id="supplier_id" name="supplier_id" value="{{ old('supplier_id', $article->supplier_id ?? '') }}" class="form-control @error('supplier_id') is-invalid @enderror" min="1">
                        @error('supplier_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12 col-md-6">
                        <label for="supplier_reference" class="form-label">Referencia fornecedor</label>
                        <input type="text" id="supplier_reference" name="supplier_reference" value="{{ old('supplier_reference', $article->supplier_reference ?? '') }}" class="form-control @error('supplier_reference') is-invalid @enderror" maxlength="120">
                        @error('supplier_reference')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>

            <div class="tab-pane" role="tabpanel" id="article-wizard-tab2" aria-labelledby="article-wizard-tab2">
                <div class="row g-3">
                    <div class="col-12 col-md-4">
                        <label for="product_family_id" class="form-label">Familia</label>
                        <select id="product_family_id" name="product_family_id" class="form-select @error('product_family_id') is-invalid @enderror" required>
                            <option value="">Selecionar familia</option>
                            @foreach (($familyOptions ?? []) as $familyOption)
                                <option value="{{ $familyOption['id'] }}" @selected((string) old('product_family_id', $article->product_family_id ?? '') === (string) $familyOption['id'])>
                                    {{ $familyOption['label'] }}
                                </option>
                            @endforeach
                        </select>
                        @error('product_family_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12 col-md-4">
                        <label for="category_id" class="form-label">Categoria</label>
                        <select id="category_id" name="category_id" class="form-select @error('category_id') is-invalid @enderror" required>
                            <option value="">Selecionar categoria</option>
                            @foreach (($categoryOptions ?? []) as $categoryOption)
                                <option value="{{ $categoryOption->id }}" @selected((string) $selectedCategoryId === (string) $categoryOption->id)>{{ $categoryOption->name }}</option>
                            @endforeach
                        </select>
                        @error('category_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12 col-md-4">
                        <label for="unit_id" class="form-label">Unidade</label>
                        <select id="unit_id" name="unit_id" class="form-select @error('unit_id') is-invalid @enderror" required>
                            <option value="">Selecionar unidade</option>
                            @foreach (($unitOptions ?? []) as $unitOption)
                                <option value="{{ $unitOption->id }}" @selected((string) $selectedUnitId === (string) $unitOption->id)>{{ $unitOption->code }} - {{ $unitOption->name }}</option>
                            @endforeach
                        </select>
                        @error('unit_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12 col-md-4">
                        <label for="brand_id" class="form-label">Marca</label>
                        <select id="brand_id" name="brand_id" class="form-select @error('brand_id') is-invalid @enderror">
                            <option value="">Sem marca</option>
                            @foreach (($brandOptions ?? []) as $brandOption)
                                <option value="{{ $brandOption->id }}" @selected((string) old('brand_id', $article->brand_id ?? '') === (string) $brandOption->id)>{{ $brandOption->name }}</option>
                            @endforeach
                        </select>
                        @error('brand_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12 col-md-4">
                        <label for="vat_rate_id" class="form-label">Taxa de IVA</label>
                        <select id="vat_rate_id" name="vat_rate_id" class="form-select @error('vat_rate_id') is-invalid @enderror" required>
                            <option value="">Selecionar taxa</option>
                            @foreach (($vatRateOptions ?? []) as $vatRateOption)
                                <option value="{{ $vatRateOption->id }}" data-is-exempt="{{ $vatRateOption->is_exempt ? '1' : '0' }}" @selected((string) $selectedVatRateId === (string) $vatRateOption->id)>
                                    {{ $vatRateOption->name }} ({{ number_format((float) $vatRateOption->rate, 2) }}%)
                                </option>
                            @endforeach
                        </select>
                        @error('vat_rate_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12 col-md-4">
                        <label for="vat_exemption_reason_id" class="form-label">Motivo de isencao IVA</label>
                        <select id="vat_exemption_reason_id" name="vat_exemption_reason_id" class="form-select @error('vat_exemption_reason_id') is-invalid @enderror">
                            <option value="">Sem motivo</option>
                            @foreach (($vatExemptionReasonOptions ?? []) as $reasonOption)
                                <option value="{{ $reasonOption->id }}" @selected((string) old('vat_exemption_reason_id', $article->vat_exemption_reason_id ?? '') === (string) $reasonOption->id)>{{ $reasonOption->code }} - {{ $reasonOption->name }}</option>
                            @endforeach
                        </select>
                        <small id="vatExemptionHelper" class="text-body-tertiary"></small>
                        @error('vat_exemption_reason_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>

            <div class="tab-pane" role="tabpanel" id="article-wizard-tab3" aria-labelledby="article-wizard-tab3">
                <div class="row g-3">
                    <div class="col-12 col-md-3">
                        <label for="cost_price" class="form-label">Preco de custo</label>
                        <input type="number" id="cost_price" name="cost_price" value="{{ old('cost_price', $article->cost_price ?? '') }}" class="form-control @error('cost_price') is-invalid @enderror" min="0" step="0.0001">
                        @error('cost_price')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 col-md-3">
                        <label for="sale_price" class="form-label">Preco de venda</label>
                        <input type="number" id="sale_price" name="sale_price" value="{{ old('sale_price', $article->sale_price ?? '') }}" class="form-control @error('sale_price') is-invalid @enderror" min="0" step="0.0001" required>
                        @error('sale_price')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 col-md-2">
                        <label for="default_margin" class="form-label">Margem %</label>
                        <input type="number" id="default_margin" name="default_margin" value="{{ old('default_margin', $article->default_margin ?? '') }}" class="form-control @error('default_margin') is-invalid @enderror" min="0" max="100" step="0.01">
                        @error('default_margin')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 col-md-2">
                        <label for="direct_discount" class="form-label">Desconto direto %</label>
                        <input type="number" id="direct_discount" name="direct_discount" value="{{ old('direct_discount', $article->direct_discount ?? '') }}" class="form-control @error('direct_discount') is-invalid @enderror" min="0" max="100" step="0.01">
                        @error('direct_discount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 col-md-2">
                        <label for="max_discount" class="form-label">Desconto max %</label>
                        <input type="number" id="max_discount" name="max_discount" value="{{ old('max_discount', $article->max_discount ?? '') }}" class="form-control @error('max_discount') is-invalid @enderror" min="0" max="100" step="0.01">
                        @error('max_discount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12 col-md-4">
                        <div class="form-check mt-2">
                            <input type="hidden" name="moves_stock" value="0">
                            <input class="form-check-input" type="checkbox" id="moves_stock" name="moves_stock" value="1" @checked(old('moves_stock', $article->moves_stock ?? true))>
                            <label class="form-check-label" for="moves_stock">Movimenta stock</label>
                        </div>
                    </div>
                    <div class="col-12 col-md-4">
                        <div class="form-check mt-2">
                            <input type="hidden" name="stock_alert_enabled" value="0">
                            <input class="form-check-input" type="checkbox" id="stock_alert_enabled" name="stock_alert_enabled" value="1" @checked(old('stock_alert_enabled', $article->stock_alert_enabled ?? false))>
                            <label class="form-check-label" for="stock_alert_enabled">Alerta de stock</label>
                        </div>
                    </div>
                    <div class="col-12 col-md-4">
                        <div class="form-check mt-2">
                            <input type="hidden" name="is_active" value="0">
                            <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" @checked(old('is_active', $article->is_active ?? true))>
                            <label class="form-check-label" for="is_active">Ativo</label>
                        </div>
                    </div>
                    <div class="col-12 col-md-4" id="minimum_stock_wrapper">
                        <label for="minimum_stock" class="form-label">Stock minimo</label>
                        <input type="number" id="minimum_stock" name="minimum_stock" value="{{ old('minimum_stock', $article->minimum_stock ?? '') }}" class="form-control @error('minimum_stock') is-invalid @enderror" min="0" step="0.001">
                        @error('minimum_stock')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>

            <div class="tab-pane" role="tabpanel" id="article-wizard-tab4" aria-labelledby="article-wizard-tab4">
                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <label for="internal_notes" class="form-label">Notas internas</label>
                        <textarea id="internal_notes" name="internal_notes" rows="4" class="form-control @error('internal_notes') is-invalid @enderror" maxlength="5000">{{ old('internal_notes', $article->internal_notes ?? '') }}</textarea>
                        @error('internal_notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 col-md-6">
                        <label for="print_notes" class="form-label">Notas para impressao</label>
                        <textarea id="print_notes" name="print_notes" rows="4" class="form-control @error('print_notes') is-invalid @enderror" maxlength="5000">{{ old('print_notes', $article->print_notes ?? '') }}</textarea>
                        @error('print_notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 col-md-6">
                        <label for="images" class="form-label">Imagens</label>
                        <input type="file" id="images" name="images[]" class="form-control @error('images') is-invalid @enderror @error('images.*') is-invalid @enderror" accept=".jpg,.jpeg,.png,.webp" multiple>
                        @error('images')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        @error('images.*')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 col-md-6">
                        <label for="documents" class="form-label">Documentos</label>
                        <input type="file" id="documents" name="documents[]" class="form-control @error('documents') is-invalid @enderror @error('documents.*') is-invalid @enderror" accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.txt,.jpg,.jpeg,.png,.webp" multiple>
                        @error('documents')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        @error('documents.*')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                @if ($isEdit)
                    <hr class="my-4">
                    <h6 class="mb-3">Imagens anexadas</h6>
                    @if ($article->images->isEmpty())
                        <p class="text-body-tertiary mb-4">Sem imagens anexadas.</p>
                    @else
                        <div class="row g-3 mb-4">
                            @foreach ($article->images as $articleImage)
                                <div class="col-12 col-md-6 col-xl-4">
                                    <div class="border rounded p-3 h-100 d-flex flex-column gap-2">
                                        <a href="{{ route('admin.articles.images.show', ['article' => $article->id, 'articleImage' => $articleImage->id]) }}" target="_blank" rel="noopener noreferrer" class="fw-semibold text-break">{{ $articleImage->original_name }}</a>
                                        <div class="small text-body-tertiary">
                                            @if ($articleImage->is_primary)
                                                <span class="badge badge-phoenix badge-phoenix-success">Primaria</span>
                                            @endif
                                            @if ($articleImage->file_size)
                                                <span class="ms-1">{{ number_format($articleImage->file_size / 1024, 1) }} KB</span>
                                            @endif
                                        </div>
                                        <form method="POST" action="{{ route('admin.articles.images.destroy', ['article' => $article->id, 'articleImage' => $articleImage->id]) }}" data-confirm="Tem a certeza que pretende remover esta imagem?" class="mt-auto">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-phoenix-danger btn-sm">Remover</button>
                                        </form>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    <h6 class="mb-3">Documentos anexados</h6>
                    @if ($article->files->isEmpty())
                        <p class="text-body-tertiary mb-0">Sem documentos anexados.</p>
                    @else
                        <div class="list-group">
                            @foreach ($article->files as $articleFile)
                                <div class="list-group-item d-flex justify-content-between align-items-center flex-wrap gap-2">
                                    <div>
                                        <a href="{{ route('admin.articles.files.download', ['article' => $article->id, 'articleFile' => $articleFile->id]) }}" class="fw-semibold">{{ $articleFile->original_name }}</a>
                                        <div class="small text-body-tertiary">
                                            {{ $articleFile->mime_type ?? '-' }}
                                            @if ($articleFile->file_size)
                                                &middot; {{ number_format($articleFile->file_size / 1024, 1) }} KB
                                            @endif
                                        </div>
                                    </div>
                                    <form method="POST" action="{{ route('admin.articles.files.destroy', ['article' => $article->id, 'articleFile' => $articleFile->id]) }}" data-confirm="Tem a certeza que pretende remover este ficheiro?">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-phoenix-danger btn-sm">Remover</button>
                                    </form>
                                </div>
                            @endforeach
                        </div>
                    @endif
                @endif
            </div>
        </div>
    </div>

    <div class="card-footer border-top-0" data-wizard-footer="data-wizard-footer">
        <div class="d-flex pager wizard list-inline mb-0">
            <button class="d-none btn btn-link ps-0" type="button" id="articleWizardPrevBtn"><span class="fas fa-chevron-left me-1"></span>Anterior</button>
            <div class="flex-1 text-end">
                <button class="btn btn-primary px-6 px-sm-6" type="button" id="articleWizardNextBtn">Seguinte <span class="fas fa-chevron-right ms-1"></span></button>
                <button class="btn btn-success px-6 px-sm-6 d-none" type="submit" id="articleWizardSubmitBtn">{{ $isEdit ? 'Gravar' : 'Criar artigo' }}</button>
            </div>
        </div>
    </div>
</div>

<div class="d-flex gap-2 justify-content-end">
    <a href="{{ route('admin.articles.index') }}" class="btn btn-phoenix-secondary">Cancelar</a>
</div>

@once
    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const wizard = document.getElementById('articleWizard');
                if (!wizard) {
                    return;
                }

                const form = wizard.closest('form');
                const navLinks = Array.from(wizard.querySelectorAll('[data-step-index]'));
                const panes = Array.from(wizard.querySelectorAll('.tab-pane'));
                const prevBtn = document.getElementById('articleWizardPrevBtn');
                const nextBtn = document.getElementById('articleWizardNextBtn');
                const submitBtn = document.getElementById('articleWizardSubmitBtn');

                const vatRate = document.getElementById('vat_rate_id');
                const vatExemptionReason = document.getElementById('vat_exemption_reason_id');
                const vatExemptionHelper = document.getElementById('vatExemptionHelper');
                const movesStock = document.getElementById('moves_stock');
                const stockAlertEnabled = document.getElementById('stock_alert_enabled');
                const minimumStockWrapper = document.getElementById('minimum_stock_wrapper');
                const minimumStock = document.getElementById('minimum_stock');

                const currentStepIndex = () => {
                    const index = navLinks.findIndex((link) => link.classList.contains('active'));
                    return index >= 0 ? index : 0;
                };

                const setStep = (index) => {
                    navLinks.forEach((link, i) => {
                        const pane = panes[i];
                        const isActive = i === index;
                        link.classList.toggle('active', isActive);
                        link.setAttribute('aria-selected', isActive ? 'true' : 'false');
                        link.setAttribute('tabindex', isActive ? '0' : '-1');
                        if (pane) {
                            pane.classList.toggle('active', isActive);
                            pane.classList.toggle('show', isActive);
                        }
                    });

                    if (prevBtn) {
                        prevBtn.classList.toggle('d-none', index === 0);
                    }

                    if (nextBtn) {
                        const isLast = index === navLinks.length - 1;
                        nextBtn.classList.toggle('d-none', isLast);
                    }

                    if (submitBtn) {
                        submitBtn.classList.toggle('d-none', index !== navLinks.length - 1);
                    }
                };

                const getValidatableFields = (container) => {
                    if (!container) {
                        return [];
                    }

                    return Array.from(container.querySelectorAll('input, select, textarea'))
                        .filter((field) => !field.disabled)
                        .filter((field) => field.type !== 'hidden');
                };

                const findFirstInvalidField = (container) => {
                    const fields = getValidatableFields(container);
                    return fields.find((field) => !field.checkValidity()) ?? null;
                };

                const validatePane = (index) => {
                    const pane = panes[index];
                    if (!pane) {
                        return true;
                    }

                    const invalidField = findFirstInvalidField(pane);
                    if (invalidField) {
                        setStep(index);
                        invalidField.reportValidity();
                        return false;
                    }

                    return true;
                };

                const validateForm = () => {
                    if (!form) {
                        return true;
                    }

                    const invalidField = findFirstInvalidField(form);
                    if (!invalidField) {
                        return true;
                    }

                    const pane = invalidField.closest('.tab-pane');
                    if (pane) {
                        const paneIndex = panes.indexOf(pane);
                        if (paneIndex >= 0) {
                            setStep(paneIndex);
                        }
                    }

                    invalidField.reportValidity();
                    return false;
                };

                navLinks.forEach((link) => {
                    link.addEventListener('click', (event) => {
                        event.preventDefault();
                        const index = Number(link.dataset.stepIndex ?? 0);

                        const current = currentStepIndex();
                        if (index > current) {
                            for (let step = current; step < index; step++) {
                                if (!validatePane(step)) {
                                    return;
                                }
                            }
                        }

                        setStep(index);
                    });
                });

                if (prevBtn) {
                    prevBtn.addEventListener('click', () => {
                        setStep(Math.max(0, currentStepIndex() - 1));
                    });
                }

                if (nextBtn) {
                    nextBtn.addEventListener('click', () => {
                        const index = currentStepIndex();
                        if (!validatePane(index)) {
                            return;
                        }

                        setStep(Math.min(navLinks.length - 1, index + 1));
                    });
                }

                if (submitBtn) {
                    submitBtn.addEventListener('click', (event) => {
                        if (validateForm()) {
                            return;
                        }

                        event.preventDefault();
                    });
                }

                const syncVatReason = () => {
                    if (!vatRate || !vatExemptionReason) {
                        return;
                    }

                    const selectedOption = vatRate.options[vatRate.selectedIndex];
                    const isExempt = selectedOption && selectedOption.dataset.isExempt === '1';
                    vatExemptionReason.required = isExempt;

                    if (!isExempt) {
                        vatExemptionReason.value = '';
                    }

                    if (vatExemptionHelper) {
                        vatExemptionHelper.textContent = isExempt
                            ? 'Obrigatorio para taxas de IVA isentas.'
                            : 'Opcional para taxas nao isentas.';
                    }
                };

                const syncStock = () => {
                    if (!movesStock || !stockAlertEnabled || !minimumStockWrapper || !minimumStock) {
                        return;
                    }

                    if (!movesStock.checked) {
                        stockAlertEnabled.checked = false;
                        stockAlertEnabled.disabled = true;
                    } else {
                        stockAlertEnabled.disabled = false;
                    }

                    const showMinimumStock = movesStock.checked && stockAlertEnabled.checked;
                    minimumStockWrapper.classList.toggle('d-none', !showMinimumStock);
                    minimumStock.disabled = !showMinimumStock;

                    if (!showMinimumStock) {
                        minimumStock.value = '';
                    }
                };

                if (vatRate) {
                    vatRate.addEventListener('change', syncVatReason);
                    syncVatReason();
                }

                if (movesStock && stockAlertEnabled) {
                    movesStock.addEventListener('change', syncStock);
                    stockAlertEnabled.addEventListener('change', syncStock);
                    syncStock();
                }

                if (form) {
                    form.addEventListener('submit', (event) => {
                        if (validateForm()) {
                            return;
                        }

                        event.preventDefault();
                    });
                }

                const firstInvalid = wizard.querySelector('.is-invalid');
                if (firstInvalid) {
                    const pane = firstInvalid.closest('.tab-pane');
                    const paneIndex = pane ? panes.indexOf(pane) : 0;
                    setStep(paneIndex >= 0 ? paneIndex : 0);
                } else {
                    setStep(0);
                }
            });
        </script>
    @endpush
@endonce
