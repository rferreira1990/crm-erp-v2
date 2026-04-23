<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreArticleRequest;
use App\Http\Requests\Admin\UpdateArticleRequest;
use App\Models\Article;
use App\Models\ArticleFile;
use App\Models\ArticleImage;
use App\Models\Brand;
use App\Models\Category;
use App\Models\ProductFamily;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Models\VatExemptionReason;
use App\Models\VatRate;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ArticleController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Article::class);

        $companyId = (int) $request->user()->company_id;
        $search = trim((string) $request->query('q', ''));

        $articles = Article::query()
            ->where('company_id', $companyId)
            ->with([
                'productFamily:id,name,family_code',
                'category:id,name',
                'brand:id,name',
                'unit:id,code',
                'vatRate:id,name,rate,is_exempt',
            ])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($searchQuery) use ($search): void {
                    $searchQuery->where('code', 'like', '%'.$search.'%')
                        ->orWhere('designation', 'like', '%'.$search.'%')
                        ->orWhere('ean', 'like', '%'.$search.'%');
                });
            })
            ->orderBy('designation')
            ->paginate(20)
            ->withQueryString();

        return view('admin.articles.index', [
            'articles' => $articles,
            'filters' => [
                'q' => $search,
            ],
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Article::class);

        $companyId = (int) $request->user()->company_id;

        return view('admin.articles.create', [
            'defaults' => [
                'category_id' => Article::defaultCategoryIdForCompany($companyId),
                'unit_id' => Article::defaultUnitIdForCompany($companyId),
            ],
            ...$this->buildFormOptions($companyId),
        ]);
    }

    public function store(StoreArticleRequest $request): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;
        $data = $request->validated();

        $vatRate = $this->findEnabledVatRateOrFail($companyId, (int) $data['vat_rate_id']);
        $article = Article::createWithGeneratedCode($companyId, $this->normalizeArticlePayload($data, $vatRate));

        $this->storeArticleImages($article, $request->file('images', []));
        $this->storeArticleFiles($article, $request->file('documents', []));

        Log::info('Article created', [
            'context' => 'company_articles',
            'article_id' => $article->id,
            'company_id' => $companyId,
            'created_by' => $request->user()->id,
            'code' => $article->code,
        ]);

        return redirect()
            ->route('admin.articles.index')
            ->with('status', 'Artigo criado com sucesso.');
    }

    public function edit(Request $request, int $article): View
    {
        $companyId = (int) $request->user()->company_id;
        $articleModel = $this->findCompanyArticleOrFail($companyId, $article);
        $this->authorize('update', $articleModel);

        $articleModel->load([
            'images' => fn ($query) => $query->orderByDesc('is_primary')->orderBy('sort_order'),
            'files' => fn ($query) => $query->latest(),
        ]);

        return view('admin.articles.edit', [
            'article' => $articleModel,
            ...$this->buildFormOptions(
                $companyId,
                $articleModel->vat_rate_id,
                $articleModel->vat_exemption_reason_id,
                $articleModel->product_family_id
            ),
        ]);
    }

    public function update(UpdateArticleRequest $request, int $article): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;
        $articleModel = $this->findCompanyArticleOrFail($companyId, $article);
        $this->authorize('update', $articleModel);

        $data = $request->validated();
        $vatRate = $this->findEnabledVatRateOrFail($companyId, (int) $data['vat_rate_id']);

        $articleModel->forceFill($this->normalizeArticlePayload($data, $vatRate))->save();

        $this->storeArticleImages($articleModel, $request->file('images', []));
        $this->storeArticleFiles($articleModel, $request->file('documents', []));

        Log::info('Article updated', [
            'context' => 'company_articles',
            'article_id' => $articleModel->id,
            'company_id' => $companyId,
            'updated_by' => $request->user()->id,
            'code' => $articleModel->code,
        ]);

        return redirect()
            ->route('admin.articles.edit', $articleModel->id)
            ->with('status', 'Artigo atualizado com sucesso.');
    }

    public function destroy(Request $request, int $article): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;
        $articleModel = $this->findCompanyArticleOrFail($companyId, $article);
        $this->authorize('delete', $articleModel);

        foreach ($articleModel->images()->get(['file_path']) as $image) {
            $this->deleteFromDisk($image->file_path);
        }

        foreach ($articleModel->files()->get(['file_path']) as $file) {
            $this->deleteFromDisk($file->file_path);
        }

        $articleModel->delete();

        Log::info('Article deleted', [
            'context' => 'company_articles',
            'article_id' => $articleModel->id,
            'company_id' => $companyId,
            'deleted_by' => $request->user()->id,
            'code' => $articleModel->code,
        ]);

        return redirect()
            ->route('admin.articles.index')
            ->with('status', 'Artigo eliminado com sucesso.');
    }

    public function destroyImage(Request $request, int $article, int $articleImage): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;
        $articleModel = $this->findCompanyArticleOrFail($companyId, $article);
        $this->authorize('update', $articleModel);

        $image = ArticleImage::query()
            ->where('company_id', $companyId)
            ->where('article_id', $articleModel->id)
            ->whereKey($articleImage)
            ->firstOrFail();

        $wasPrimary = (bool) $image->is_primary;
        $this->deleteFromDisk($image->file_path);
        $image->delete();

        if ($wasPrimary) {
            $replacement = $articleModel->images()
                ->orderBy('sort_order')
                ->orderBy('id')
                ->first();

            if ($replacement !== null) {
                $replacement->forceFill(['is_primary' => true])->save();
            }
        }

        return redirect()
            ->route('admin.articles.edit', $articleModel->id)
            ->with('status', 'Imagem removida com sucesso.');
    }

    public function destroyFile(Request $request, int $article, int $articleFile): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;
        $articleModel = $this->findCompanyArticleOrFail($companyId, $article);
        $this->authorize('update', $articleModel);

        $file = ArticleFile::query()
            ->where('company_id', $companyId)
            ->where('article_id', $articleModel->id)
            ->whereKey($articleFile)
            ->firstOrFail();

        $this->deleteFromDisk($file->file_path);
        $file->delete();

        return redirect()
            ->route('admin.articles.edit', $articleModel->id)
            ->with('status', 'Ficheiro removido com sucesso.');
    }

    private function findCompanyArticleOrFail(int $companyId, int $articleId): Article
    {
        return Article::query()
            ->where('company_id', $companyId)
            ->whereKey($articleId)
            ->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeArticlePayload(array $data, VatRate $vatRate): array
    {
        $movesStock = (bool) ($data['moves_stock'] ?? false);
        $stockAlertEnabled = (bool) ($data['stock_alert_enabled'] ?? false);

        if (! $movesStock) {
            $stockAlertEnabled = false;
            $data['minimum_stock'] = null;
        } elseif (! $stockAlertEnabled) {
            $data['minimum_stock'] = null;
        }

        $data['stock_alert_enabled'] = $stockAlertEnabled;

        if (! $vatRate->is_exempt) {
            $data['vat_exemption_reason_id'] = null;
        }

        return $data;
    }

    /**
     * @return array{
     *   familyOptions: array<int, array{id:int,label:string}>,
     *   categoryOptions: \Illuminate\Support\Collection<int, Category>,
     *   unitOptions: \Illuminate\Support\Collection<int, Unit>,
     *   brandOptions: \Illuminate\Support\Collection<int, Brand>,
     *   vatRateOptions: \Illuminate\Support\Collection<int, VatRate>,
     *   vatExemptionReasonOptions: \Illuminate\Support\Collection<int, VatExemptionReason>
     * }
     */
    private function buildFormOptions(
        int $companyId,
        ?int $includeVatRateId = null,
        ?int $includeReasonId = null,
        ?int $includeFamilyId = null
    ): array {
        return [
            'familyOptions' => $this->buildFamilyOptions($companyId, $includeFamilyId),
            'categoryOptions' => Category::query()
                ->visibleToCompany($companyId)
                ->orderBy('name')
                ->get(['id', 'name', 'company_id']),
            'unitOptions' => Unit::query()
                ->visibleToCompany($companyId)
                ->orderBy('name')
                ->get(['id', 'code', 'name', 'company_id']),
            'brandOptions' => Brand::query()
                ->where('company_id', $companyId)
                ->orderBy('name')
                ->get(['id', 'name']),
            'vatRateOptions' => $this->enabledVatRates($companyId, $includeVatRateId),
            'vatExemptionReasonOptions' => $this->enabledVatExemptionReasons($companyId, $includeReasonId),
        ];
    }

    public function show(Request $request, int $article): View
    {
        $companyId = (int) $request->user()->company_id;
        $articleModel = $this->findCompanyArticleOrFail($companyId, $article);
        $this->authorize('view', $articleModel);

        $articleModel->load([
            'productFamily:id,name,family_code',
            'category:id,name',
            'brand:id,name',
            'unit:id,code,name',
            'vatRate:id,name,rate,is_exempt',
            'vatExemptionReason:id,code,name',
            'images' => fn ($query) => $query->orderByDesc('is_primary')->orderBy('sort_order'),
            'files' => fn ($query) => $query->latest(),
        ]);

        $movements = StockMovement::query()
            ->forCompany($companyId)
            ->where('article_id', (int) $articleModel->id)
            ->with([
                'performer:id,name',
            ])
            ->orderByDesc('movement_date')
            ->orderByDesc('id')
            ->paginate(15, ['*'], 'movements_page')
            ->withQueryString();

        $lastReceiptMovement = StockMovement::query()
            ->forCompany($companyId)
            ->where('article_id', (int) $articleModel->id)
            ->where('type', StockMovement::TYPE_PURCHASE_RECEIPT)
            ->where('direction', StockMovement::DIRECTION_IN)
            ->orderByDesc('movement_date')
            ->orderByDesc('id')
            ->first();

        $lastKnownCost = StockMovement::query()
            ->forCompany($companyId)
            ->where('article_id', (int) $articleModel->id)
            ->where('direction', StockMovement::DIRECTION_IN)
            ->whereNotNull('unit_cost')
            ->orderByDesc('movement_date')
            ->orderByDesc('id')
            ->value('unit_cost');

        $recentEntriesCount = StockMovement::query()
            ->forCompany($companyId)
            ->where('article_id', (int) $articleModel->id)
            ->where('direction', StockMovement::DIRECTION_IN)
            ->whereDate('movement_date', '>=', now()->subDays(30)->toDateString())
            ->count();

        $belowMinimum = $articleModel->moves_stock
            && $articleModel->stock_alert_enabled
            && $articleModel->minimum_stock !== null
            && (float) $articleModel->stock_quantity < (float) $articleModel->minimum_stock;

        return view('admin.articles.show', [
            'article' => $articleModel,
            'movements' => $movements,
            'belowMinimum' => $belowMinimum,
            'purchaseSummary' => [
                'lastReceiptMovement' => $lastReceiptMovement,
                'lastKnownCost' => $lastKnownCost,
                'recentEntriesCount' => $recentEntriesCount,
            ],
        ]);
    }

    /**
     * @return array<int, array{id:int,label:string}>
     */
    private function buildFamilyOptions(int $companyId, ?int $includeFamilyId = null): array
    {
        $families = ProductFamily::query()
            ->visibleToCompany($companyId)
            ->where(function ($query) use ($includeFamilyId): void {
                $query->whereNotNull('family_code');

                if ($includeFamilyId !== null) {
                    $query->orWhere('id', $includeFamilyId);
                }
            })
            ->get(['id', 'parent_id', 'name', 'family_code']);

        $familyById = $families->keyBy('id');
        $pathCache = [];

        $buildPath = function (int $familyId) use (&$pathCache, $familyById): string {
            if (isset($pathCache[$familyId])) {
                return $pathCache[$familyId];
            }

            $parts = [];
            $seen = [];
            $cursorId = $familyId;
            $depth = 0;

            while ($cursorId > 0 && $depth < 50) {
                if (isset($seen[$cursorId])) {
                    break;
                }

                $seen[$cursorId] = true;

                /** @var ProductFamily|null $family */
                $family = $familyById->get($cursorId);

                if ($family === null) {
                    break;
                }

                $parts[] = $family->name;
                $cursorId = $family->parent_id !== null
                    ? (int) $family->parent_id
                    : 0;
                $depth++;
            }

            $path = implode(' > ', array_reverse($parts));
            $pathCache[$familyId] = $path;

            return $path;
        };

        return $families
            ->map(function (ProductFamily $family) use ($buildPath): array {
                $pathLabel = $buildPath($family->id);
                $code = $family->family_code !== null ? ' ['.$family->family_code.']' : '';

                return [
                    'id' => $family->id,
                    'label' => $pathLabel.$code,
                ];
            })
            ->sortBy('label', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, VatRate>
     */
    private function enabledVatRates(int $companyId, ?int $includeVatRateId = null): Collection
    {
        return VatRate::query()
            ->with([
                'companyOverrides' => fn ($query) => $query->where('company_id', $companyId),
            ])
            ->visibleToCompany($companyId)
            ->get()
            ->filter(function (VatRate $vatRate) use ($companyId, $includeVatRateId): bool {
                if ($includeVatRateId !== null && $vatRate->id === $includeVatRateId) {
                    return true;
                }

                return $vatRate->isEnabledForCompany($companyId);
            })
            ->sortBy([
                ['region', 'asc'],
                ['is_exempt', 'asc'],
                ['rate', 'desc'],
                ['name', 'asc'],
            ])
            ->values();
    }

    /**
     * @return Collection<int, VatExemptionReason>
     */
    private function enabledVatExemptionReasons(int $companyId, ?int $includeReasonId = null): Collection
    {
        return VatExemptionReason::query()
            ->with([
                'companyOverrides' => fn ($query) => $query->where('company_id', $companyId),
            ])
            ->visibleToCompany($companyId)
            ->orderBy('code')
            ->get()
            ->filter(function (VatExemptionReason $reason) use ($companyId, $includeReasonId): bool {
                if ($includeReasonId !== null && $reason->id === $includeReasonId) {
                    return true;
                }

                return $reason->isEnabledForCompany($companyId);
            })
            ->values();
    }

    private function findEnabledVatRateOrFail(int $companyId, int $vatRateId): VatRate
    {
        /** @var VatRate $vatRate */
        $vatRate = VatRate::query()
            ->with([
                'companyOverrides' => fn ($query) => $query->where('company_id', $companyId),
            ])
            ->visibleToCompany($companyId)
            ->whereKey($vatRateId)
            ->firstOrFail();

        if (! $vatRate->isEnabledForCompany($companyId)) {
            abort(422, 'A taxa de IVA selecionada nao esta ativa para a empresa.');
        }

        return $vatRate;
    }

    /**
     * @param array<int, UploadedFile>|UploadedFile|null $images
     */
    private function storeArticleImages(Article $article, array|UploadedFile|null $images): void
    {
        if ($images instanceof UploadedFile) {
            $images = [$images];
        }

        if (! is_array($images) || $images === []) {
            return;
        }

        $companyId = (int) $article->company_id;
        $directory = 'articles/'.$companyId.'/'.$article->id.'/images';
        $nextSortOrder = ((int) $article->images()->max('sort_order')) + 1;
        $hasPrimary = $article->images()->where('is_primary', true)->exists();

        foreach ($images as $image) {
            if (! $image instanceof UploadedFile) {
                continue;
            }

            $storedPath = $image->storeAs(
                $directory,
                Str::uuid()->toString().'.'.$image->getClientOriginalExtension(),
                'local'
            );

            $isPrimary = ! $hasPrimary;
            if ($isPrimary) {
                $hasPrimary = true;
            }

            ArticleImage::query()->create([
                'article_id' => $article->id,
                'company_id' => $companyId,
                'original_name' => $image->getClientOriginalName(),
                'file_path' => $storedPath,
                'mime_type' => $image->getClientMimeType(),
                'file_size' => $image->getSize(),
                'sort_order' => $nextSortOrder,
                'is_primary' => $isPrimary,
            ]);

            $nextSortOrder++;
        }
    }

    /**
     * @param array<int, UploadedFile>|UploadedFile|null $files
     */
    private function storeArticleFiles(Article $article, array|UploadedFile|null $files): void
    {
        if ($files instanceof UploadedFile) {
            $files = [$files];
        }

        if (! is_array($files) || $files === []) {
            return;
        }

        $companyId = (int) $article->company_id;
        $directory = 'articles/'.$companyId.'/'.$article->id.'/files';

        foreach ($files as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $storedPath = $file->storeAs(
                $directory,
                Str::uuid()->toString().'.'.$file->getClientOriginalExtension(),
                'local'
            );

            ArticleFile::query()->create([
                'article_id' => $article->id,
                'company_id' => $companyId,
                'original_name' => $file->getClientOriginalName(),
                'file_path' => $storedPath,
                'mime_type' => $file->getClientMimeType(),
                'file_size' => $file->getSize(),
            ]);
        }
    }

    private function deleteFromDisk(?string $path): void
    {
        if (! $path) {
            return;
        }

        Storage::disk('local')->delete($path);
    }
}
