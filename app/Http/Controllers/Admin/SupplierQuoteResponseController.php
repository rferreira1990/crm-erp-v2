<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSupplierQuoteResponseRequest;
use App\Models\SupplierQuote;
use App\Models\SupplierQuoteItem;
use App\Models\SupplierQuoteRequest;
use App\Models\SupplierQuoteRequestSupplier;
use App\Services\Admin\SupplierQuoteRequestStatusService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class SupplierQuoteResponseController extends Controller
{
    public function __construct(
        private readonly SupplierQuoteRequestStatusService $rfqStatusService
    ) {
    }

    public function create(Request $request, int $rfq, int $rfqSupplier): View
    {
        $companyId = (int) $request->user()->company_id;
        $rfqModel = $this->findCompanyRfqOrFail($companyId, $rfq);
        $this->authorize('update', $rfqModel);

        $rfqSupplierModel = $this->findRfqSupplierOrFail($rfqModel, $rfqSupplier);
        $rfqSupplierModel->loadMissing([
            'supplier:id,name,email',
            'supplierQuote.items',
        ]);
        $rfqModel->loadMissing([
            'items' => fn ($query) => $query->orderBy('line_order')->orderBy('id'),
        ]);

        $existingQuote = $rfqSupplierModel->supplierQuote;
        $existingItemsByRfqItem = $existingQuote
            ? $existingQuote->items->keyBy('supplier_quote_request_item_id')
            : collect();

        return view('admin.rfqs.response', [
            'rfq' => $rfqModel,
            'rfqSupplier' => $rfqSupplierModel,
            'existingQuote' => $existingQuote,
            'existingItemsByRfqItem' => $existingItemsByRfqItem,
        ]);
    }

    public function store(StoreSupplierQuoteResponseRequest $request, int $rfq, int $rfqSupplier): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;
        $rfqModel = $this->findCompanyRfqOrFail($companyId, $rfq);
        $this->authorize('update', $rfqModel);

        $rfqSupplierModel = $this->findRfqSupplierOrFail($rfqModel, $rfqSupplier);

        $validated = $request->validated();
        $respondedItems = $request->respondedItems();

        $rfqItemIds = $rfqModel->items()->pluck('id')->map(fn ($id) => (int) $id)->all();
        $validRfqItemLookup = array_flip($rfqItemIds);

        foreach ($respondedItems as $item) {
            $rfqItemId = (int) ($item['supplier_quote_request_item_id'] ?? 0);
            if (! isset($validRfqItemLookup[$rfqItemId])) {
                throw new NotFoundHttpException();
            }
        }

        DB::transaction(function () use ($validated, $respondedItems, $companyId, $rfqSupplierModel, $rfqModel): void {
            $supplierQuote = SupplierQuote::query()->firstOrNew([
                'supplier_quote_request_supplier_id' => $rfqSupplierModel->id,
            ]);

            $supplierQuote->forceFill([
                'company_id' => $companyId,
                'status' => SupplierQuote::STATUS_RECEIVED,
                'shipping_cost' => (float) ($validated['shipping_cost'] ?? 0),
                'delivery_days' => $validated['delivery_days'] ?? null,
                'payment_terms_text' => $validated['payment_terms_text'] ?? null,
                'valid_until' => $validated['valid_until'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'received_at' => $validated['received_at'],
            ])->save();

            $supplierQuote->items()->delete();

            $subtotal = 0.0;
            $discountTotal = 0.0;
            $taxTotal = 0.0;
            $grandLines = 0.0;

            foreach ($respondedItems as $item) {
                $isAvailable = (bool) ($item['is_available'] ?? true);
                $quantity = $isAvailable ? (float) ($item['quantity'] ?? 0) : null;
                $unitPrice = $isAvailable ? (float) ($item['unit_price'] ?? 0) : null;
                $discountPercent = $isAvailable ? (float) ($item['discount_percent'] ?? 0) : null;
                $vatPercent = $isAvailable ? (float) ($item['vat_percent'] ?? 0) : null;

                $lineTotal = null;
                if ($isAvailable && $quantity !== null && $unitPrice !== null) {
                    $lineSubtotal = round($quantity * $unitPrice, 2);
                    $lineDiscount = round($lineSubtotal * (($discountPercent ?? 0) / 100), 2);
                    $lineNet = round($lineSubtotal - $lineDiscount, 2);
                    $lineTax = round($lineNet * (($vatPercent ?? 0) / 100), 2);
                    $lineTotal = round($lineNet + $lineTax, 2);

                    $subtotal += $lineSubtotal;
                    $discountTotal += $lineDiscount;
                    $taxTotal += $lineTax;
                    $grandLines += $lineTotal;
                }

                $supplierQuote->items()->create([
                    'company_id' => $companyId,
                    'supplier_quote_request_item_id' => (int) $item['supplier_quote_request_item_id'],
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'discount_percent' => $discountPercent,
                    'vat_percent' => $vatPercent,
                    'line_total' => $lineTotal,
                    'alternative_description' => $item['alternative_description'] ?? null,
                    'brand' => $item['brand'] ?? null,
                    'is_available' => $isAvailable,
                    'is_alternative' => (bool) ($item['is_alternative'] ?? false),
                    'notes' => $item['notes'] ?? null,
                ]);
            }

            $shippingCost = (float) ($validated['shipping_cost'] ?? 0);

            $supplierQuote->forceFill([
                'subtotal' => round($subtotal, 2),
                'discount_total' => round($discountTotal, 2),
                'tax_total' => round($taxTotal, 2),
                'grand_total' => round($grandLines + $shippingCost, 2),
            ])->save();

            $rfqSupplierModel->forceFill([
                'status' => SupplierQuoteRequestSupplier::STATUS_RESPONDED,
                'responded_at' => now(),
            ])->save();

            $this->rfqStatusService->syncFromSupplierResponses($rfqModel);
        });

        return redirect()
            ->route('admin.rfqs.show', $rfqModel->id)
            ->with('status', 'Resposta do fornecedor registada com sucesso.');
    }

    private function findCompanyRfqOrFail(int $companyId, int $rfqId): SupplierQuoteRequest
    {
        return SupplierQuoteRequest::query()
            ->forCompany($companyId)
            ->whereKey($rfqId)
            ->firstOrFail();
    }

    private function findRfqSupplierOrFail(SupplierQuoteRequest $rfq, int $rfqSupplierId): SupplierQuoteRequestSupplier
    {
        $rfqSupplier = $rfq->invitedSuppliers()
            ->whereKey($rfqSupplierId)
            ->first();

        if (! $rfqSupplier) {
            throw new NotFoundHttpException();
        }

        return $rfqSupplier;
    }
}

