<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCategoryRequest;
use App\Http\Requests\Admin\UpdateCategoryRequest;
use App\Models\Category;
use Illuminate\Support\Collection;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CategoryController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Category::class);

        $companyId = (int) $request->user()->company_id;
        $search = trim((string) $request->query('q', ''));

        $categories = Category::query()
            ->with(['parent.parent.parent.parent'])
            ->visibleToCompany($companyId)
            ->when($search !== '', function ($query) use ($search): void {
                $query->where('name', 'like', '%'.$search.'%');
            })
            ->orderByDesc('is_system')
            ->orderByRaw('CASE WHEN parent_id IS NULL THEN 0 ELSE 1 END')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.categories.index', [
            'categories' => $categories,
            'hierarchyLabels' => $this->buildHierarchyLabels(
                $categories->getCollection(),
                $companyId
            ),
            'filters' => [
                'q' => $search,
            ],
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Category::class);

        $companyId = (int) request()->user()->company_id;

        return view('admin.categories.create', [
            'parentOptions' => $this->buildParentOptions($companyId),
        ]);
    }

    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $category = Category::query()->create([
            'company_id' => $request->user()->company_id,
            'is_system' => false,
            'name' => $data['name'],
            'parent_id' => $data['parent_id'] ?? null,
        ]);

        Log::info('Company category created', [
            'context' => 'company_categories',
            'category_id' => $category->id,
            'company_id' => $category->company_id,
            'created_by' => $request->user()->id,
            'name' => $category->name,
        ]);

        return redirect()
            ->route('admin.categories.index')
            ->with('status', 'Categoria criada com sucesso.');
    }

    public function edit(Request $request, int $category): View
    {
        $companyId = (int) $request->user()->company_id;
        $categoryModel = $this->findVisibleCategoryOrFail($companyId, $category);
        $this->authorize('update', $categoryModel);

        return view('admin.categories.edit', [
            'category' => $categoryModel,
            'parentOptions' => $this->buildParentOptions(
                $companyId,
                $categoryModel->id
            ),
        ]);
    }

    public function update(UpdateCategoryRequest $request, int $category): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;
        $categoryModel = $this->findVisibleCategoryOrFail($companyId, $category);
        $this->authorize('update', $categoryModel);

        $data = $request->validated();

        $categoryModel->forceFill([
            'name' => $data['name'],
            'parent_id' => $data['parent_id'] ?? null,
        ])->save();

        Log::info('Company category updated', [
            'context' => 'company_categories',
            'category_id' => $categoryModel->id,
            'company_id' => $categoryModel->company_id,
            'updated_by' => $request->user()->id,
            'name' => $categoryModel->name,
        ]);

        return redirect()
            ->route('admin.categories.index')
            ->with('status', 'Categoria atualizada com sucesso.');
    }

    public function destroy(Request $request, int $category): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;
        $categoryModel = $this->findVisibleCategoryOrFail($companyId, $category);
        $this->authorize('delete', $categoryModel);

        if ($this->isCategoryInUse($categoryModel)) {
            return back()->withErrors([
                'category' => 'Nao e possivel eliminar a categoria porque esta em uso.',
            ]);
        }

        if ($categoryModel->children()->exists()) {
            return back()->withErrors([
                'category' => 'Nao e possivel eliminar a categoria porque tem subcategorias associadas.',
            ]);
        }

        $categoryModel->delete();

        Log::info('Company category deleted', [
            'context' => 'company_categories',
            'category_id' => $categoryModel->id,
            'company_id' => $categoryModel->company_id,
            'deleted_by' => $request->user()->id,
            'name' => $categoryModel->name,
        ]);

        return redirect()
            ->route('admin.categories.index')
            ->with('status', 'Categoria eliminada com sucesso.');
    }

    private function findVisibleCategoryOrFail(int $companyId, int $categoryId): Category
    {
        return Category::query()
            ->visibleToCompany($companyId)
            ->whereKey($categoryId)
            ->firstOrFail();
    }

    private function isCategoryInUse(Category $category): bool
    {
        // Future extension point: return true when related records (e.g. products/items) exist.
        return false;
    }

    /**
     * @return array<int, string>
     */
    private function buildHierarchyLabels(Collection $pageCategories, int $companyId): array
    {
        $map = Category::query()
            ->visibleToCompany($companyId)
            ->get(['id', 'parent_id', 'name'])
            ->keyBy('id');

        $labelCache = [];

        $buildLabel = function (int $categoryId) use (&$buildLabel, &$labelCache, $map): string {
            if (isset($labelCache[$categoryId])) {
                return $labelCache[$categoryId];
            }

            /** @var Category|null $category */
            $category = $map->get($categoryId);

            if ($category === null) {
                return '';
            }

            if ($category->parent_id === null) {
                return $labelCache[$categoryId] = $category->name;
            }

            $parentLabel = $buildLabel((int) $category->parent_id);

            return $labelCache[$categoryId] = $parentLabel !== ''
                ? $parentLabel.' > '.$category->name
                : $category->name;
        };

        $labels = [];

        foreach ($pageCategories as $category) {
            $labels[$category->id] = $buildLabel((int) $category->id);
        }

        return $labels;
    }

    /**
     * @return array<int, array{id:int,label:string}>
     */
    private function buildParentOptions(int $companyId, ?int $excludeCategoryId = null): array
    {
        $categories = Category::query()
            ->visibleToCompany($companyId)
            ->get(['id', 'parent_id', 'name']);

        $parentById = $categories
            ->mapWithKeys(fn (Category $category): array => [$category->id => $category->parent_id])
            ->all();

        $pathCache = [];
        $buildPath = function (int $categoryId) use (&$buildPath, &$pathCache, $categories, $parentById): string {
            if (isset($pathCache[$categoryId])) {
                return $pathCache[$categoryId];
            }

            /** @var Category|null $category */
            $category = $categories->firstWhere('id', $categoryId);

            if ($category === null) {
                return '';
            }

            $parentId = $parentById[$categoryId] ?? null;

            if ($parentId === null) {
                return $pathCache[$categoryId] = $category->name;
            }

            $parentPath = $buildPath((int) $parentId);

            return $pathCache[$categoryId] = $parentPath !== ''
                ? $parentPath.' > '.$category->name
                : $category->name;
        };

        $isDescendant = function (int $candidateId, int $ancestorId) use ($parentById): bool {
            $visited = [];
            $cursor = $candidateId;
            $depth = 0;

            while ($depth < 50) {
                $parentId = $parentById[$cursor] ?? null;

                if ($parentId === null) {
                    return false;
                }

                if (isset($visited[$parentId])) {
                    return false;
                }

                if ((int) $parentId === $ancestorId) {
                    return true;
                }

                $visited[$parentId] = true;
                $cursor = (int) $parentId;
                $depth++;
            }

            return false;
        };

        return $categories
            ->filter(function (Category $category) use ($excludeCategoryId, $isDescendant): bool {
                if ($excludeCategoryId === null) {
                    return true;
                }

                if ($category->id === $excludeCategoryId) {
                    return false;
                }

                return ! $isDescendant($category->id, $excludeCategoryId);
            })
            ->map(fn (Category $category): array => [
                'id' => $category->id,
                'label' => $buildPath($category->id),
            ])
            ->sortBy('label', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }
}
