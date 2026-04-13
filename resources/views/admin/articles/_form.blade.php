@php
    $isEdit = isset($article);
    $selectedCategoryId = old('category_id', $article->category_id ?? ($defaults['category_id'] ?? ''));
    $selectedUnitId = old('unit_id', $article->unit_id ?? ($defaults['unit_id'] ?? ''));
@endphp

<div class="row g-3">
    <div class="col-12">
        <h6 class="text-uppercase text-body-tertiary mb-1">Identificacao</h6>
    </div>

    <div class="col-12 col-md-3">
        <label class="form-label">Codigo</label>
        <input
            type="text"
            class="form-control"
            value="{{ $article->code ?? 'Gerado automaticamente no create' }}"
            readonly
        >
    </div>

    <div class="col-12 col-md-6">
        <label for="designation" class="form-label">Designacao</label>
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

    <div class="col-12 col-md-3">
        <label for="abbreviation" class="form-label">Abreviatura</label>
        <input
            type="text"
            id="abbreviation"
            name="abbreviation"
            value="{{ old('abbreviation', $article->abbreviation ?? '') }}"
            class="form-control @error('abbreviation') is-invalid @enderror"
            maxlength="50"
        >
        @error('abbreviation')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12 col-md-3">
        <label for="ean" class="form-label">EAN</label>
        <input
            type="text"
            id="ean"
            name="ean"
            value="{{ old('ean', $article->ean ?? '') }}"
            class="form-control @error('ean') is-invalid @enderror"
            maxlength="20"
            placeholder="8 a 14 digitos"
        >
        @error('ean')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12 col-md-3">
        <label for="supplier_id" class="form-label">Fornecedor (ID)</label>
        <input
            type="number"
            id="supplier_id"
            name="supplier_id"
            value="{{ old('supplier_id', $article->supplier_id ?? '') }}"
            class="form-control @error('supplier_id') is-invalid @enderror"
            min="1"
        >
        @error('supplier_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12 col-md-6">
        <label for="supplier_reference" class="form-label">Referencia fornecedor</label>
        <input
            type="text"
            id="supplier_reference"
            name="supplier_reference"
            value="{{ old('supplier_reference', $article->supplier_reference ?? '') }}"
            class="form-control @error('supplier_reference') is-invalid @enderror"
            maxlength="120"
        >
        @error('supplier_reference')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12 mt-4">
        <h6 class="text-uppercase text-body-tertiary mb-1">Classificacao</h6>
    </div>

    <div class="col-12 col-md-4">
        <label for="product_family_id" class="form-label">Familia</label>
        <select
            id="product_family_id"
            name="product_family_id"
            class="form-select @error('product_family_id') is-invalid @enderror"
            required
        >
            <option value="">Selecionar familia</option>
            @foreach (($familyOptions ?? []) as $familyOption)
                <option
                    value="{{ $familyOption['id'] }}"
                    @selected((string) old('product_family_id', $article->product_family_id ?? '') === (string) $familyOption['id'])
                >
                    {{ $familyOption['label'] }}
                </option>
            @endforeach
        </select>
        @error('product_family_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12 col-md-4">
        <label for="category_id" class="form-label">Categoria</label>
        <select
            id="category_id"
            name="category_id"
            class="form-select @error('category_id') is-invalid @enderror"
            required
        >
            <option value="">Selecionar categoria</option>
            @foreach (($categoryOptions ?? []) as $categoryOption)
                <option value="{{ $categoryOption->id }}" @selected((string) $selectedCategoryId === (string) $categoryOption->id)>
                    {{ $categoryOption->name }}
                </option>
            @endforeach
        </select>
        @error('category_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12 col-md-4">
        <label for="unit_id" class="form-label">Unidade</label>
        <select
            id="unit_id"
            name="unit_id"
            class="form-select @error('unit_id') is-invalid @enderror"
            required
        >
            <option value="">Selecionar unidade</option>
            @foreach (($unitOptions ?? []) as $unitOption)
                <option value="{{ $unitOption->id }}" @selected((string) $selectedUnitId === (string) $unitOption->id)>
                    {{ $unitOption->code }} - {{ $unitOption->name }}
                </option>
            @endforeach
        </select>
        @error('unit_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12 col-md-4">
        <label for="brand_id" class="form-label">Marca</label>
        <select
            id="brand_id"
            name="brand_id"
            class="form-select @error('brand_id') is-invalid @enderror"
        >
            <option value="">Sem marca</option>
            @foreach (($brandOptions ?? []) as $brandOption)
                <option
                    value="{{ $brandOption->id }}"
                    @selected((string) old('brand_id', $article->brand_id ?? '') === (string) $brandOption->id)
                >
                    {{ $brandOption->name }}
                </option>
            @endforeach
        </select>
        @error('brand_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12 mt-4">
        <h6 class="text-uppercase text-body-tertiary mb-1">Fiscalidade</h6>
    </div>

    <div class="col-12 col-md-6">
        <label for="vat_rate_id" class="form-label">Taxa de IVA</label>
        <select
            id="vat_rate_id"
            name="vat_rate_id"
            class="form-select @error('vat_rate_id') is-invalid @enderror"
            required
        >
            <option value="">Selecionar taxa</option>
            @foreach (($vatRateOptions ?? []) as $vatRateOption)
                <option
                    value="{{ $vatRateOption->id }}"
                    @selected((string) old('vat_rate_id', $article->vat_rate_id ?? '') === (string) $vatRateOption->id)
                >
                    {{ $vatRateOption->name }} ({{ number_format((float) $vatRateOption->rate, 2) }}%)
                </option>
            @endforeach
        </select>
        @error('vat_rate_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12 col-md-6">
        <label for="vat_exemption_reason_id" class="form-label">Motivo de isencao IVA</label>
        <select
            id="vat_exemption_reason_id"
            name="vat_exemption_reason_id"
            class="form-select @error('vat_exemption_reason_id') is-invalid @enderror"
        >
            <option value="">Sem motivo</option>
            @foreach (($vatExemptionReasonOptions ?? []) as $reasonOption)
                <option
                    value="{{ $reasonOption->id }}"
                    @selected((string) old('vat_exemption_reason_id', $article->vat_exemption_reason_id ?? '') === (string) $reasonOption->id)
                >
                    {{ $reasonOption->code }} - {{ $reasonOption->name }}
                </option>
            @endforeach
        </select>
        @error('vat_exemption_reason_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12 mt-4">
        <h6 class="text-uppercase text-body-tertiary mb-1">Precos e descontos</h6>
    </div>

    <div class="col-12 col-md-3">
        <label for="cost_price" class="form-label">Preco de custo</label>
        <input
            type="number"
            id="cost_price"
            name="cost_price"
            value="{{ old('cost_price', $article->cost_price ?? '') }}"
            class="form-control @error('cost_price') is-invalid @enderror"
            min="0"
            step="0.0001"
        >
        @error('cost_price')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12 col-md-3">
        <label for="sale_price" class="form-label">Preco de venda</label>
        <input
            type="number"
            id="sale_price"
            name="sale_price"
            value="{{ old('sale_price', $article->sale_price ?? '') }}"
            class="form-control @error('sale_price') is-invalid @enderror"
            min="0"
            step="0.0001"
        >
        @error('sale_price')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12 col-md-2">
        <label for="default_margin" class="form-label">Margem %</label>
        <input
            type="number"
            id="default_margin"
            name="default_margin"
            value="{{ old('default_margin', $article->default_margin ?? '') }}"
            class="form-control @error('default_margin') is-invalid @enderror"
            min="0"
            max="100"
            step="0.01"
        >
        @error('default_margin')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12 col-md-2">
        <label for="direct_discount" class="form-label">Desconto direto %</label>
        <input
            type="number"
            id="direct_discount"
            name="direct_discount"
            value="{{ old('direct_discount', $article->direct_discount ?? '') }}"
            class="form-control @error('direct_discount') is-invalid @enderror"
            min="0"
            max="100"
            step="0.01"
        >
        @error('direct_discount')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12 col-md-2">
        <label for="max_discount" class="form-label">Desconto max %</label>
        <input
            type="number"
            id="max_discount"
            name="max_discount"
            value="{{ old('max_discount', $article->max_discount ?? '') }}"
            class="form-control @error('max_discount') is-invalid @enderror"
            min="0"
            max="100"
            step="0.01"
        >
        @error('max_discount')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12 mt-4">
        <h6 class="text-uppercase text-body-tertiary mb-1">Stock e estado</h6>
    </div>

    <div class="col-12 col-md-3">
        <label for="minimum_stock" class="form-label">Stock minimo</label>
        <input
            type="number"
            id="minimum_stock"
            name="minimum_stock"
            value="{{ old('minimum_stock', $article->minimum_stock ?? '') }}"
            class="form-control @error('minimum_stock') is-invalid @enderror"
            min="0"
            step="0.001"
        >
        @error('minimum_stock')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12 col-md-3 d-flex align-items-end">
        <div class="form-check">
            <input type="hidden" name="moves_stock" value="0">
            <input
                class="form-check-input"
                type="checkbox"
                id="moves_stock"
                name="moves_stock"
                value="1"
                @checked(old('moves_stock', $article->moves_stock ?? true))
            >
            <label class="form-check-label" for="moves_stock">Movimenta stock</label>
        </div>
    </div>

    <div class="col-12 col-md-3 d-flex align-items-end">
        <div class="form-check">
            <input type="hidden" name="stock_alert_enabled" value="0">
            <input
                class="form-check-input"
                type="checkbox"
                id="stock_alert_enabled"
                name="stock_alert_enabled"
                value="1"
                @checked(old('stock_alert_enabled', $article->stock_alert_enabled ?? false))
            >
            <label class="form-check-label" for="stock_alert_enabled">Alerta de stock</label>
        </div>
    </div>

    <div class="col-12 col-md-3 d-flex align-items-end">
        <div class="form-check">
            <input type="hidden" name="is_active" value="0">
            <input
                class="form-check-input"
                type="checkbox"
                id="is_active"
                name="is_active"
                value="1"
                @checked(old('is_active', $article->is_active ?? true))
            >
            <label class="form-check-label" for="is_active">Ativo</label>
        </div>
    </div>

    <div class="col-12 mt-4">
        <h6 class="text-uppercase text-body-tertiary mb-1">Notas</h6>
    </div>

    <div class="col-12 col-md-6">
        <label for="internal_notes" class="form-label">Notas internas</label>
        <textarea
            id="internal_notes"
            name="internal_notes"
            rows="4"
            class="form-control @error('internal_notes') is-invalid @enderror"
            maxlength="5000"
        >{{ old('internal_notes', $article->internal_notes ?? '') }}</textarea>
        @error('internal_notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12 col-md-6">
        <label for="print_notes" class="form-label">Notas para impressao</label>
        <textarea
            id="print_notes"
            name="print_notes"
            rows="4"
            class="form-control @error('print_notes') is-invalid @enderror"
            maxlength="5000"
        >{{ old('print_notes', $article->print_notes ?? '') }}</textarea>
        @error('print_notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12 mt-4">
        <h6 class="text-uppercase text-body-tertiary mb-1">Ficheiros</h6>
    </div>

    <div class="col-12 col-md-6">
        <label for="images" class="form-label">Imagens</label>
        <input
            type="file"
            id="images"
            name="images[]"
            class="form-control @error('images') is-invalid @enderror @error('images.*') is-invalid @enderror"
            accept=".jpg,.jpeg,.png,.webp"
            multiple
        >
        @error('images')<div class="invalid-feedback">{{ $message }}</div>@enderror
        @error('images.*')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12 col-md-6">
        <label for="documents" class="form-label">Documentos</label>
        <input
            type="file"
            id="documents"
            name="documents[]"
            class="form-control @error('documents') is-invalid @enderror @error('documents.*') is-invalid @enderror"
            accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.txt,.jpg,.jpeg,.png,.webp"
            multiple
        >
        @error('documents')<div class="invalid-feedback">{{ $message }}</div>@enderror
        @error('documents.*')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12 d-flex gap-2 justify-content-end mt-3">
        <a href="{{ route('admin.articles.index') }}" class="btn btn-phoenix-secondary">Cancelar</a>
        <button type="submit" class="btn btn-primary">
            {{ $isEdit ? 'Guardar alteracoes' : 'Criar artigo' }}
        </button>
    </div>
</div>

