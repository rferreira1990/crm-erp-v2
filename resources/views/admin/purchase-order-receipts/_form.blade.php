@php
    $receiptDateDefault = old('receipt_date', isset($receipt) ? optional($receipt->receipt_date)->format('Y-m-d') : now()->toDateString());
    $supplierDocNumberDefault = old('supplier_document_number', isset($receipt) ? $receipt->supplier_document_number : null);
    $supplierDocDateDefault = old('supplier_document_date', isset($receipt) ? optional($receipt->supplier_document_date)->format('Y-m-d') : null);
    $notesDefault = old('notes', isset($receipt) ? $receipt->notes : null);
    $internalNotesDefault = old('internal_notes', isset($receipt) ? $receipt->internal_notes : null);
@endphp

<form method="POST" action="{{ $formAction }}" class="row g-4">
    @csrf
    @if ($formMethod === 'PATCH')
        @method('PATCH')
    @endif

    <div class="col-12">
        <div class="card">
            <div class="card-header bg-body-tertiary">
                <h5 class="mb-0">Dados da rececao</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12 col-md-4">
                        <label class="form-label" for="receipt_date">Data da rececao</label>
                        <input type="date" id="receipt_date" name="receipt_date" value="{{ $receiptDateDefault }}" class="form-control @error('receipt_date') is-invalid @enderror" required>
                        @error('receipt_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label" for="supplier_document_number">Doc. fornecedor</label>
                        <input type="text" id="supplier_document_number" name="supplier_document_number" value="{{ $supplierDocNumberDefault }}" class="form-control @error('supplier_document_number') is-invalid @enderror" maxlength="120">
                        @error('supplier_document_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label" for="supplier_document_date">Data doc. fornecedor</label>
                        <input type="date" id="supplier_document_date" name="supplier_document_date" value="{{ $supplierDocDateDefault }}" class="form-control @error('supplier_document_date') is-invalid @enderror">
                        @error('supplier_document_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="notes">Notas</label>
                        <textarea id="notes" name="notes" rows="2" class="form-control @error('notes') is-invalid @enderror">{{ $notesDefault }}</textarea>
                        @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="internal_notes">Notas internas</label>
                        <textarea id="internal_notes" name="internal_notes" rows="2" class="form-control @error('internal_notes') is-invalid @enderror">{{ $internalNotesDefault }}</textarea>
                        @error('internal_notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card">
            <div class="card-header bg-body-tertiary">
                <h5 class="mb-0">Linhas da rececao</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm fs-9 mb-0 align-middle">
                        <thead class="bg-body-tertiary">
                            <tr>
                                <th class="ps-3">#</th>
                                <th>Codigo</th>
                                <th>Descricao</th>
                                <th>Qtd. encomendada</th>
                                <th>Qtd. recebida</th>
                                <th>Qtd. em falta</th>
                                <th>Qtd. a receber agora</th>
                                <th class="pe-3">Notas linha</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($lines as $line)
                                @php
                                    $itemId = (int) $line['purchase_order_item_id'];
                                    $receivedInput = old('items.'.$itemId.'.received_quantity', $line['received_quantity']);
                                    $notesInput = old('items.'.$itemId.'.notes', $line['notes'] ?? null);
                                @endphp
                                <tr>
                                    <td class="ps-3">{{ $line['line_order'] }}</td>
                                    <td>{{ $line['article_code'] ?: '-' }}</td>
                                    <td>{{ $line['description'] }}</td>
                                    <td>{{ number_format((float) $line['ordered_quantity'], 3, ',', '.') }}</td>
                                    <td>{{ number_format((float) $line['previously_received_quantity'], 3, ',', '.') }}</td>
                                    <td>{{ number_format((float) $line['remaining_quantity'], 3, ',', '.') }}</td>
                                    <td style="min-width: 160px;">
                                        <input type="hidden" name="items[{{ $itemId }}][purchase_order_item_id]" value="{{ $itemId }}">
                                        <input
                                            type="number"
                                            step="0.001"
                                            min="0"
                                            max="{{ number_format((float) $line['remaining_quantity'], 3, '.', '') }}"
                                            name="items[{{ $itemId }}][received_quantity]"
                                            value="{{ number_format((float) $receivedInput, 3, '.', '') }}"
                                            class="form-control form-control-sm @error('items.'.$itemId.'.received_quantity') is-invalid @enderror"
                                        >
                                        @error('items.'.$itemId.'.received_quantity')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                    </td>
                                    <td class="pe-3" style="min-width: 200px;">
                                        <input
                                            type="text"
                                            name="items[{{ $itemId }}][notes]"
                                            value="{{ $notesInput }}"
                                            class="form-control form-control-sm @error('items.'.$itemId.'.notes') is-invalid @enderror"
                                            maxlength="1000"
                                        >
                                        @error('items.'.$itemId.'.notes')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center py-4 text-body-tertiary">Sem linhas disponiveis para rececao.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 d-flex justify-content-end gap-2">
        <a href="{{ route('admin.purchase-orders.show', $purchaseOrder->id) }}" class="btn btn-phoenix-secondary">Voltar</a>
        <button type="submit" class="btn btn-primary">{{ $submitLabel }}</button>
    </div>
</form>
