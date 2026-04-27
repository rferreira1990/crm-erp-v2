<?php

namespace App\Services\Admin;

use App\Models\SalesDocument;
use App\Models\SalesDocumentReceipt;
use Illuminate\Support\Facades\DB;

class SalesDocumentPaymentStatusService
{
    private const EPSILON = 0.005;

    public function issuedReceiptsTotal(int $companyId, int $salesDocumentId): float
    {
        return round((float) SalesDocumentReceipt::query()
            ->forCompany($companyId)
            ->where('sales_document_id', $salesDocumentId)
            ->where('status', SalesDocumentReceipt::STATUS_ISSUED)
            ->sum('amount'), 2);
    }

    public function openAmount(SalesDocument $document): float
    {
        $received = $this->issuedReceiptsTotal((int) $document->company_id, (int) $document->id);
        $open = round((float) $document->grand_total - $received, 2);

        return $open > 0 ? $open : 0.0;
    }

    public function recalculateForDocument(int $companyId, int $salesDocumentId, ?int $updatedBy = null): SalesDocument
    {
        return DB::transaction(function () use ($companyId, $salesDocumentId, $updatedBy): SalesDocument {
            /** @var SalesDocument $document */
            $document = SalesDocument::query()
                ->forCompany($companyId)
                ->whereKey($salesDocumentId)
                ->lockForUpdate()
                ->firstOrFail();

            $received = $this->issuedReceiptsTotal($companyId, (int) $document->id);
            $newStatus = $this->resolvePaymentStatus($document, $received);

            $payload = [
                'payment_status' => $newStatus,
                'paid_at' => $newStatus === SalesDocument::PAYMENT_STATUS_PAID
                    ? ($document->paid_at ?: now())
                    : null,
            ];

            if ($updatedBy !== null) {
                $payload['updated_by'] = $updatedBy;
            }

            $document->forceFill($payload)->save();

            return $document;
        });
    }

    private function resolvePaymentStatus(SalesDocument $document, float $issuedReceiptsTotal): string
    {
        if ($issuedReceiptsTotal <= self::EPSILON) {
            return SalesDocument::PAYMENT_STATUS_UNPAID;
        }

        if ($issuedReceiptsTotal + self::EPSILON >= (float) $document->grand_total) {
            return SalesDocument::PAYMENT_STATUS_PAID;
        }

        return SalesDocument::PAYMENT_STATUS_PARTIAL;
    }
}
