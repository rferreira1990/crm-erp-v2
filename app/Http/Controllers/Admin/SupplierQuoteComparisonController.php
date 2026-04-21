<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupplierQuoteAward;
use App\Models\SupplierQuoteRequest;
use App\Services\Admin\SupplierQuoteComparisonService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class SupplierQuoteComparisonController extends Controller
{
    public function __construct(
        private readonly SupplierQuoteComparisonService $comparisonService
    ) {
    }

    public function show(Request $request, int $rfq): View
    {
        $companyId = (int) $request->user()->company_id;
        $rfqModel = $this->findCompanyRfqOrFail($companyId, $rfq);
        $this->authorize('compare', $rfqModel);

        $comparison = $this->comparisonService->build($rfqModel);

        if (in_array($rfqModel->status, [
            SupplierQuoteRequest::STATUS_PARTIALLY_RECEIVED,
            SupplierQuoteRequest::STATUS_RECEIVED,
        ], true) && $comparison['has_responses']) {
            $rfqModel->forceFill([
                'status' => SupplierQuoteRequest::STATUS_COMPARED,
            ])->save();
            $comparison['rfq']->status = SupplierQuoteRequest::STATUS_COMPARED;
        }

        $comparison['rfq']->loadMissing([
            'latestAward' => fn ($query) => $query->with([
                'items:id,supplier_quote_award_id,supplier_quote_request_item_id,supplier_id,is_cheapest_option,is_alternative',
                'supplier:id,name',
                'awardedByUser:id,name',
            ]),
        ]);

        return view('admin.rfqs.compare', [
            'rfq' => $comparison['rfq'],
            'comparison' => $comparison,
            'statusLabels' => SupplierQuoteRequest::statusLabels(),
            'awardModeLabels' => SupplierQuoteAward::modeLabels(),
            'awardReasonOptions' => SupplierQuoteAward::reasonOptions(),
        ]);
    }

    private function findCompanyRfqOrFail(int $companyId, int $rfqId): SupplierQuoteRequest
    {
        return SupplierQuoteRequest::query()
            ->forCompany($companyId)
            ->whereKey($rfqId)
            ->firstOrFail();
    }
}
