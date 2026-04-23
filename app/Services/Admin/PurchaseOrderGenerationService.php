<?php

namespace App\Services\Admin;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\SupplierQuoteAward;
use App\Models\SupplierQuoteAwardItem;
use App\Models\SupplierQuoteRequest;
use App\Models\SupplierQuoteRequestItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

class PurchaseOrderGenerationService
{
    /**
     * @return Collection<int, PurchaseOrder>
     */
    public function generateFromAwardedRfq(SupplierQuoteRequest $rfq, int $createdBy): Collection
    {
        $this->assertRfqCanGeneratePurchaseOrders($rfq);

        try {
            return DB::transaction(function () use ($rfq, $createdBy): Collection {
                /** @var SupplierQuoteAward|null $award */
                $award = SupplierQuoteAward::query()
                    ->where('company_id', $rfq->company_id)
                    ->where('supplier_quote_request_id', $rfq->id)
                    ->orderByDesc('awarded_at')
                    ->orderByDesc('id')
                    ->lockForUpdate()
                    ->with([
                        'items' => fn ($query) => $query
                            ->with([
                                'supplierQuoteItem:id,discount_percent,vat_percent,line_total',
                                'rfqItem:id,article_id,line_type',
                            ])
                            ->orderBy('id'),
                    ])
                    ->first();

                if (! $award) {
                    throw ValidationException::withMessages([
                        'rfq' => 'Nao existe adjudicacao valida para gerar encomenda.',
                    ]);
                }

                $existingOrders = PurchaseOrder::query()
                    ->where('company_id', $rfq->company_id)
                    ->where('supplier_quote_award_id', $award->id)
                    ->lockForUpdate()
                    ->count();

                if ($existingOrders > 0) {
                    throw ValidationException::withMessages([
                        'rfq' => 'Ja existem encomendas geradas para esta adjudicacao.',
                    ]);
                }

                $awardItems = $award->items
                    ->filter(fn (SupplierQuoteAwardItem $item): bool => (int) ($item->supplier_id ?? 0) > 0)
                    ->values();

                if ($awardItems->isEmpty()) {
                    throw ValidationException::withMessages([
                        'rfq' => 'A adjudicacao nao contem linhas validas para gerar encomendas.',
                    ]);
                }

                $existingGeneratedAwardItems = PurchaseOrderItem::query()
                    ->where('company_id', $rfq->company_id)
                    ->whereIn('source_award_item_id', $awardItems->pluck('id')->all())
                    ->exists();

                if ($existingGeneratedAwardItems) {
                    throw ValidationException::withMessages([
                        'rfq' => 'Ja existem encomendas geradas para itens desta adjudicacao.',
                    ]);
                }

                $itemsBySupplier = $awardItems
                    ->groupBy(fn (SupplierQuoteAwardItem $item): int => (int) $item->supplier_id)
                    ->sortKeys();

                $isSingleSupplierAward = $itemsBySupplier->count() === 1;
                $orders = collect();

                /** @var Collection<int, SupplierQuoteAwardItem> $supplierItems */
                foreach ($itemsBySupplier as $supplierId => $supplierItems) {
                    /** @var Supplier|null $supplier */
                    $supplier = Supplier::query()
                        ->forCompany((int) $rfq->company_id)
                        ->whereKey((int) $supplierId)
                        ->first();

                    $supplierNameSnapshot = trim((string) ($supplier?->name ?: $supplierItems->first()?->supplier_name ?: 'Fornecedor #'.$supplierId));
                    $supplierEmailSnapshot = $this->normalizeNullableString($supplier?->email);
                    $supplierPhoneSnapshot = $this->normalizeNullableString($supplier?->phone ?: $supplier?->mobile);
                    $supplierAddressSnapshot = $this->buildSupplierAddressSnapshot($supplier);

                    $lineOrder = 1;
                    $linePayload = [];
                    $subtotal = 0.0;
                    $discountTotal = 0.0;
                    $taxTotal = 0.0;
                    $linesGrandTotal = 0.0;

                    foreach ($supplierItems as $awardItem) {
                        $sourceQuoteItem = $awardItem->supplierQuoteItem;

                        $quantity = round((float) ($awardItem->quantity ?? 0), 3);
                        $unitPrice = round((float) ($awardItem->unit_price ?? 0), 4);
                        $discountPercent = round((float) ($sourceQuoteItem?->discount_percent ?? 0), 2);
                        $vatPercent = round((float) ($sourceQuoteItem?->vat_percent ?? 0), 2);
                        $lineType = $this->resolveLineType($awardItem);
                        $articleId = $this->resolveArticleId($awardItem);

                        $lineSubtotal = round($quantity * $unitPrice, 2);
                        $lineDiscountTotal = round($lineSubtotal * ($discountPercent / 100), 2);
                        $lineNet = round($lineSubtotal - $lineDiscountTotal, 2);

                        $sourceLineTotal = $sourceQuoteItem?->line_total ?? $awardItem->line_total;
                        if ($sourceLineTotal !== null) {
                            $lineTotal = round((float) $sourceLineTotal, 2);
                            $lineTaxTotal = round(max(0.0, $lineTotal - $lineNet), 2);
                        } else {
                            $lineTaxTotal = round($lineNet * ($vatPercent / 100), 2);
                            $lineTotal = round($lineNet + $lineTaxTotal, 2);
                        }

                        $subtotal += $lineSubtotal;
                        $discountTotal += $lineDiscountTotal;
                        $taxTotal += $lineTaxTotal;
                        $linesGrandTotal += $lineTotal;

                        $linePayload[] = [
                            'company_id' => (int) $rfq->company_id,
                            'source_award_item_id' => (int) $awardItem->id,
                            'source_supplier_quote_item_id' => $awardItem->supplier_quote_item_id,
                            'line_type' => $lineType,
                            'stock_resolution_status' => $this->defaultStockResolutionStatus($lineType, $articleId),
                            'line_order' => $lineOrder++,
                            'article_id' => $articleId,
                            'article_code' => $awardItem->article_code,
                            'description' => (string) ($awardItem->description ?: '-'),
                            'unit_name' => $awardItem->unit_name,
                            'quantity' => $quantity,
                            'unit_price' => $unitPrice,
                            'discount_percent' => $discountPercent,
                            'vat_percent' => $vatPercent,
                            'line_subtotal' => $lineSubtotal,
                            'line_discount_total' => $lineDiscountTotal,
                            'line_tax_total' => $lineTaxTotal,
                            'line_total' => $lineTotal,
                            'is_alternative' => (bool) ($awardItem->is_alternative ?? false),
                            'alternative_description' => $this->normalizeNullableString($awardItem->alternative_description),
                            'notes' => $this->normalizeNullableString($awardItem->notes),
                        ];
                    }

                    $subtotal = round($subtotal, 2);
                    $discountTotal = round($discountTotal, 2);
                    $taxTotal = round($taxTotal, 2);
                    $linesGrandTotal = round($linesGrandTotal, 2);

                    $shippingTotal = 0.0;
                    if ($isSingleSupplierAward && $award->awarded_total !== null) {
                        $shippingTotal = max(0.0, round(((float) $award->awarded_total) - $linesGrandTotal, 2));
                    }

                    $grandTotal = round($linesGrandTotal + $shippingTotal, 2);

                    $internalNotes = null;
                    if (! $isSingleSupplierAward) {
                        $internalNotes = 'Gerada por adjudicacao fracionada por item. Total sem reparticao automatica de portes por fornecedor.';
                    }

                    $purchaseOrder = PurchaseOrder::createWithGeneratedNumber((int) $rfq->company_id, [
                        'status' => PurchaseOrder::STATUS_DRAFT,
                        'supplier_quote_request_id' => (int) $rfq->id,
                        'supplier_quote_award_id' => (int) $award->id,
                        'supplier_id' => $supplier?->id ?: (int) $supplierId,
                        'supplier_name_snapshot' => $supplierNameSnapshot,
                        'supplier_email_snapshot' => $supplierEmailSnapshot,
                        'supplier_phone_snapshot' => $supplierPhoneSnapshot,
                        'supplier_address_snapshot' => $supplierAddressSnapshot,
                        'issue_date' => now()->toDateString(),
                        'expected_delivery_date' => null,
                        'currency' => 'EUR',
                        'subtotal' => $subtotal,
                        'discount_total' => $discountTotal,
                        'shipping_total' => $shippingTotal,
                        'tax_total' => $taxTotal,
                        'grand_total' => $grandTotal,
                        'internal_notes' => $internalNotes,
                        'supplier_notes' => null,
                        'created_by' => $createdBy,
                        'assigned_user_id' => $rfq->assigned_user_id,
                        'is_locked' => false,
                        'is_active' => true,
                    ]);

                    $purchaseOrder->items()->createMany($linePayload);
                    $orders->push($purchaseOrder);
                }

                return $orders;
            });
        } catch (QueryException $exception) {
            if ((string) $exception->getCode() === '23000') {
                throw ValidationException::withMessages([
                    'rfq' => 'Ja existem encomendas geradas para esta adjudicacao.',
                ]);
            }

            throw $exception;
        }
    }

