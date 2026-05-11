<?php

namespace App\Services\Admin;

use App\Models\PurchaseDocument;
use App\Models\SupplierPayment;
use Illuminate\Support\Facades\DB;

class PurchaseDocumentPaymentStatusService
{
    private const EPSILON = 0.005;

    public function issuedPaymentsTotal(int $companyId, int $purchaseDocumentId): float
    {
        return round((float) SupplierPayment::query()
            ->forCompany($companyId)
            ->where('purchase_document_id', $purchaseDocumentId)
            ->where('status', SupplierPayment::STATUS_ISSUED)
            ->sum('amount'), 2);
    }

    public function openAmount(PurchaseDocument $document): float
    {
        $paid = $this->issuedPaymentsTotal((int) $document->company_id, (int) $document->id);
        $open = round((float) $document->grand_total - $paid, 2);

        return $open > 0 ? $open : 0.0;
    }

    public function recalculateForDocument(int $companyId, int $purchaseDocumentId, ?int $updatedBy = null): PurchaseDocument
    {
        return DB::transaction(function () use ($companyId, $purchaseDocumentId, $updatedBy): PurchaseDocument {
            /** @var PurchaseDocument $document */
            $document = PurchaseDocument::query()
                ->forCompany($companyId)
                ->whereKey($purchaseDocumentId)
                ->lockForUpdate()
                ->firstOrFail();

            $paid = $this->issuedPaymentsTotal($companyId, (int) $document->id);
            $newStatus = $this->resolvePaymentStatus((float) $document->grand_total, $paid);

            $payload = ['payment_status' => $newStatus];
            if ($updatedBy !== null) {
                $payload['updated_by'] = $updatedBy;
            }

            $document->forceFill($payload)->save();

            return $document;
        });
    }

    private function resolvePaymentStatus(float $grandTotal, float $paidTotal): string
    {
        if ($paidTotal <= self::EPSILON) {
            return PurchaseDocument::PAYMENT_STATUS_UNPAID;
        }

        if ($paidTotal + self::EPSILON >= $grandTotal) {
            return PurchaseDocument::PAYMENT_STATUS_PAID;
        }

        return PurchaseDocument::PAYMENT_STATUS_PARTIAL;
    }
}
