<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProductFamilyRequest;
use App\Http\Requests\Admin\UpdateProductFamilyRequest;
use App\Models\ProductFamily;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class ProductFamilyController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', ProductFamily::class);

        $companyId = (int) $request->user()->company_id;
        $search = trim((string) $request->query('q', ''));

        $families = ProductFamily::query()
            ->visibleToCompany($companyId)
            ->when($search !== '', function ($query) use ($search): void {
                $query->where('name', 'like', '%'.$search.'%');
            })
            ->orderByRaw('CASE WHEN parent_id IS NULL THEN 0 ELSE 1 END')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.product-families.index', [
            'families' => $families,
            'hierarchyLabels' => $this->buildHierarchyLabels(
                $families->getCollection(),
                $companyId
            ),
            'filters' => [
                'q' => $search,
            ],
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', ProductFamily::class);

        $companyId = (int) $request->user()->company_id;

        return view('admin.product-families.create', [
            'parentOptions' => $this->buildParentOptions($companyId),
        ]);
    }

    public function store(StoreProductFamilyRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $family = ProductFamily::query()->create([
            'company_id' => $request->user()->company_id,
            'is_system' => false,
            'name' => $data['name'],
            'parent_id' => $data['parent_id'] ?? null,
            'family_code' => $data['family_code'] ?? null,
        ]);

        Log::info('Company product family created', [
            'context' => 'company_product_families',
            'family_id' => $family->id,
            'company_id' => $family->company_id,
            'created_by' => $request->user()->id,
            'name' => $family->name,
        ]);

        return redirect()
            ->route('admin.product-families.index')
            ->with('status', 'Familia de produtos criada com sucesso.');
    }

    public function edit(Request $request, int $productFamily): View
    {
        $companyId = (int) $request->user()->company_id;
        $family = $this->findVisibleFamilyOrFail($companyId, $productFamily);
        $this->authorize('update', $family);

        return view('admin.product-families.edit', [
            'family' => $family,
            'parentOptions' => $this->buildParentOptions(
                $companyId,
                $family->id
            ),
        ]);
    }

    public function update(UpdateProductFamilyRequest $request, int $productFamily): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;
        $family = $this->findVisibleFamilyOrFail($companyId, $productFamily);
        $this->authorize('update', $family);
        $data = $request->validated();

        $family->forceFill([
            'name' => $data['name'],
            'parent_id' => $data['parent_id'] ?? null,
            'family_code' => $data['family_code'] ?? null,
        ])->save();

        Log::info('Company product family updated', [
            'context' => 'company_product_families',
            'family_id' => $family->id,
            'company_id' => $family->company_id,
            'updated_by' => $request->user()->id,
            'name' => $family->name,
        ]);

        return redirect()
            ->route('admin.product-families.index')
            ->with('status', 'Familia de produtos atualizada com sucesso.');
    }

    public function destroy(Request $request, int $productFamily): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;
        $family = $this->findVisibleFamilyOrFail($companyId, $productFamily);
        $this->authorize('delete', $family);

        if ($this->isFamilyInUse($family)) {
            return back()->withErrors([
                'product_family' => 'Nao e possivel eliminar a familia porque esta em uso.',
            ]);
        }

        if ($family->children()->exists()) {
            return back()->withErrors([
                'product_family' => 'Nao e possivel eliminar a familia porque tem subfamilias associadas.',
            ]);
        }

        $family->delete();

        Log::info('Company product family deleted', [
            'context' => 'company_product_families',
            'family_id' => $family->id,
            'company_id' => $family->company_id,
            'deleted_by' => $request->user()->id,
            'name' => $family->name,
        ]);

        return redirect()
            ->route('admin.product-families.index')
            ->with('status', 'Familia de produtos eliminada com sucesso.');
    }

    private function findVisibleFamilyOrFail(int $companyId, int $familyId): ProductFamily
    {
        return ProductFamily::query()
            ->visibleToCompany($companyId)
            ->whereKey($familyId)
            ->firstOrFail();
    }

    private function isFamilyInUse(ProductFamily $family): bool
    {
        return $family->articles()->exists();
    }

    /**
     * @return array<int, string>
     */
    private function buildHierarchyLabels(Collection $pageFamilies, int $companyId): array
    {
        $map = ProductFamily::query()
            ->visibleToCompany($companyId)
            ->get(['id', 'parent_id', 'name'])
            ->keyBy('id');

        $labelCache = [];

        $buildLabel = function (int $familyId) use (&$labelCache, $map): string {
            if (isset($labelCache[$familyId])) {
                return $labelCache[$familyId];
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
                $family = $map->get($cursorId);

                if ($family === null) {
                    break;
                }

                $parts[] = $family->name;
                $cursorId = $family->parent_id !== null
                    ? (int) $family->parent_id
                    : 0;
                $depth++;
            }

            $label = implode(' > ', array_reverse($parts));
            $labelCache[$familyId] = $label;

            return $label;
        };

        $labels = [];

        foreach ($pageFamilies as $family) {
            $labels[$family->id] = $buildLabel((int) $family->id);
        }

        return $labels;
    }

    /**
     * @return array<int, array{id:int,label:string}>
     */
    private function buildParentOptions(int $companyId, ?int $excludeFamilyId = null): array
    {
        $families = ProductFamily::query()
            ->visibleToCompany($companyId)
            ->get(['id', 'parent_id', 'name']);
        $familyById = $families->keyBy('id');

        $parentById = $families
            ->mapWithKeys(fn (ProductFamily $family): array => [$family->id => $family->parent_id])
            ->all();

        $pathCache = [];
        $buildPath = function (int $familyId) use (&$pathCache, $familyById, $parentById): string {
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

                $parentId = $parentById[$cursorId] ?? null;
                $cursorId = $parentId !== null
                    ? (int) $parentId
                    : 0;
                $depth++;
            }

            $path = implode(' > ', array_reverse($parts));
            $pathCache[$familyId] = $path;

            return $path;
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

        return $families
            ->filter(function (ProductFamily $family) use ($excludeFamilyId, $isDescendant): bool {
                if ($excludeFamilyId === null) {
                    return true;
                }

                if ($family->id === $excludeFamilyId) {
                    return false;
                }

                return ! $isDescendant($family->id, $excludeFamilyId);
            })
            ->map(fn (ProductFamily $family): array => [
                'id' => $family->id,
                'label' => $buildPath($family->id),
            ])
            ->sortBy('label', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }
}
