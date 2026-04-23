@extends('layouts.admin')

@section('title', 'Ficha da rececao')
@section('page_title', 'Ficha da rececao')
@section('page_subtitle', $receipt->number)

@section('page_actions')
    <a href="{{ route('admin.purchase-order-receipts.index') }}" class="btn btn-phoenix-secondary btn-sm">Voltar</a>
    <form method="POST" action="{{ route('admin.purchase-order-receipts.pdf.generate', $receipt->id) }}" class="d-inline">
        @csrf
        <button type="submit" class="btn btn-phoenix-secondary btn-sm">Gerar PDF</button>
    </form>
    @if ($receipt->pdf_path)
        <a href="{{ route('admin.purchase-order-receipts.pdf.download', $receipt->id) }}" class="btn btn-phoenix-secondary btn-sm">Download PDF</a>
    @endif
    @if ($receipt->isEditable() && auth()->user()->can('company.purchase_order_receipts.update'))
        <a href="{{ route('admin.purchase-order-receipts.edit', $receipt->id) }}" class="btn btn-phoenix-secondary btn-sm">Editar</a>
    @endif
    @if ($receipt->canPost() && auth()->user()->can('company.purchase_order_receipts.post'))
        <form method="POST" action="{{ route('admin.purchase-order-receipts.post', $receipt->id) }}" class="d-inline">
            @csrf
            <button type="submit" class="btn btn-primary btn-sm">Confirmar rececao</button>
        </form>
    @endif
    @if ($receipt->isEditable() && $unresolvedStockLines->isNotEmpty())
        <button type="button" class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#resolveStockLineModal-{{ $unresolvedStockLines->first()->id }}">
            Resolver linhas livres
        </button>
    @endif
@endsection

@section('breadcrumbs')
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.purchase-order-receipts.index') }}">Rececoes de encomendas</a></li>
        <li class="breadcrumb-item active" aria-current="page">{{ $receipt->number }}</li>
    </ol>
@endsection

