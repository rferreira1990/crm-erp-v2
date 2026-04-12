<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreVatExemptionReasonRequest;
use App\Http\Requests\Admin\UpdateVatExemptionReasonRequest;
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
            ->visibleToCompany($companyId)
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($subQuery) use ($search): void {
                    $subQuery->where('code', 'like', '%'.$search.'%')
                        ->orWhere('name', 'like', '%'.$search.'%');
                });
            })
            ->orderByDesc('is_system')
            ->orderBy('code')
            ->paginate(20)
            ->withQueryString();

        return view('admin.vat-exemption-reasons.index', [
            'reasons' => $reasons,
            'filters' => [
                'q' => $search,
            ],
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', VatExemptionReason::class);

        return view('admin.vat-exemption-reasons.create');
    }

    public function store(StoreVatExemptionReasonRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $reason = VatExemptionReason::query()->create([
            'company_id' => $request->user()->company_id,
            'is_system' => false,
            'code' => $data['code'],
            'name' => $data['name'],
            'legal_reference' => $data['legal_reference'] ?? null,
        ]);

        Log::info('Company VAT exemption reason created', [
            'context' => 'company_vat_exemption_reasons',
            'reason_id' => $reason->id,
            'company_id' => $reason->company_id,
            'created_by' => $request->user()->id,
            'code' => $reason->code,
        ]);

        return redirect()
            ->route('admin.vat-exemption-reasons.index')
            ->with('status', 'Motivo de isencao criado com sucesso.');
    }

    public function edit(Request $request, int $vatExemptionReason): View
    {
        $companyId = (int) $request->user()->company_id;
        $reason = $this->findVisibleReasonOrFail($companyId, $vatExemptionReason);
        $this->authorize('update', $reason);

        return view('admin.vat-exemption-reasons.edit', [
            'reason' => $reason,
        ]);
    }

    public function update(UpdateVatExemptionReasonRequest $request, int $vatExemptionReason): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;
        $reason = $this->findVisibleReasonOrFail($companyId, $vatExemptionReason);
        $this->authorize('update', $reason);

        $reason->forceFill($request->validated())->save();

        Log::info('Company VAT exemption reason updated', [
            'context' => 'company_vat_exemption_reasons',
            'reason_id' => $reason->id,
            'company_id' => $reason->company_id,
            'updated_by' => $request->user()->id,
            'code' => $reason->code,
        ]);

        return redirect()
            ->route('admin.vat-exemption-reasons.index')
            ->with('status', 'Motivo de isencao atualizado com sucesso.');
    }

    public function destroy(Request $request, int $vatExemptionReason): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;
        $reason = $this->findVisibleReasonOrFail($companyId, $vatExemptionReason);
        $this->authorize('delete', $reason);

        if ($this->isReasonInUse($reason)) {
            return back()->withErrors([
                'vat_exemption_reason' => 'Nao e possivel eliminar o motivo de isencao porque esta em uso.',
            ]);
        }

        $reason->delete();

        Log::info('Company VAT exemption reason deleted', [
            'context' => 'company_vat_exemption_reasons',
            'reason_id' => $reason->id,
            'company_id' => $reason->company_id,
            'deleted_by' => $request->user()->id,
            'code' => $reason->code,
        ]);

        return redirect()
            ->route('admin.vat-exemption-reasons.index')
            ->with('status', 'Motivo de isencao eliminado com sucesso.');
    }

    private function findVisibleReasonOrFail(int $companyId, int $reasonId): VatExemptionReason
    {
        return VatExemptionReason::query()
            ->visibleToCompany($companyId)
            ->whereKey($reasonId)
            ->firstOrFail();
    }

    private function isReasonInUse(VatExemptionReason $reason): bool
    {
        // Extension point: block delete when VAT rates/documents reference this reason.
        return false;
    }
}

