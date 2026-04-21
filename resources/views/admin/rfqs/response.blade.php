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
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
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
                        <input type="datetime-local" id="received_at" name="received_at" value="{{ old('received_at', optional($existingQuote?->received_at)->format('Y-m-d\TH:i') ?? now()->format('Y-m-d\TH:i')) }}" class="form-control @error('received_at') is-invalid @enderror" required>
                        @error('received_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 col-md-3">
                        <label for="shipping_cost" class="form-label">Portes</label>
                        <input type="number" id="shipping_cost" name="shipping_cost" min="0" step="0.01" value="{{ old('shipping_cost', $existingQuote->shipping_cost ?? 0) }}" class="form-control @error('shipping_cost') is-invalid @enderror">
                        @error('shipping_cost')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 col-md-3">
                        <label for="delivery_days" class="form-label">Prazo entrega (dias)</label>
                        <input type="number" id="delivery_days" name="delivery_days" min="0" step="1" value="{{ old('delivery_days', $existingQuote->delivery_days ?? '') }}" class="form-control @error('delivery_days') is-invalid @enderror">
                        @error('delivery_days')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 col-md-3">
                        <label for="supplier_document_date" class="form-label">Data documento fornecedor</label>
                        <input type="date" id="supplier_document_date" name="supplier_document_date" value="{{ old('supplier_document_date', optional($existingQuote?->supplier_document_date)->format('Y-m-d')) }}" class="form-control @error('supplier_document_date') is-invalid @enderror">
                        @error('supplier_document_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 col-md-3">
                        <label for="valid_until" class="form-label">Validade proposta</label>
                        <input type="date" id="valid_until" name="valid_until" value="{{ old('valid_until', optional($existingQuote?->valid_until)->format('Y-m-d')) }}" class="form-control @error('valid_until') is-invalid @enderror">
                        @error('valid_until')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 col-md-6">
                        <label for="supplier_document_number" class="form-label">Numero documento fornecedor</label>
                        <input type="text" id="supplier_document_number" name="supplier_document_number" value="{{ old('supplier_document_number', $existingQuote->supplier_document_number ?? '') }}" class="form-control @error('supplier_document_number') is-invalid @enderror" maxlength="120">
                        @error('supplier_document_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 col-md-6">
                        <label for="commercial_discount_text" class="form-label">Desconto comercial (texto livre)</label>
                        <input type="text" id="commercial_discount_text" name="commercial_discount_text" value="{{ old('commercial_discount_text', $existingQuote->commercial_discount_text ?? '') }}" class="form-control @error('commercial_discount_text') is-invalid @enderror" maxlength="255" placeholder="Ex.: 3% pp">
                        @error('commercial_discount_text')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 col-md-6">
                        @php
                            $selectedPaymentTerm = old('payment_terms_text', $existingQuote->payment_terms_text ?? $defaultPaymentTermText);
                            $hasSelectedInOptions = in_array($selectedPaymentTerm, $paymentTermOptions, true);
                        @endphp
                        <label for="payment_terms_text" class="form-label">Condicoes de pagamento</label>
                        <select id="payment_terms_text" name="payment_terms_text" class="form-select @error('payment_terms_text') is-invalid @enderror">
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
                        <input type="file" id="supplier_document_pdf" name="supplier_document_pdf" accept="application/pdf" class="form-control @error('supplier_document_pdf') is-invalid @enderror">
                        @error('supplier_document_pdf')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        @if ($existingQuote?->supplier_document_pdf_path)
                            <div class="mt-2">
                                <a href="{{ route('admin.rfqs.responses.document.download', [$rfq->id, $rfqSupplier->id]) }}" class="btn btn-phoenix-secondary btn-sm">Download documento atual</a>
                            </div>
                        @endif
                    </div>
                    <div class="col-12">
                        <label for="notes" class="form-label">Observacoes</label>
                        <textarea id="notes" name="notes" rows="3" class="form-control @error('notes') is-invalid @enderror">{{ old('notes', $existingQuote->notes ?? '') }}</textarea>
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
                                <th>Disponivel</th>
                                <th>Qtd.</th>
                                <th>P. unit.</th>
                                <th>Desc %</th>
                                <th>IVA %</th>
                                <th>Alternativo</th>
                                <th>Descricao alternativa</th>
                                <th>Marca</th>
                                <th class="pe-3">Notas</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rfq->items as $index => $rfqItem)
                                @php
                                    $existing = $existingItemsByRfqItem->get($rfqItem->id);
                                    $isResponded = old("items.$index.is_responded", $existing !== null);
                                    $isAvailable = old("items.$index.is_available", $existing?->is_available ?? true);
                                    $isAlternative = old("items.$index.is_alternative", $existing?->is_alternative ?? false);
                                @endphp
                                <tr class="response-row">
                                    <td class="ps-3">
                                        <input type="hidden" name="items[{{ $index }}][supplier_quote_request_item_id]" value="{{ $rfqItem->id }}">
                                        <input type="hidden" name="items[{{ $index }}][is_responded]" value="0">
                                        <input class="form-check-input is-responded-input" type="checkbox" name="items[{{ $index }}][is_responded]" value="1" @checked($isResponded)>
                                    </td>
                                    <td>
                                        <div class="fw-semibold">{{ $rfqItem->description }}</div>
                                        <div class="text-body-tertiary fs-10">{{ $rfqItem->article_code ?: '-' }} | {{ number_format((float) $rfqItem->quantity, 3, ',', '.') }} {{ $rfqItem->unit_name ?: '' }}</div>
                                    </td>
                                    <td>
                                        <input type="hidden" name="items[{{ $index }}][is_available]" value="0">
                                        <input class="form-check-input is-available-input" type="checkbox" name="items[{{ $index }}][is_available]" value="1" @checked($isAvailable)>
                                    </td>
                                    <td>
                                        <input type="number" min="0" step="0.001" name="items[{{ $index }}][quantity]" value="{{ old("items.$index.quantity", $existing?->quantity ?? $rfqItem->quantity) }}" class="form-control form-control-sm response-field @error("items.$index.quantity") is-invalid @enderror">
                                        @error("items.$index.quantity")<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                    </td>
                                    <td>
                                        <input type="number" min="0" step="0.0001" name="items[{{ $index }}][unit_price]" value="{{ old("items.$index.unit_price", $existing?->unit_price ?? '') }}" class="form-control form-control-sm response-field @error("items.$index.unit_price") is-invalid @enderror">
                                        @error("items.$index.unit_price")<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                    </td>
                                    <td>
                                        <input type="number" min="0" max="100" step="0.01" name="items[{{ $index }}][discount_percent]" value="{{ old("items.$index.discount_percent", $existing?->discount_percent ?? 0) }}" class="form-control form-control-sm response-field @error("items.$index.discount_percent") is-invalid @enderror">
                                        @error("items.$index.discount_percent")<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                    </td>
                                    <td>
                                        <input type="number" min="0" max="100" step="0.01" name="items[{{ $index }}][vat_percent]" value="{{ old("items.$index.vat_percent", $existing?->vat_percent ?? 0) }}" class="form-control form-control-sm response-field @error("items.$index.vat_percent") is-invalid @enderror">
                                        @error("items.$index.vat_percent")<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                    </td>
                                    <td>
                                        <input type="hidden" name="items[{{ $index }}][is_alternative]" value="0">
                                        <input class="form-check-input response-field" type="checkbox" name="items[{{ $index }}][is_alternative]" value="1" @checked($isAlternative)>
                                    </td>
                                    <td>
                                        <textarea rows="2" name="items[{{ $index }}][alternative_description]" class="form-control form-control-sm response-field @error("items.$index.alternative_description") is-invalid @enderror">{{ old("items.$index.alternative_description", $existing?->alternative_description ?? '') }}</textarea>
                                        @error("items.$index.alternative_description")<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                    </td>
                                    <td>
                                        <input type="text" maxlength="120" name="items[{{ $index }}][brand]" value="{{ old("items.$index.brand", $existing?->brand ?? '') }}" class="form-control form-control-sm response-field @error("items.$index.brand") is-invalid @enderror">
                                        @error("items.$index.brand")<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                    </td>
                                    <td class="pe-3">
                                        <textarea rows="2" name="items[{{ $index }}][notes]" class="form-control form-control-sm response-field @error("items.$index.notes") is-invalid @enderror">{{ old("items.$index.notes", $existing?->notes ?? '') }}</textarea>
                                        @error("items.$index.notes")<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="d-flex gap-2 justify-content-end">
            <a href="{{ route('admin.rfqs.show', $rfq->id) }}" class="btn btn-phoenix-secondary">Cancelar</a>
            <button type="submit" class="btn btn-primary">Guardar resposta</button>
        </div>
    </form>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const rows = document.querySelectorAll('tr.response-row');

            const syncRow = (row) => {
                const isResponded = row.querySelector('.is-responded-input')?.checked ?? false;
                const isAvailable = row.querySelector('.is-available-input')?.checked ?? true;
                const fields = row.querySelectorAll('.response-field');

                fields.forEach((field) => {
                    if (!isResponded) {
                        field.disabled = true;
                        return;
                    }

                    if (!isAvailable && ['quantity', 'unit_price', 'discount_percent', 'vat_percent'].some((name) => field.name.endsWith('[' + name + ']'))) {
                        field.disabled = true;
                        return;
                    }

                    field.disabled = false;
                });
            };

            rows.forEach((row) => {
                const respondedInput = row.querySelector('.is-responded-input');
                const availableInput = row.querySelector('.is-available-input');
                if (respondedInput) respondedInput.addEventListener('change', () => syncRow(row));
                if (availableInput) availableInput.addEventListener('change', () => syncRow(row));
                syncRow(row);
            });
        });
    </script>
@endpush
