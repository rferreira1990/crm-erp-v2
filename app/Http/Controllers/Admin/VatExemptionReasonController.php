<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CompanyVatExemptionReasonOverride;
use App\Models\VatRate;
use App\Models\VatExemptionReason;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class VatExemptionReasonController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', VatExemptionReason::class);

        $companyId = (int) $request->user()->company_id;
        $search = trim((string) $request->query('q', ''));

        $reasons = VatExemptionReason::query()
            ->with([
                'companyOverrides' => fn ($query) => $query->where('company_id', $companyId),
            ])
            ->visibleToCompany($companyId)
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($subQuery) use ($search): void {
                    $subQuery->where('code', 'like', '%'.$search.'%')
                        ->orWhere('name', 'like', '%'.$search.'%');
                });
            })
            ->orderBy('code')
            ->paginate(20)
            ->withQueryString();

        return view('admin.vat-exemption-reasons.index', [
            'reasons' => $reasons,
            'canManageAvailability' => $request->user()->can('manageAvailability', VatExemptionReason::class),
            'filters' => [
                'q' => $search,
            ],
        ]);
    }

    public function enable(Request $request, int $vatExemptionReason): RedirectResponse
    {
        return $this->toggleAvailability($request, $vatExemptionReason, true);
    }

    public function disable(Request $request, int $vatExemptionReason): RedirectResponse
    {
        return $this->toggleAvailability($request, $vatExemptionReason, false);
    }

    private function toggleAvailability(Request $request, int $reasonId, bool $isEnabled): RedirectResponse
    {
        $this->authorize('manageAvailability', VatExemptionReason::class);

        $companyId = (int) $request->user()->company_id;
        $reason = $this->findSystemReasonOrFail($companyId, $reasonId);
        $isCurrentlyEnabled = $reason->isEnabledForCompany($companyId);

        if (! $isEnabled && $isCurrentlyEnabled && $this->isLastEnabledReason($companyId) && $this->hasEnabledExemptVatRate($companyId)) {
            return redirect()
                ->route('admin.vat-exemption-reasons.index')
                ->withErrors([
                    'vat_exemption_reason' => 'Nao pode desativar o ultimo motivo ativo enquanto existir uma taxa Isento ativa.',
                ]);
        }

        CompanyVatExemptionReasonOverride::query()->updateOrCreate(
            [
                'company_id' => $companyId,
                'vat_exemption_reason_id' => $reason->id,
            ],
            [
                'is_enabled' => $isEnabled,
            ]
        );

        Log::info('Company VAT exemption reason availability changed', [
            'context' => 'company_vat_exemption_reasons',
            'reason_id' => $reason->id,
            'company_id' => $companyId,
            'updated_by' => $request->user()->id,
            'is_enabled' => $isEnabled,
        ]);

        return redirect()
            ->route('admin.vat-exemption-reasons.index')
            ->with('status', $isEnabled
                ? 'Disponibilidade do motivo de isencao atualizada para ativa.'
                : 'Disponibilidade do motivo de isencao atualizada para inativa.');
    }

    private function findSystemReasonOrFail(int $companyId, int $reasonId): VatExemptionReason
    {
        return VatExemptionReason::query()
            ->visibleToCompany($companyId)
            ->whereKey($reasonId)
            ->firstOrFail();
    }

    private function isLastEnabledReason(int $companyId): bool
    {
        $enabledReasons = VatExemptionReason::query()
            ->with([
                'companyOverrides' => fn ($query) => $query->where('company_id', $companyId),
            ])
            ->visibleToCompany($companyId)
            ->get()
            ->filter(fn (VatExemptionReason $reason): bool => $reason->isEnabledForCompany($companyId));

        return $enabledReasons->count() <= 1;
    }

    private function hasEnabledExemptVatRate(int $companyId): bool
    {
        $exemptRates = VatRate::query()
            ->with([
                'companyOverrides' => fn ($query) => $query->where('company_id', $companyId),
            ])
            ->visibleToCompany($companyId)
            ->where('is_exempt', true)
            ->get();

        return $exemptRates->contains(fn (VatRate $rate): bool => $rate->isEnabledForCompany($companyId));
    }
}
