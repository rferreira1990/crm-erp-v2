<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Models\SupplierQuoteRequest;
use App\Services\Admin\PurchaseOrderGenerationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PurchaseOrderGenerationController extends Controller
{
    public function __construct(
        private readonly PurchaseOrderGenerationService $generationService
    ) {
    }

    public function store(Request $request, int $rfq): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;
        $rfqModel = SupplierQuoteRequest::query()
            ->forCompany($companyId)
            ->whereKey($rfq)
            ->firstOrFail();

        $this->authorize('view', $rfqModel);
        $this->authorize('create', PurchaseOrder::class);

        $orders = $this->generationService->generateFromAwardedRfq(
            rfq: $rfqModel,
            createdBy: (int) $request->user()->id,
        );

        $message = $orders->count() === 1
            ? 'Encomenda gerada com sucesso.'
            : $orders->count().' encomendas geradas com sucesso.';

        return redirect()
            ->route('admin.rfqs.show', $rfqModel->id)
            ->with('status', $message);
    }
}
