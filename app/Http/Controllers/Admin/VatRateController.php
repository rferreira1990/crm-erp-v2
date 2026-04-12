<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CompanyVatRateOverride;
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
            ->with([
                'vatExemptionReason:id,code,name',
                'companyOverrides' => fn ($query) => $query->where('company_id', $companyId),
            ])
            ->visibleToCompany($companyId)
            ->when($search !== '', function ($query) use ($search): void {
                $query->where('name', 'like', '%'.$search.'%');
            })
            ->orderByRaw(
                'CASE region
                    WHEN ? THEN 1
                    WHEN ? THEN 2
                    WHEN ? THEN 3
                    ELSE 99
                END',
                [
                    VatRate::REGION_MAINLAND,
                    VatRate::REGION_MADEIRA,
                    VatRate::REGION_AZORES,
                ]
            )
            ->orderBy('is_exempt')
            ->orderByDesc('rate')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.vat-rates.index', [
            'vatRates' => $vatRates,
            'canManageAvailability' => $request->user()->can('manageAvailability', VatRate::class),
            'filters' => [
                'q' => $search,
            ],
        ]);
    }

    public function enable(Request $request, int $vatRate): RedirectResponse
    {
        return $this->toggleAvailability($request, $vatRate, true);
    }

    public function disable(Request $request, int $vatRate): RedirectResponse
    {
        return $this->toggleAvailability($request, $vatRate, false);
    }

    private function toggleAvailability(Request $request, int $vatRateId, bool $isEnabled): RedirectResponse
    {
        $this->authorize('manageAvailability', VatRate::class);

        $companyId = (int) $request->user()->company_id;
        $vatRate = $this->findSystemVatRateOrFail($companyId, $vatRateId);

        CompanyVatRateOverride::query()->updateOrCreate(
            [
                'company_id' => $companyId,
                'vat_rate_id' => $vatRate->id,
            ],
            [
                'is_enabled' => $isEnabled,
            ]
        );

        Log::info('Company VAT rate availability changed', [
            'context' => 'company_vat_rates',
            'vat_rate_id' => $vatRate->id,
            'company_id' => $companyId,
            'updated_by' => $request->user()->id,
            'is_enabled' => $isEnabled,
        ]);

        return redirect()
            ->route('admin.vat-rates.index')
            ->with('status', $isEnabled
                ? 'Disponibilidade da taxa de IVA atualizada para ativa.'
                : 'Disponibilidade da taxa de IVA atualizada para inativa.');
    }

    private function findSystemVatRateOrFail(int $companyId, int $vatRateId): VatRate
    {
        return VatRate::query()
            ->visibleToCompany($companyId)
            ->whereKey($vatRateId)
            ->firstOrFail();
    }
}