    private function assertRfqCanGeneratePurchaseOrders(SupplierQuoteRequest $rfq): void
    {
        if ($rfq->status !== SupplierQuoteRequest::STATUS_AWARDED) {
            throw ValidationException::withMessages([
                'rfq' => 'Apenas pedidos adjudicados permitem gerar encomendas.',
            ]);
        }
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }

    private function buildSupplierAddressSnapshot(?Supplier $supplier): ?string
    {
        if (! $supplier) {
            return null;
        }

        $address = trim((string) $supplier->address);
        $location = trim(implode(' ', array_filter([
            $supplier->postal_code,
            $supplier->locality,
            $supplier->city,
        ], fn ($value): bool => trim((string) $value) !== '')));

        $snapshot = trim(implode(' | ', array_filter([$address, $location], fn ($value): bool => trim((string) $value) !== '')));

        return $snapshot !== '' ? $snapshot : null;
    }

    private function resolveArticleId(SupplierQuoteAwardItem $awardItem): ?int
    {
        $articleId = $awardItem->rfqItem?->article_id;

        return $articleId !== null ? (int) $articleId : null;
    }

    private function resolveLineType(SupplierQuoteAwardItem $awardItem): string
    {
        $lineType = trim((string) ($awardItem->line_type ?: $awardItem->rfqItem?->line_type ?: ''));
        if ($lineType === '') {
            return $awardItem->rfqItem?->article_id
                ? SupplierQuoteRequestItem::TYPE_ARTICLE
                : SupplierQuoteRequestItem::TYPE_TEXT;
        }

        return $lineType;
    }

    private function defaultStockResolutionStatus(string $lineType, ?int $articleId): string
    {
        if (in_array($lineType, [SupplierQuoteRequestItem::TYPE_SECTION, SupplierQuoteRequestItem::TYPE_NOTE], true)) {
            return PurchaseOrderItem::STOCK_RESOLUTION_NON_STOCKABLE;
        }

        if ($articleId !== null) {
            return PurchaseOrderItem::STOCK_RESOLUTION_RESOLVED_ARTICLE;
        }

        return PurchaseOrderItem::STOCK_RESOLUTION_PENDING;
    }
}
