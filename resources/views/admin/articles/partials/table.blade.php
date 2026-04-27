<div class="mx-n4 px-4 mx-lg-n6 px-lg-6 bg-body-emphasis border-top border-bottom border-translucent position-relative top-1">
    <div class="table-responsive scrollbar mx-n1 px-1">
        <table class="table fs-9 mb-0">
            <thead>
                <tr>
                    <th class="white-space-nowrap align-middle ps-0" style="width: 86px;"></th>
                    <th class="white-space-nowrap align-middle ps-2" style="min-width: 260px;">ARTIGO</th>
                    <th class="align-middle text-end ps-4" style="width: 120px;">PRECO CUSTO</th>
                    <th class="align-middle text-end ps-4" style="width: 120px;">PRECO VENDA</th>
                    <th class="align-middle text-end ps-4" style="width: 120px;">STOCK ATUAL</th>
                    <th class="align-middle text-end ps-4" style="width: 130px;">ENCOMENDADO</th>
                    <th class="align-middle ps-4" style="width: 170px;">FAMILIA</th>
                    <th class="align-middle ps-4" style="width: 180px;">MARCA</th>
                    <th class="align-middle ps-4" style="width: 120px;">UNIDADE</th>
                    <th class="align-middle ps-4" style="width: 220px;">IVA</th>
                    <th class="align-middle ps-4" style="width: 120px;">ESTADO</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($articles as $article)
                    @php
                        $designation = trim((string) $article->designation);
                        $avatarLetter = $designation !== '' ? mb_strtoupper(mb_substr($designation, 0, 1)) : 'A';
                    @endphp
                    <tr class="position-static">
                        <td class="align-middle white-space-nowrap py-2 ps-0">
                            <a href="{{ route('admin.articles.show', $article->id) }}" class="d-inline-flex align-items-center justify-content-center border border-translucent rounded-2 text-body-emphasis text-decoration-none fw-semibold" style="width: 46px; height: 46px;">
                                {{ $avatarLetter }}
                            </a>
                        </td>
                        <td class="align-middle ps-2">
                            <a class="fw-semibold line-clamp-2 mb-0 text-decoration-none" href="{{ route('admin.articles.show', $article->id) }}">
                                {{ $article->designation }}
                            </a>
                            <div class="fs-10 text-body-tertiary mt-1">
                                <span class="fw-semibold">{{ $article->code }}</span>
                                @if ($article->ean)
                                    <span class="mx-1">&bull;</span>EAN {{ $article->ean }}
                                @endif
                                @if ($article->category?->name)
                                    <span class="mx-1">&bull;</span>{{ $article->category->name }}
                                @endif
                            </div>
                        </td>
                        <td class="align-middle text-end fw-bold text-body-tertiary ps-4 white-space-nowrap">
                            {{ $article->cost_price !== null ? number_format((float) $article->cost_price, 2, ',', '.').' €' : '-' }}
                        </td>
                        <td class="align-middle text-end fw-bold text-body-tertiary ps-4 white-space-nowrap">
                            {{ $article->sale_price !== null ? number_format((float) $article->sale_price, 2, ',', '.').' €' : '-' }}
                        </td>
                        <td class="align-middle text-end fw-semibold text-body-tertiary ps-4 white-space-nowrap">
                            {{ number_format((float) ($article->stock_quantity ?? 0), 2, ',', '.') }}
                        </td>
                        <td class="align-middle text-end fw-semibold text-body-tertiary ps-4 white-space-nowrap">
                            {{ number_format((float) ($article->stock_ordered_pending ?? 0), 2, ',', '.') }}
                        </td>
                        <td class="align-middle text-body-quaternary ps-4">
                            {{ $article->productFamily?->name ?? '-' }}
                        </td>
                        <td class="align-middle text-body-quaternary ps-4">
                            @if ($article->brand?->logo_path)
                                <img
                                    src="{{ route('admin.brands.logo.show', $article->brand->id) }}"
                                    alt="Logo {{ $article->brand->name }}"
                                    title="{{ $article->brand->name }}"
                                    style="display: block; width: auto; height: 34px; max-width: 120px; object-fit: contain; object-position: left center;"
                                >
                            @elseif ($article->brand?->name)
                                <span
                                    class="d-inline-block text-truncate"
                                    style="max-width: 120px;"
                                    title="{{ $article->brand->name }}"
                                >
                                    {{ \Illuminate\Support\Str::limit($article->brand->name, 18) }}
                                </span>
                            @else
                                -
                            @endif
                        </td>
                        <td class="align-middle white-space-nowrap ps-4">
                            <span class="badge badge-phoenix badge-phoenix-secondary">{{ $article->unit?->code ?? '-' }}</span>
                        </td>
                        <td class="align-middle ps-4">
                            @if ($article->vatRate)
                                <span class="fw-semibold">{{ $article->vatRate->name }}</span>
                                <span class="text-body-tertiary">({{ number_format((float) $article->vatRate->rate, 2) }}%)</span>
                            @else
                                -
                            @endif
                        </td>
                        <td class="align-middle ps-4">
                            @if ($article->is_active)
                                <span class="badge badge-phoenix badge-phoenix-success">Ativo</span>
                            @else
                                <span class="badge badge-phoenix badge-phoenix-secondary">Inativo</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="11" class="text-center py-5 text-body-tertiary">Sem artigos registados.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@if ($articles->hasPages())
    <div class="mt-3">
        {{ $articles->links() }}
    </div>
@endif
