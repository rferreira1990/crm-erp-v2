<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreVatRateRequest;
use App\Http\Requests\Admin\UpdateVatRateRequest;
use App\Models\VatExemptionReason;
use App\Models\VatRate;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class VatRateController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', VatRate::class);

        $companyId = (int) $request->user()->company_id;
        $search = trim((string) $request->query('q', ''));

        $vatRates = VatRate::query()
            ->with('vatExemptionReason:id,code,name')
            ->visibleToCompany($companyId)
            ->when($search !== '', function ($query) use ($search): void {
                $query->where('name', 'like', '%'.$search.'%');
            })
            ->orderByDesc('is_system')
            ->orderBy('region')
            ->orderBy('rate')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.vat-rates.index', [
            'vatRates' => $vatRates,
            'filters' => [
                'q' => $search,
            ],
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', VatRate::class);

        return view('admin.vat-rates.create', [
            'regionOptions' => VatRate::regionLabels(),
            'exemptionReasons' => $this->visibleExemptionReasons($request),
        ]);
    }

    public function store(StoreVatRateRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $vatRate = VatRate::query()->create([
            'company_id' => $request->user()->company_id,
            'is_system' => false,
            'name' => $data['name'],
            'region' => $data['region'] ?? null,
            'rate' => $data['rate'],
            'is_exempt' => (bool) $data['is_exempt'],
            'vat_exemption_reason_id' => (bool) $data['is_exempt'] ? ($data['vat_exemption_reason_id'] ?? null) : null,
        ]);

        Log::info('Company VAT rate created', [
            'context' => 'company_vat_rates',
            'vat_rate_id' => $vatRate->id,
            'company_id' => $vatRate->company_id,
            'created_by' => $request->user()->id,
            'name' => $vatRate->name,
            'rate' => $vatRate->rate,
            'is_exempt' => $vatRate->is_exempt,
        ]);

        return redirect()
            ->route('admin.vat-rates.index')
            ->with('status', 'Taxa de IVA criada com sucesso.');
    }

    public function edit(Request $request, int $vatRate): View
    {
        $companyId = (int) $request->user()->company_id;
        $rate = $this->findVisibleVatRateOrFail($companyId, $vatRate);
        $this->authorize('update', $rate);

        return view('admin.vat-rates.edit', [
            'vatRate' => $rate,
            'regionOptions' => VatRate::regionLabels(),
            'exemptionReasons' => $this->visibleExemptionReasons($request),
        ]);
    }

    public function update(UpdateVatRateRequest $request, int $vatRate): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;
        $rate = $this->findVisibleVatRateOrFail($companyId, $vatRate);
        $this->authorize('update', $rate);

        $data = $request->validated();
        $rate->forceFill([
            'name' => $data['name'],
            'region' => $data['region'] ?? null,
            'rate' => $data['rate'],
            'is_exempt' => (bool) $data['is_exempt'],
            'vat_exemption_reason_id' => (bool) $data['is_exempt'] ? ($data['vat_exemption_reason_id'] ?? null) : null,
        ])->save();

        Log::info('Company VAT rate updated', [
            'context' => 'company_vat_rates',
            'vat_rate_id' => $rate->id,
            'company_id' => $rate->company_id,
            'updated_by' => $request->user()->id,
            'name' => $rate->name,
            'rate' => $rate->rate,
            'is_exempt' => $rate->is_exempt,
        ]);

        return redirect()
            ->route('admin.vat-rates.index')
            ->with('status', 'Taxa de IVA atualizada com sucesso.');
    }

    public function destroy(Request $request, int $vatRate): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;
        $rate = $this->findVisibleVatRateOrFail($companyId, $vatRate);
        $this->authorize('delete', $rate);

        if ($this->isVatRateInUse($rate)) {
            return back()->withErrors([
                'vat_rate' => 'Nao e possivel eliminar a taxa de IVA porque esta em uso.',
            ]);
        }

        $rate->delete();

        Log::info('Company VAT rate deleted', [
            'context' => 'company_vat_rates',
            'vat_rate_id' => $rate->id,
            'company_id' => $rate->company_id,
            'deleted_by' => $request->user()->id,
            'name' => $rate->name,
        ]);

        return redirect()
            ->route('admin.vat-rates.index')
            ->with('status', 'Taxa de IVA eliminada com sucesso.');
    }

    private function visibleExemptionReasons(Request $request)
    {
        $companyId = (int) $request->user()->company_id;

        return VatExemptionReason::query()
            ->visibleToCompany($companyId)
            ->orderByDesc('is_system')
            ->orderBy('code')
            ->get();
    }

    private function findVisibleVatRateOrFail(int $companyId, int $vatRateId): VatRate
    {
        return VatRate::query()
            ->visibleToCompany($companyId)
            ->whereKey($vatRateId)
            ->firstOrFail();
    }

    private function isVatRateInUse(VatRate $vatRate): bool
    {
        // Extension point: block delete when documents start referencing VAT rates.
        return false;
    }
}

