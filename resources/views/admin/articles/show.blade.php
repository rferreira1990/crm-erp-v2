@extends('layouts.admin')

@section('title', 'Ficha de artigo')
@section('page_title', 'Ficha de artigo')
@section('page_subtitle', $article->code.' - '.$article->designation)

@section('page_actions')
    <a href="{{ route('admin.articles.index') }}" class="btn btn-phoenix-secondary btn-sm">Voltar</a>
    @can('company.articles.update')
        <a href="{{ route('admin.articles.edit', $article->id) }}" class="btn btn-primary btn-sm">Editar artigo</a>
        @if (! $canDeleteArticle && $article->is_active)
            <form method="POST" action="{{ route('admin.articles.deactivate', $article->id) }}" class="d-inline" data-confirm="Tem a certeza que pretende inativar este artigo?">
                @csrf
                <button type="submit" class="btn btn-phoenix-warning btn-sm">Inativar</button>
            </form>
        @endif
    @endcan
    @can('company.articles.delete')
        @if ($canDeleteArticle)
            <form method="POST" action="{{ route('admin.articles.destroy', $article->id) }}" class="d-inline" data-confirm="Tem a certeza que pretende apagar este artigo?">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-phoenix-danger btn-sm">Apagar artigo</button>
            </form>
        @endif
    @endcan
@endsection

@section('breadcrumbs')
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.articles.index') }}">Artigos</a></li>
        <li class="breadcrumb-item active" aria-current="page">{{ $article->code }}</li>
    </ol>
@endsection