@section('content')
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
    @endif

    <div class="row g-4 mb-4">
        <div class="col-12 col-xxl-8">
            <div class="card mb-4">
                <div class="card-header bg-body-tertiary d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">{{ $receipt->number }}</h5>
                    <span class="badge badge-phoenix {{ $receipt->statusBadgeClass() }}">{{ $statusLabels[$receipt->status] ?? $receipt->status }}</span>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12 col-md-4">
                            <div class="text-body-tertiary fs-9">Data rececao</div>
                            <div class="fw-semibold">{{ optional($receipt->receipt_date)->format('Y-m-d') ?? '-' }}</div>
                        </div>
                        <div class="col-12 col-md-4">
                            <div class="text-body-tertiary fs-9">Doc. fornecedor</div>
                            <div class="fw-semibold">{{ $receipt->supplier_document_number ?: '-' }}</div>
                        </div>
                        <div class="col-12 col-md-4">
                            <div class="text-body-tertiary fs-9">Data doc. fornecedor</div>
                            <div class="fw-semibold">{{ optional($receipt->supplier_document_date)->format('Y-m-d') ?? '-' }}</div>
                        </div>
                        <div class="col-12 col-md-4">
                            <div class="text-body-tertiary fs-9">Encomenda origem</div>
                            <div class="fw-semibold">
                                @if ($receipt->purchaseOrder)
                                    <a href="{{ route('admin.purchase-orders.show', $receipt->purchaseOrder->id) }}">{{ $receipt->purchaseOrder->number }}</a>
                                @else
                                    -
                                @endif
                            </div>
                        </div>
                        <div class="col-12 col-md-4">
                            <div class="text-body-tertiary fs-9">Fornecedor</div>
                            <div class="fw-semibold">{{ $receipt->purchaseOrder?->supplier_name_snapshot ?? '-' }}</div>
                        </div>
                        <div class="col-12 col-md-4">
                            <div class="text-body-tertiary fs-9">Recebido por</div>
                            <div class="fw-semibold">{{ $receipt->receiver?->name ?? '-' }}</div>
                        </div>
                        <div class="col-12 col-md-6">
                            <div class="text-body-tertiary fs-9">Notas</div>
                            <div>{{ $receipt->notes ?: '-' }}</div>
                        </div>
                        <div class="col-12 col-md-6">
                            <div class="text-body-tertiary fs-9">Notas internas</div>
                            <div>{{ $receipt->internal_notes ?: '-' }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header bg-body-tertiary">
                    <h5 class="mb-0">Linhas recebidas</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm fs-9 mb-0">
                            <thead class="bg-body-tertiary">
                                <tr>
                                    <th class="ps-3">#</th>
                                    <th>Codigo</th>
                                    <th>Descricao</th>
                                    <th>Qtd. encomendada</th>
                                    <th>Qtd. recebida antes</th>
                                    <th>Qtd. recebida agora</th>
                                    <th class="pe-3">Qtd. em falta</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($receipt->items as $item)
                                    @php
                                        $remaining = max(0, (float) $item->ordered_quantity - ((float) $item->previously_received_quantity + (float) $item->received_quantity));
                                    @endphp
                                    <tr>
                                        <td class="ps-3">{{ $item->line_order }}</td>
                                        <td>{{ $item->article_code ?: '-' }}</td>
                                        <td>{{ $item->description }}</td>
                                        <td>{{ number_format((float) $item->ordered_quantity, 3, ',', '.') }}</td>
                                        <td>{{ number_format((float) $item->previously_received_quantity, 3, ',', '.') }}</td>
                                        <td>{{ number_format((float) $item->received_quantity, 3, ',', '.') }}</td>
                                        <td class="pe-3">{{ number_format($remaining, 3, ',', '.') }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center py-4 text-body-tertiary">Sem linhas.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            @if ($unresolvedStockLines->isNotEmpty())
                <div class="card mb-4 border-warning">
                    <div class="card-header bg-warning-subtle">
                        <h5 class="mb-0 text-warning-emphasis">Linhas livres por resolver</h5>
                    </div>
                    <div class="card-body">
                        <p class="mb-3 text-body-secondary">
                            Existem linhas de texto com quantidade recebida que precisam de decisao explicita antes de confirmar a rececao.
                        </p>
                        <div class="table-responsive">
                            <table class="table table-sm fs-9 mb-0">
                                <thead class="bg-body-tertiary">
                                    <tr>
                                        <th class="ps-3">#</th>
                                        <th>Descricao</th>
                                        <th>Qtd. recebida</th>
                                        <th>Unidade</th>
                                        <th class="text-end pe-3">Resolver</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($unresolvedStockLines as $line)
                                        <tr>
                                            <td class="ps-3">{{ $line->line_order }}</td>
                                            <td>{{ $line->description }}</td>
                                            <td>{{ number_format((float) $line->received_quantity, 3, ',', '.') }}</td>
                                            <td>{{ $line->unit_name ?: '-' }}</td>
                                            <td class="text-end pe-3">
                                                <button type="button" class="btn btn-phoenix-warning btn-sm" data-bs-toggle="modal" data-bs-target="#resolveStockLineModal-{{ $line->id }}">
                                                    Resolver
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endif

            <div class="card mb-4">
                <div class="card-header bg-body-tertiary">
                    <h5 class="mb-0">Movimentos de stock</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm fs-9 mb-0">
                            <thead class="bg-body-tertiary">
                                <tr>
                                    <th class="ps-3">Data</th>
                                    <th>Artigo</th>
                                    <th>Tipo</th>
                                    <th>Direcao</th>
                                    <th>Quantidade</th>
                                    <th>Custo unit.</th>
                                    <th class="pe-3">Utilizador</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($receipt->stockMovements as $movement)
                                    <tr>
                                        <td class="ps-3">{{ optional($movement->movement_date)->format('Y-m-d') ?? '-' }}</td>
                                        <td>
                                            @if ($movement->article)
                                                <span class="fw-semibold">{{ $movement->article->code }}</span> - {{ $movement->article->designation }}
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td>{{ $movement->type }}</td>
                                        <td>{{ $movement->direction }}</td>
                                        <td>{{ number_format((float) $movement->quantity, 3, ',', '.') }}</td>
                                        <td>{{ $movement->unit_cost !== null ? number_format((float) $movement->unit_cost, 4, ',', '.') : '-' }}</td>
                                        <td class="pe-3">{{ $movement->performer?->name ?? '-' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center py-4 text-body-tertiary">Sem movimentos de stock gerados.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-xxl-4">
            <div class="card mb-4">
                <div class="card-header bg-body-tertiary">
                    <h5 class="mb-0">Estado</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <div class="text-body-tertiary fs-9">Estado da rececao</div>
                        <div class="fw-semibold">{{ $statusLabels[$receipt->status] ?? $receipt->status }}</div>
                    </div>
                    <div class="mb-3">
                        <div class="text-body-tertiary fs-9">Estado da encomenda</div>
                        <div class="fw-semibold">{{ $purchaseOrderStatusLabels[$receipt->purchaseOrder?->status] ?? $receipt->purchaseOrder?->status ?? '-' }}</div>
                    </div>
                    <div class="mb-3">
                        <div class="text-body-tertiary fs-9">Fecho final</div>
                        <div class="fw-semibold">{{ $receipt->is_final ? 'Sim' : 'Nao' }}</div>
                    </div>
                    <div class="mb-3">
                        <div class="text-body-tertiary fs-9">Integrado em stock</div>
                        <div class="fw-semibold">{{ $receipt->stock_posted_at ? 'Sim' : 'Nao' }}</div>
                        <div class="text-body-tertiary fs-9">
                            {{ $receipt->stock_posted_at ? $receipt->stock_posted_at->format('Y-m-d H:i') : '-' }}
                        </div>
                    </div>
                    @if ($receipt->canCancel() && auth()->user()->can('company.purchase_order_receipts.delete'))
                        <form method="POST" action="{{ route('admin.purchase-order-receipts.cancel', $receipt->id) }}">
                            @csrf
                            <button type="submit" class="btn btn-phoenix-danger btn-sm w-100">Cancelar rascunho</button>
                        </form>
                    @endif
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header bg-body-tertiary">
                    <h5 class="mb-0">Rastreabilidade</h5>
                </div>
                <div class="card-body">
                    <div class="mb-2">
                        <div class="text-body-tertiary fs-9">RFQ origem</div>
                        <div class="fw-semibold">
                            @if ($receipt->purchaseOrder?->rfq)
                                <a href="{{ route('admin.rfqs.show', $receipt->purchaseOrder->rfq->id) }}">{{ $receipt->purchaseOrder->rfq->number }}</a>
                            @else
                                -
                            @endif
                        </div>
                    </div>
                    <div class="mb-0">
                        <div class="text-body-tertiary fs-9">Encomenda origem</div>
                        <div class="fw-semibold">
                            @if ($receipt->purchaseOrder)
                                <a href="{{ route('admin.purchase-orders.show', $receipt->purchaseOrder->id) }}">{{ $receipt->purchaseOrder->number }}</a>
                            @else
                                -
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @foreach ($unresolvedStockLines as $line)
        <div class="modal fade" id="resolveStockLineModal-{{ $line->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Resolver linha livre #{{ $line->line_order }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-subtle-info mb-4">
                            <div><span class="fw-semibold">Descricao:</span> {{ $line->description }}</div>
                            <div><span class="fw-semibold">Quantidade recebida:</span> {{ number_format((float) $line->received_quantity, 3, ',', '.') }} {{ $line->unit_name ?: '' }}</div>
                            <div><span class="fw-semibold">Origem:</span> Rececao {{ $receipt->number }} / Encomenda {{ $receipt->purchaseOrder?->number ?? '-' }}</div>
                        </div>

                        <div class="card mb-3">
                            <div class="card-header bg-body-tertiary">
                                <h6 class="mb-0">1) Associar a artigo existente</h6>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="{{ route('admin.purchase-order-receipts.lines.resolve', [$receipt->id, $line->id]) }}" class="row g-3">
                                    @csrf
                                    <input type="hidden" name="action" value="assign_existing">
                                    <div class="col-12">
                                        <label class="form-label">Artigo</label>
                                        <select name="article_id" class="form-select" required>
                                            <option value="">Selecionar artigo</option>
                                            @foreach ($articleResolutionOptions['existingArticles'] as $existingArticle)
                                                <option value="{{ $existingArticle->id }}">
                                                    {{ $existingArticle->code }} - {{ $existingArticle->designation }}{{ $existingArticle->moves_stock ? '' : ' (nao move stock)' }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-12 text-end">
                                        <button type="submit" class="btn btn-primary btn-sm">Associar artigo</button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        @can('company.articles.create')
                            <div class="card mb-3">
                                <div class="card-header bg-body-tertiary">
                                    <h6 class="mb-0">2) Criar novo artigo rapido</h6>
                                </div>
                                <div class="card-body">
                                    <form method="POST" action="{{ route('admin.purchase-order-receipts.lines.resolve', [$receipt->id, $line->id]) }}" class="row g-3">
                                        @csrf
                                        <input type="hidden" name="action" value="create_new">
                                        <div class="col-12 col-md-6">
                                            <label class="form-label">Designacao</label>
                                            <input type="text" name="designation" class="form-control" value="{{ old('designation', $line->description) }}" required maxlength="190">
                                        </div>
                                        <div class="col-12 col-md-6">
                                            <label class="form-label">Familia</label>
                                            <select name="product_family_id" class="form-select" required>
                                                <option value="">Selecionar familia</option>
                                                @foreach ($articleResolutionOptions['familyOptions'] as $family)
                                                    <option value="{{ $family->id }}">{{ $family->name }}{{ $family->family_code ? ' ['.$family->family_code.']' : '' }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-12 col-md-4">
                                            <label class="form-label">Categoria</label>
                                            <select name="category_id" class="form-select">
                                                <option value="">Default</option>
                                                @foreach ($articleResolutionOptions['categoryOptions'] as $category)
                                                    <option value="{{ $category->id }}" @selected((int) $articleResolutionOptions['defaults']['category_id'] === (int) $category->id)>{{ $category->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-12 col-md-4">
                                            <label class="form-label">Unidade</label>
                                            <select name="unit_id" class="form-select">
                                                <option value="">Default</option>
                                                @foreach ($articleResolutionOptions['unitOptions'] as $unit)
                                                    <option value="{{ $unit->id }}" @selected((int) $articleResolutionOptions['defaults']['unit_id'] === (int) $unit->id)>{{ $unit->code }} - {{ $unit->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-12 col-md-4">
                                            <label class="form-label">Taxa IVA</label>
                                            <select name="vat_rate_id" class="form-select" required>
                                                <option value="">Selecionar taxa</option>
                                                @foreach ($articleResolutionOptions['vatRateOptions'] as $vatRate)
                                                    <option value="{{ $vatRate->id }}">{{ $vatRate->name }} ({{ number_format((float) $vatRate->rate, 2, ',', '.') }}%)</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-12 col-md-4">
                                            <div class="form-check mt-2">
                                                <input type="hidden" name="moves_stock" value="0">
                                                <input type="checkbox" name="moves_stock" id="moves_stock_line_{{ $line->id }}" value="1" class="form-check-input" checked>
                                                <label class="form-check-label" for="moves_stock_line_{{ $line->id }}">Move stock</label>
                                            </div>
                                        </div>
                                        <div class="col-12 col-md-4">
                                            <div class="form-check mt-2">
                                                <input type="hidden" name="is_active" value="0">
                                                <input type="checkbox" name="is_active" id="is_active_line_{{ $line->id }}" value="1" class="form-check-input" checked>
                                                <label class="form-check-label" for="is_active_line_{{ $line->id }}">Ativo</label>
                                            </div>
                                        </div>
                                        <div class="col-12 text-end">
                                            <button type="submit" class="btn btn-primary btn-sm">Criar e associar</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        @endcan

                        <div class="card">
                            <div class="card-header bg-body-tertiary">
                                <h6 class="mb-0">3) Manter nao stockavel</h6>
                            </div>
                            <div class="card-body">
                                <p class="text-body-secondary mb-3">A linha mantem-se documental e nao gera movimentos de stock.</p>
                                <form method="POST" action="{{ route('admin.purchase-order-receipts.lines.resolve', [$receipt->id, $line->id]) }}">
                                    @csrf
                                    <input type="hidden" name="action" value="mark_non_stockable">
                                    <div class="text-end">
                                        <button type="submit" class="btn btn-phoenix-secondary btn-sm">Marcar nao stockavel</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endforeach

    @if ($errors->has('stock_resolution') && $unresolvedStockLines->isNotEmpty())
        @push('scripts')
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    const firstModal = document.getElementById('resolveStockLineModal-{{ $unresolvedStockLines->first()->id }}');
                    if (!firstModal || typeof bootstrap === 'undefined') {
                        return;
                    }

                    const modal = new bootstrap.Modal(firstModal);
                    modal.show();
                });
            </script>
        @endpush
    @endif
@endsection
