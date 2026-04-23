<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PostConstructionSiteMaterialUsageRequest;
use App\Http\Requests\Admin\StoreConstructionSiteMaterialUsageRequest;
use App\Http\Requests\Admin\UpdateConstructionSiteMaterialUsageRequest;
use App\Models\Article;
use App\Models\ConstructionSite;
use App\Models\ConstructionSiteMaterialUsage;
use App\Services\Admin\ConstructionSiteMaterialUsageService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ConstructionSiteMaterialUsageController extends Controller
{
    public function __construct(
        private readonly ConstructionSiteMaterialUsageService $materialUsageService
    ) {
    }

    public function index(Request $request, int $constructionSite): View
    {
        $this->authorize('viewAny', ConstructionSiteMaterialUsage::class);

        $companyId = (int) $request->user()->company_id;
        $site = $this->findCompanyConstructionSiteOrFail($companyId, $constructionSite);
        $this->authorize('view', $site);

        $usages = ConstructionSiteMaterialUsage::query()
            ->forCompany($companyId)
            ->where('construction_site_id', $site->id)
            ->with([
                'creator:id,name',
            ])
            ->withCount('items')
            ->orderByDesc('usage_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.construction-site-material-usages.index', [
            'site' => $site,
            'usages' => $usages,
            'statusLabels' => ConstructionSiteMaterialUsage::statusLabels(),
        ]);
    }

    public function create(Request $request, int $constructionSite): View
    {
        $this->authorize('create', ConstructionSiteMaterialUsage::class);

        $companyId = (int) $request->user()->company_id;
        $site = $this->findCompanyConstructionSiteOrFail($companyId, $constructionSite);
        $this->authorize('view', $site);

        return view('admin.construction-site-material-usages.create', [
            'site' => $site,
            ...$this->buildFormOptions($companyId),
        ]);
    }

    public function store(
        StoreConstructionSiteMaterialUsageRequest $request,
        int $constructionSite
    ): RedirectResponse {
        $this->authorize('create', ConstructionSiteMaterialUsage::class);

        $companyId = (int) $request->user()->company_id;
        $site = $this->findCompanyConstructionSiteOrFail($companyId, $constructionSite);
        $this->authorize('view', $site);

        $usage = $this->materialUsageService->createDraft(
            constructionSite: $site,
            createdBy: (int) $request->user()->id,
            payload: $request->validated()
        );

        return redirect()
            ->route('admin.construction-sites.material-usages.show', [$site->id, $usage->id])
            ->with('status', 'Consumo de material registado em rascunho com sucesso.');
    }

    public function show(Request $request, int $constructionSite, int $constructionSiteMaterialUsage): View
    {
        $companyId = (int) $request->user()->company_id;
        $site = $this->findCompanyConstructionSiteOrFail($companyId, $constructionSite);
        $usage = $this->findCompanyMaterialUsageOrFail($companyId, $site->id, $constructionSiteMaterialUsage);
        $this->authorize('view', $usage);

        $usage->load([
            'creator:id,name',
            'items' => fn ($query) => $query
                ->orderBy('id')
                ->with('article:id,code,designation'),
            'stockMovements' => fn ($query) => $query
                ->with([
                    'article:id,code,designation',
                    'performer:id,name',
                ])
                ->orderByDesc('movement_date')
                ->orderByDesc('id'),
        ]);

        return view('admin.construction-site-material-usages.show', [
            'site' => $site,
            'usage' => $usage,
            'statusLabels' => ConstructionSiteMaterialUsage::statusLabels(),
        ]);
    }

    public function edit(Request $request, int $constructionSite, int $constructionSiteMaterialUsage): View|RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;
        $site = $this->findCompanyConstructionSiteOrFail($companyId, $constructionSite);
        $usage = $this->findCompanyMaterialUsageOrFail($companyId, $site->id, $constructionSiteMaterialUsage);
        $this->authorize('update', $usage);

        if (! $usage->isEditable()) {
            return redirect()
                ->route('admin.construction-sites.material-usages.show', [$site->id, $usage->id])
                ->withErrors([
                    'usage' => 'Apenas consumos em rascunho podem ser editados.',
                ]);
        }

        $usage->load([
            'items' => fn ($query) => $query->orderBy('id'),
        ]);

        $includeArticleIds = $usage->items
            ->pluck('article_id')
            ->filter(fn ($articleId): bool => (int) $articleId > 0)
            ->map(fn ($articleId): int => (int) $articleId)
            ->values()
            ->all();

        return view('admin.construction-site-material-usages.edit', [
            'site' => $site,
            'usage' => $usage,
            ...$this->buildFormOptions($companyId, $includeArticleIds),
        ]);
    }

    public function update(
        UpdateConstructionSiteMaterialUsageRequest $request,
        int $constructionSite,
        int $constructionSiteMaterialUsage
    ): RedirectResponse {
        $companyId = (int) $request->user()->company_id;
        $site = $this->findCompanyConstructionSiteOrFail($companyId, $constructionSite);
        $usage = $this->findCompanyMaterialUsageOrFail($companyId, $site->id, $constructionSiteMaterialUsage);
        $this->authorize('update', $usage);

        $updated = $this->materialUsageService->updateDraft($usage, $request->validated());

        return redirect()
            ->route('admin.construction-sites.material-usages.show', [$site->id, $updated->id])
            ->with('status', 'Consumo de material atualizado com sucesso.');
    }

    public function post(
        PostConstructionSiteMaterialUsageRequest $request,
        int $constructionSite,
        int $constructionSiteMaterialUsage
    ): RedirectResponse {
        $companyId = (int) $request->user()->company_id;
        $site = $this->findCompanyConstructionSiteOrFail($companyId, $constructionSite);
        $usage = $this->findCompanyMaterialUsageOrFail($companyId, $site->id, $constructionSiteMaterialUsage);
        $this->authorize('post', $usage);

        $posted = $this->materialUsageService->post(
            usage: $usage,
            performedBy: (int) $request->user()->id
        );

        return redirect()
            ->route('admin.construction-sites.material-usages.show', [$site->id, $posted->id])
            ->with('status', 'Consumo de material confirmado e integrado em stock.');
    }

    public function cancel(Request $request, int $constructionSite, int $constructionSiteMaterialUsage): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;
        $site = $this->findCompanyConstructionSiteOrFail($companyId, $constructionSite);
        $usage = $this->findCompanyMaterialUsageOrFail($companyId, $site->id, $constructionSiteMaterialUsage);
        $this->authorize('delete', $usage);

        $cancelled = $this->materialUsageService->cancelDraft($usage);

        return redirect()
            ->route('admin.construction-sites.material-usages.show', [$site->id, $cancelled->id])
            ->with('status', 'Registo de consumo cancelado com sucesso.');
    }

    private function findCompanyConstructionSiteOrFail(int $companyId, int $siteId): ConstructionSite
    {
        return ConstructionSite::query()
            ->forCompany($companyId)
            ->whereKey($siteId)
            ->firstOrFail();
    }

    private function findCompanyMaterialUsageOrFail(int $companyId, int $siteId, int $usageId): ConstructionSiteMaterialUsage
    {
        return ConstructionSiteMaterialUsage::query()
            ->forCompany($companyId)
            ->where('construction_site_id', $siteId)
            ->whereKey($usageId)
            ->firstOrFail();
    }

    /**
     * @param array<int, int> $includeArticleIds
     * @return array{
     *   articleOptions:\Illuminate\Support\Collection<int, Article>
     * }
     */
    private function buildFormOptions(int $companyId, array $includeArticleIds = []): array
    {
        return [
            'articleOptions' => Article::query()
                ->forCompany($companyId)
                ->where(function ($query) use ($includeArticleIds): void {
                    $query->where(function ($activeQuery): void {
                        $activeQuery->where('is_active', true)
                            ->where('moves_stock', true);
                    });

                    if ($includeArticleIds !== []) {
                        $query->orWhereIn('id', $includeArticleIds);
                    }
                })
                ->with('unit:id,name')
                ->orderBy('designation')
                ->get([
                    'id',
                    'code',
                    'designation',
                    'unit_id',
                    'stock_quantity',
                    'cost_price',
                    'moves_stock',
                ]),
        ];
    }
}
