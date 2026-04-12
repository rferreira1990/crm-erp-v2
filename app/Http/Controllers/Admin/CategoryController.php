<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCategoryRequest;
use App\Http\Requests\Admin\UpdateCategoryRequest;
use App\Models\Category;
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
            ->visibleToCompany($companyId)
            ->when($search !== '', function ($query) use ($search): void {
                $query->where('name', 'like', '%'.$search.'%');
            })
            ->orderByDesc('is_system')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.categories.index', [
            'categories' => $categories,
            'filters' => [
                'q' => $search,
            ],
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Category::class);

        return view('admin.categories.create');
    }

    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $category = Category::query()->create([
            'company_id' => $request->user()->company_id,
            'is_system' => false,
            'name' => $data['name'],
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
        ]);
    }

    public function update(UpdateCategoryRequest $request, int $category): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;
        $categoryModel = $this->findVisibleCategoryOrFail($companyId, $category);
        $this->authorize('update', $categoryModel);

        $categoryModel->forceFill($request->validated())->save();

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
}
