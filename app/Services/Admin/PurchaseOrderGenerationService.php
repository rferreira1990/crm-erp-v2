<?php

namespace App\Services\Admin;

use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\SupplierQuoteAward;
use App\Models\SupplierQuoteAwardItem;
use App\Models\SupplierQuoteRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseOrderGenerationService
{
    /**
     * @return Collection<int, PurchaseOrder>
     */
    public function generateFromAwardedRfq(SupplierQuoteRequest $rfq, int $createdBy): Collection
    {
        $this->assertRfqCanGeneratePurchaseOrders($rfq);

        return DB::transaction(function () use ($rfq, $createdBy): Collection {
            /** @var SupplierQuoteAward|null $award */
            $award = SupplierQuoteAward::query()
                ->where('company_id', $rfq->company_id)
                ->where('supplier_quote_request_id', $rfq->id)
                ->orderByDesc('awarded_at')
                ->orderByDesc('id')
                ->lockForUpdate()
                ->with(['items' => fn ($query) => $query->orderBy('id')])
                ->first();

            if (! $award) {
                throw ValidationException::withMessages([
                    'rfq' => 'Nao existe adjudicacao valida para gerar encomenda.',
                ]);
            }

            $existingCount = PurchaseOrder::query()
                ->where('company_id', $rfq->company_id)
                ->where('supplier_quote_award_id', $award->id)
                ->count();

            if ($existingCount > 0) {
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

                foreach ($supplierItems as $awardItem) {
                    $quantity = (float) ($awardItem->quantity ?? 0);
                    $unitPrice = (float) ($awardItem->unit_price ?? 0);
                    $lineTotal = $awardItem->line_total !== null
                        ? (float) $awardItem->line_total
                        : round($quantity * $unitPrice, 2);

                    $subtotal += $lineTotal;

                    $linePayload[] = [
                        'company_id' => (int) $rfq->company_id,
                        'source_award_item_id' => (int) $awardItem->id,
                        'source_supplier_quote_item_id' => $awardItem->supplier_quote_item_id,
                        'line_order' => $lineOrder++,
                        'article_id' => null,
                        'article_code' => $awardItem->article_code,
                        'description' => (string) ($awardItem->description ?: '-'),
                        'unit_name' => $awardItem->unit_name,
                        'quantity' => round($quantity, 3),
                        'unit_price' => round($unitPrice, 4),
                        'discount_percent' => 0,
                        'vat_percent' => 0,
                        'line_subtotal' => round($lineTotal, 2),
                        'line_discount_total' => 0,
                        'line_tax_total' => 0,
                        'line_total' => round($lineTotal, 2),
                        'is_alternative' => (bool) ($awardItem->is_alternative ?? false),
                        'alternative_description' => $this->normalizeNullableString($awardItem->alternative_description),
                        'notes' => $this->normalizeNullableString($awardItem->notes),
                    ];
                }

                $subtotal = round($subtotal, 2);
                $shippingTotal = 0.0;
                if ($isSingleSupplierAward && $award->awarded_total !== null) {
                    $shippingTotal = max(0.0, round(((float) $award->awarded_total) - $subtotal, 2));
                }
                $grandTotal = round($subtotal + $shippingTotal, 2);

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
                    'discount_total' => 0,
                    'shipping_total' => $shippingTotal,
                    'tax_total' => 0,
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
}

