<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSupplierQuoteAwardRequest;
use App\Models\SupplierQuoteAward;
use App\Models\SupplierQuoteRequest;
use App\Services\Admin\SupplierQuoteAwardService;
use Illuminate\Http\RedirectResponse;

class SupplierQuoteAwardController extends Controller
{
    public function __construct(
        private readonly SupplierQuoteAwardService $awardService
    ) {
    }

    public function store(StoreSupplierQuoteAwardRequest $request, int $rfq): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;
        $rfqModel = $this->findCompanyRfqOrFail($companyId, $rfq);
        $this->authorize('award', $rfqModel);

        $award = $this->awardService->award(
            rfq: $rfqModel,
            awardedBy: (int) $request->user()->id,
            payload: $request->validated()
        );

        return redirect()
            ->route('admin.rfqs.show', $rfqModel->id)
            ->with('status', 'Adjudicacao registada com sucesso ('.(SupplierQuoteAward::modeLabels()[$award->mode] ?? $award->mode).').');
    }

    private function findCompanyRfqOrFail(int $companyId, int $rfqId): SupplierQuoteRequest
    {
        return SupplierQuoteRequest::query()
            ->forCompany($companyId)
            ->whereKey($rfqId)
            ->firstOrFail();
    }
}
