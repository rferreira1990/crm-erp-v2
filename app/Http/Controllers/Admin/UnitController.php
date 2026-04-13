<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUnitRequest;
use App\Http\Requests\Admin\UpdateUnitRequest;
use App\Models\Article;
use App\Models\Unit;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class UnitController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Unit::class);

        $companyId = (int) $request->user()->company_id;
        $search = trim((string) $request->query('q', ''));

        $units = Unit::query()
            ->visibleToCompany($companyId)
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($searchQuery) use ($search): void {
                    $searchQuery->where('code', 'like', '%'.$search.'%')
                        ->orWhere('name', 'like', '%'.$search.'%');
                });
            })
            ->orderByDesc('is_system')
            ->orderBy('name')
            ->orderBy('code')
            ->paginate(20)
            ->withQueryString();

        return view('admin.units.index', [
            'units' => $units,
            'filters' => [
                'q' => $search,
            ],
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Unit::class);

        return view('admin.units.create');
    }

    public function store(StoreUnitRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $unit = Unit::query()->create([
            'company_id' => $request->user()->company_id,
            'is_system' => false,
            'code' => $data['code'],
            'name' => $data['name'],
        ]);

        Log::info('Company unit created', [
            'context' => 'company_units',
            'unit_id' => $unit->id,
            'company_id' => $unit->company_id,
            'created_by' => $request->user()->id,
            'code' => $unit->code,
        ]);

        return redirect()
            ->route('admin.units.index')
            ->with('status', 'Unidade criada com sucesso.');
    }

    public function edit(Request $request, int $unit): View
    {
        $companyId = (int) $request->user()->company_id;
        $unitModel = $this->findVisibleUnitOrFail($companyId, $unit);
        $this->authorize('update', $unitModel);

        return view('admin.units.edit', [
            'unit' => $unitModel,
        ]);
    }

    public function update(UpdateUnitRequest $request, int $unit): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;
        $unitModel = $this->findVisibleUnitOrFail($companyId, $unit);
        $this->authorize('update', $unitModel);

        $unitModel->forceFill($request->validated())->save();

        Log::info('Company unit updated', [
            'context' => 'company_units',
            'unit_id' => $unitModel->id,
            'company_id' => $unitModel->company_id,
            'updated_by' => $request->user()->id,
            'code' => $unitModel->code,
        ]);

        return redirect()
            ->route('admin.units.index')
            ->with('status', 'Unidade atualizada com sucesso.');
    }

    public function destroy(Request $request, int $unit): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;
        $unitModel = $this->findVisibleUnitOrFail($companyId, $unit);
        $this->authorize('delete', $unitModel);

        if ($this->isUnitInUse($unitModel)) {
            return back()->withErrors([
                'unit' => 'Nao e possivel eliminar a unidade porque esta em uso.',
            ]);
        }

        $unitModel->delete();

        Log::info('Company unit deleted', [
            'context' => 'company_units',
            'unit_id' => $unitModel->id,
            'company_id' => $unitModel->company_id,
            'deleted_by' => $request->user()->id,
            'code' => $unitModel->code,
        ]);

        return redirect()
            ->route('admin.units.index')
            ->with('status', 'Unidade eliminada com sucesso.');
    }

    private function findVisibleUnitOrFail(int $companyId, int $unitId): Unit
    {
        return Unit::query()
            ->visibleToCompany($companyId)
            ->whereKey($unitId)
            ->firstOrFail();
    }

    private function isUnitInUse(Unit $unit): bool
    {
        return Article::query()
            ->where('company_id', $unit->company_id)
            ->where('unit_id', $unit->id)
            ->exists();
    }
}
