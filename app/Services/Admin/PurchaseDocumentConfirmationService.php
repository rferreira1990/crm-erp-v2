<?php

namespace App\Services\Admin;

use App\Models\PurchaseDocument;
use App\Models\PurchaseDocumentItem;
use App\Models\PurchaseOrder;
use App\Models\StockMovement;
use App\Models\SupplierPayment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseDocumentConfirmationService
{
    public function __construct(
        private readonly PurchaseDocumentPaymentStatusService $paymentStatusService,
        private readonly PurchaseDocumentStockService $stockService
    ) {
    }

    public function confirm(int $companyId, int $documentId, int $performedBy): PurchaseDocument
    {
        return DB::transaction(function () use ($companyId, $documentId, $performedBy): PurchaseDocument {
            /** @var PurchaseDocument $document */
            $document = PurchaseDocument::query()
                ->forCompany($companyId)
                ->whereKey($documentId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($document->status !== PurchaseDocument::STATUS_DRAFT) {
                throw ValidationException::withMessages([
                    'purchase_document' => 'Apenas documentos em rascunho podem ser confirmados.',
                ]);
            }

            $document->load([
                'items' => fn ($query) => $query
                    ->orderBy('line_order')
                    ->orderBy('id')
                    ->lockForUpdate(),
            ]);

            if ($document->items->isEmpty()) {
                throw ValidationException::withMessages([
                    'purchase_document' => 'Nao e possivel confirmar um documento sem linhas.',
                ]);
            }

            foreach ($document->items as $item) {
                $amounts = PurchaseDocumentItem::calculateAmounts(
                    quantity: round((float) $item->quantity, 3),
                    unitPrice: round((float) $item->unit_price, 4),
                    discountPercent: round((float) ($item->discount_percent ?? 0), 2),
                    taxRate: round((float) ($item->tax_rate ?? 0), 2)
                );

                $item->forceFill([
                    'line_subtotal' => $amounts['line_subtotal'],
                    'line_discount_total' => $amounts['line_discount_total'],
                    'line_tax_total' => $amounts['line_tax_total'],
                    'line_total' => $amounts['line_total'],
                ])->save();
            }

            $document->recalculateTotalsFromItems();

            $document->forceFill([
                ...$document->applyStatusTransition(PurchaseDocument::STATUS_CONFIRMED),
                'updated_by' => $performedBy,
            ]);

            $this->stockService->moveStockForConfirmedDocument($document, $performedBy);

            $document->save();
            $this->syncPurchaseOrderReceptionStatus($document);

            $this->paymentStatusService->recalculateForDocument(
                companyId: $companyId,
                purchaseDocumentId: (int) $document->id,
                updatedBy: $performedBy
            );

            return $document->fresh([
                'supplier:id,name',
                'purchaseOrder:id,number,supplier_id',
                'items' => fn ($query) => $query->orderBy('line_order')->orderBy('id'),
            ]);
        });
    }

    public function cancel(int $companyId, int $documentId, int $performedBy): PurchaseDocument
    {
        return DB::transaction(function () use ($companyId, $documentId, $performedBy): PurchaseDocument {
            /** @var PurchaseDocument $document */
            $document = PurchaseDocument::query()
                ->forCompany($companyId)
                ->whereKey($documentId)
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($document->status, [PurchaseDocument::STATUS_DRAFT, PurchaseDocument::STATUS_CONFIRMED], true)) {
                throw ValidationException::withMessages([
                    'purchase_document' => 'Apenas documentos em rascunho ou confirmados podem ser cancelados.',
                ]);
            }

            $hasIssuedPayments = SupplierPayment::query()
                ->forCompany($companyId)
                ->where('purchase_document_id', (int) $document->id)
                ->where('status', SupplierPayment::STATUS_ISSUED)
                ->exists();

            if ($hasIssuedPayments) {
                throw ValidationException::withMessages([
                    'purchase_document' => 'Nao e possivel cancelar documento com pagamentos emitidos.',
                ]);
            }

            $hasStockMovements = StockMovement::query()
                ->forCompany($companyId)
                ->where('reference_type', StockMovement::REFERENCE_PURCHASE_DOCUMENT)
                ->where('reference_id', (int) $document->id)
                ->exists();

            if ($hasStockMovements) {
                throw ValidationException::withMessages([
                    'purchase_document' => 'Nao e possivel cancelar documento com movimentos de stock associados.',
                ]);
            }

            if (! $document->canTransitionTo(PurchaseDocument::STATUS_CANCELLED)) {
                throw ValidationException::withMessages([
                    'purchase_document' => 'Transicao de estado invalida para cancelado.',
                ]);
            }

            $document->forceFill([
                ...$document->applyStatusTransition(PurchaseDocument::STATUS_CANCELLED),
                'cancelled_by' => $performedBy,
                'updated_by' => $performedBy,
            ])->save();

            return $document;
        });
    }

    private function syncPurchaseOrderReceptionStatus(PurchaseDocument $document): void
    {
        if (! $document->purchase_order_id) {
            return;
        }

        /** @var PurchaseOrder|null $purchaseOrder */
        $purchaseOrder = PurchaseOrder::query()
            ->forCompany((int) $document->company_id)
            ->whereKey((int) $document->purchase_order_id)
            ->lockForUpdate()
            ->first();

        if (! $purchaseOrder) {
            return;
        }

        /** @var Collection<int, \App\Models\PurchaseOrderItem> $orderItems */
        $orderItems = $purchaseOrder->items()
            ->lockForUpdate()
            ->get(['id', 'quantity'])
            ->keyBy('id');

        if ($orderItems->isEmpty()) {
            return;
        }

        $receivedByItemId = PurchaseDocumentItem::query()
            ->whereIn('purchase_order_item_id', $orderItems->keys()->all())
            ->whereIn('purchase_document_id', function ($subQuery) use ($document): void {
                $subQuery->select('id')
                    ->from('purchase_documents')
                    ->where('company_id', (int) $document->company_id)
                    ->where('purchase_order_id', (int) $document->purchase_order_id)
                    ->where('status', PurchaseDocument::STATUS_CONFIRMED);
            })
            ->selectRaw('purchase_order_item_id, SUM(quantity) as qty')
            ->groupBy('purchase_order_item_id')
            ->pluck('qty', 'purchase_order_item_id');

        $anyReceived = false;
        $allFullyReceived = true;

        foreach ($orderItems as $orderItem) {
            $orderedQty = round((float) $orderItem->quantity, 3);
            $receivedQty = round((float) ($receivedByItemId[(int) $orderItem->id] ?? 0), 3);

            if ($receivedQty > 0) {
                $anyReceived = true;
            }

            if ($receivedQty + 0.0005 < $orderedQty) {
                $allFullyReceived = false;
            }
        }

        if (! $anyReceived) {
            return;
        }

        $targetStatus = $allFullyReceived
            ? PurchaseOrder::STATUS_RECEIVED
            : PurchaseOrder::STATUS_PARTIALLY_RECEIVED;

        if ($purchaseOrder->status !== $targetStatus) {
            $purchaseOrder->forceFill([
                'status' => $targetStatus,
                'is_locked' => true,
            ])->save();
        }
    }
}
