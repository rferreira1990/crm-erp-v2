<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePriceTierRequest;
use App\Http\Requests\Admin\UpdatePriceTierRequest;
use App\Models\PriceTier;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PriceTierController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', PriceTier::class);

        $companyId = (int) $request->user()->company_id;
        $search = trim((string) $request->query('q', ''));

        $priceTiers = PriceTier::query()
            ->visibleToCompany($companyId)
            ->when($search !== '', function ($query) use ($search): void {
                $query->where('name', 'like', '%'.$search.'%');
            })
            ->orderByDesc('is_system')
            ->orderByDesc('is_default')
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.price-tiers.index', [
            'priceTiers' => $priceTiers,
            'filters' => [
                'q' => $search,
            ],
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', PriceTier::class);

        return view('admin.price-tiers.create');
    }

    public function store(StorePriceTierRequest $request): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;
        $data = $request->validated();

        $priceTier = PriceTier::query()->create([
            'company_id' => $companyId,
            'name' => $data['name'],
            'percentage_adjustment' => $data['percentage_adjustment'],
            'is_system' => false,
            'is_default' => false,
            'is_active' => (bool) $data['is_active'],
        ]);

        Log::info('Price tier created', [
            'context' => 'company_price_tiers',
            'price_tier_id' => $priceTier->id,
            'company_id' => $companyId,
            'created_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('admin.price-tiers.index')
            ->with('status', 'Escalao de preco criado com sucesso.');
    }

    public function edit(Request $request, int $priceTier): View
    {
        $companyId = (int) $request->user()->company_id;
        $priceTierModel = $this->findVisiblePriceTierOrFail($companyId, $priceTier);
        $this->authorize('update', $priceTierModel);

        return view('admin.price-tiers.edit', [
            'priceTier' => $priceTierModel,
        ]);
    }

    public function update(UpdatePriceTierRequest $request, int $priceTier): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;
        $priceTierModel = $this->findVisiblePriceTierOrFail($companyId, $priceTier);
        $this->authorize('update', $priceTierModel);

        $data = $request->validated();

        $priceTierModel->forceFill([
            'name' => $data['name'],
            'percentage_adjustment' => $data['percentage_adjustment'],
            'is_active' => (bool) $data['is_active'],
        ])->save();

        Log::info('Price tier updated', [
            'context' => 'company_price_tiers',
            'price_tier_id' => $priceTierModel->id,
            'company_id' => $companyId,
            'updated_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('admin.price-tiers.index')
            ->with('status', 'Escalao de preco atualizado com sucesso.');
    }

    public function destroy(Request $request, int $priceTier): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;
        $priceTierModel = $this->findVisiblePriceTierOrFail($companyId, $priceTier);
        $this->authorize('delete', $priceTierModel);

        if ($priceTierModel->customers()->exists()) {
            return back()->withErrors([
                'price_tier' => 'Nao e possivel apagar um escalao em uso.',
            ]);
        }

        $priceTierModel->delete();

        Log::info('Price tier deleted', [
            'context' => 'company_price_tiers',
            'price_tier_id' => $priceTierModel->id,
            'company_id' => $companyId,
            'deleted_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('admin.price-tiers.index')
            ->with('status', 'Escalao de preco eliminado com sucesso.');
    }

    private function findVisiblePriceTierOrFail(int $companyId, int $priceTierId): PriceTier
    {
        return PriceTier::query()
            ->visibleToCompany($companyId)
            ->whereKey($priceTierId)
            ->firstOrFail();
    }
}