@section('content')
    @if ($errors->any())
        <div class="alert alert-danger mb-4" role="alert">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="row g-4 mb-4">
        <div class="col-12 col-xxl-8">
            <div class="card mb-4">
                <div class="card-header bg-body-tertiary d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Resumo do artigo</h5>
                    @if ($article->is_active)
                        <span class="badge badge-phoenix badge-phoenix-success">Ativo</span>
                    @else
                        <span class="badge badge-phoenix badge-phoenix-secondary">Inativo</span>
                    @endif
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12 col-md-4"><div class="text-body-tertiary fs-9">Codigo</div><div class="fw-semibold">{{ $article->code }}</div></div>
                        <div class="col-12 col-md-8"><div class="text-body-tertiary fs-9">Designacao</div><div class="fw-semibold">{{ $article->designation }}</div></div>
                        <div class="col-12 col-md-3"><div class="text-body-tertiary fs-9">Abreviatura</div><div class="fw-semibold">{{ $article->abbreviation ?: '-' }}</div></div>
                        <div class="col-12 col-md-3"><div class="text-body-tertiary fs-9">EAN</div><div class="fw-semibold">{{ $article->ean ?: '-' }}</div></div>
                        <div class="col-12 col-md-3"><div class="text-body-tertiary fs-9">Familia</div><div class="fw-semibold">{{ $article->productFamily?->name ?? '-' }}</div></div>
                        <div class="col-12 col-md-3"><div class="text-body-tertiary fs-9">Categoria</div><div class="fw-semibold">{{ $article->category?->name ?? '-' }}</div></div>
                        <div class="col-12 col-md-3"><div class="text-body-tertiary fs-9">Marca</div><div class="fw-semibold">{{ $article->brand?->name ?? '-' }}</div></div>
                        <div class="col-12 col-md-3"><div class="text-body-tertiary fs-9">Unidade</div><div class="fw-semibold">{{ $article->unit?->code ?? '-' }}</div></div>
                        <div class="col-12 col-md-6"><div class="text-body-tertiary fs-9">IVA</div><div class="fw-semibold">{{ $article->vatRate ? $article->vatRate->name.' ('.number_format((float) $article->vatRate->rate, 2, ',', '.').'%)' : '-' }}</div></div>
                        <div class="col-12 col-md-6"><div class="text-body-tertiary fs-9">Motivo isencao IVA</div><div class="fw-semibold">{{ $article->vatExemptionReason ? $article->vatExemptionReason->code.' - '.$article->vatExemptionReason->name : '-' }}</div></div>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header bg-body-tertiary">
                    <h5 class="mb-0">Precos e descontos</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12 col-md-4"><div class="text-body-tertiary fs-9">Preco custo</div><div class="fw-semibold">{{ $article->cost_price !== null ? number_format((float) $article->cost_price, 4, ',', '.') : '-' }}</div></div>
                        <div class="col-12 col-md-4"><div class="text-body-tertiary fs-9">Preco venda</div><div class="fw-semibold">{{ $article->sale_price !== null ? number_format((float) $article->sale_price, 4, ',', '.') : '-' }}</div></div>
                        <div class="col-12 col-md-4"><div class="text-body-tertiary fs-9">Margem default</div><div class="fw-semibold">{{ $article->default_margin !== null ? number_format((float) $article->default_margin, 2, ',', '.').' %' : '-' }}</div></div>
                        <div class="col-12 col-md-4"><div class="text-body-tertiary fs-9">Desconto direto</div><div class="fw-semibold">{{ $article->direct_discount !== null ? number_format((float) $article->direct_discount, 2, ',', '.').' %' : '-' }}</div></div>
                        <div class="col-12 col-md-4"><div class="text-body-tertiary fs-9">Desconto maximo</div><div class="fw-semibold">{{ $article->max_discount !== null ? number_format((float) $article->max_discount, 2, ',', '.').' %' : '-' }}</div></div>
                        <div class="col-12 col-md-4"><div class="text-body-tertiary fs-9">Ultimo custo conhecido</div><div class="fw-semibold">{{ $purchaseSummary['lastKnownCost'] !== null ? number_format((float) $purchaseSummary['lastKnownCost'], 4, ',', '.') : '-' }}</div></div>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header bg-body-tertiary">
                    <h5 class="mb-0">Historico de movimentos</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm fs-9 mb-0">
                            <thead class="bg-body-tertiary">
                                <tr>
                                    <th class="ps-3">Data</th>
                                    <th>Tipo</th>
                                    <th>Direcao</th>
                                    <th>Quantidade</th>
                                    <th>Custo unit.</th>
                                    <th>Referencia</th>
                                    <th>Motivo</th>
                                    <th>Utilizador</th>
                                    <th class="pe-3">Notas</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($movements as $movement)
                                    <tr>
                                        <td class="ps-3">{{ optional($movement->movement_date)->format('Y-m-d') ?? '-' }}</td>
                                        <td>{{ \App\Models\StockMovement::typeLabels()[$movement->type] ?? $movement->type }}</td>
                                        <td>{{ \App\Models\StockMovement::directionLabels()[$movement->direction] ?? $movement->direction }}</td>
                                        <td>{{ number_format((float) $movement->quantity, 3, ',', '.') }}</td>
                                        <td>{{ $movement->unit_cost !== null ? number_format((float) $movement->unit_cost, 4, ',', '.') : '-' }}</td>
                                        <td>
                                            @if ($movement->reference_type === 'manual')
                                                Manual
                                            @else
                                                {{ $movement->reference_type }} #{{ $movement->reference_id }}
                                            @endif
                                        </td>
                                        <td>{{ $movement->reasonLabel() ?? '-' }}</td>
                                        <td>{{ $movement->performer?->name ?? '-' }}</td>
                                        <td class="pe-3">{{ $movement->notes ?: '-' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="text-center py-4 text-body-tertiary">Sem movimentos registados.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                @if ($movements->hasPages())
                    <div class="card-footer">{{ $movements->links() }}</div>
                @endif
            </div>
        </div>

        <div class="col-12 col-xxl-4">
            <div class="card mb-4">
                <div class="card-header bg-body-tertiary">
                    <h5 class="mb-0">Stock</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <div class="text-body-tertiary fs-9">Stock atual</div>
                        <div class="fw-bold fs-8 {{ $belowMinimum ? 'text-danger' : '' }}">{{ number_format((float) $article->stock_quantity, 3, ',', '.') }}</div>
                    </div>
                    <div class="mb-2"><span class="text-body-tertiary fs-9">Move stock:</span> <span class="fw-semibold">{{ $article->moves_stock ? 'Sim' : 'Nao' }}</span></div>
                    <div class="mb-2"><span class="text-body-tertiary fs-9">Alerta stock:</span> <span class="fw-semibold">{{ $article->stock_alert_enabled ? 'Ativo' : 'Inativo' }}</span></div>
                    <div class="mb-2"><span class="text-body-tertiary fs-9">Stock minimo:</span> <span class="fw-semibold">{{ $article->minimum_stock !== null ? number_format((float) $article->minimum_stock, 3, ',', '.') : '-' }}</span></div>
                    <div class="mb-0">
                        <span class="text-body-tertiary fs-9">Estado minimo:</span>
                        @if ($belowMinimum)
                            <span class="badge badge-phoenix badge-phoenix-danger">Abaixo do minimo</span>
                        @else
                            <span class="badge badge-phoenix badge-phoenix-success">OK</span>
                        @endif
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header bg-body-tertiary">
                    <h5 class="mb-0">Resumo de compras</h5>
                </div>
                <div class="card-body">
                    <div class="mb-2">
                        <div class="text-body-tertiary fs-9">Ultima rececao</div>
                        <div class="fw-semibold">
                            @if ($purchaseSummary['lastReceiptMovement'])
                                {{ optional($purchaseSummary['lastReceiptMovement']->movement_date)->format('Y-m-d') }} ({{ number_format((float) $purchaseSummary['lastReceiptMovement']->quantity, 3, ',', '.') }})
                            @else
                                -
                            @endif
                        </div>
                    </div>
                    <div class="mb-0">
                        <div class="text-body-tertiary fs-9">Entradas ultimos 30 dias</div>
                        <div class="fw-semibold">{{ $purchaseSummary['recentEntriesCount'] }}</div>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header bg-body-tertiary">
                    <h5 class="mb-0">Imagens</h5>
                </div>
                <div class="card-body">
                    @forelse ($article->images as $image)
                        <div class="d-flex justify-content-between align-items-center border rounded p-2 mb-2">
                            <a href="{{ route('admin.articles.images.show', ['article' => $article->id, 'articleImage' => $image->id]) }}" target="_blank" rel="noopener noreferrer">{{ $image->original_name }}</a>
                            @if ($image->is_primary)
                                <span class="badge badge-phoenix badge-phoenix-success">Primaria</span>
                            @endif
                        </div>
                    @empty
                        <div class="text-body-tertiary">Sem imagens.</div>
                    @endforelse
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header bg-body-tertiary">
                    <h5 class="mb-0">Ficheiros</h5>
                </div>
                <div class="card-body">
                    @forelse ($article->files as $file)
                        <div class="border rounded p-2 mb-2">
                            <a href="{{ route('admin.articles.files.download', ['article' => $article->id, 'articleFile' => $file->id]) }}" class="fw-semibold">{{ $file->original_name }}</a>
                            <div class="small text-body-tertiary">{{ $file->mime_type ?? '-' }}</div>
                        </div>
                    @empty
                        <div class="text-body-tertiary">Sem ficheiros.</div>
                    @endforelse
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header bg-body-tertiary">
                    <h5 class="mb-0">Notas</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <div class="text-body-tertiary fs-9">Notas internas</div>
                        <div>{{ $article->internal_notes ?: '-' }}</div>
                    </div>
                    <div class="mb-0">
                        <div class="text-body-tertiary fs-9">Notas impressao</div>
                        <div>{{ $article->print_notes ?: '-' }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
