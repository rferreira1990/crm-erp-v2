<?php

namespace App\Services\Admin;

use App\Models\SupplierQuoteRequest;
use App\Models\SupplierQuoteRequestSupplier;

class SupplierQuoteRequestStatusService
{
    public function syncFromSupplierResponses(SupplierQuoteRequest $rfq): void
    {
        if ($rfq->status === SupplierQuoteRequest::STATUS_CANCELLED) {
            return;
        }

        $invites = $rfq->invitedSuppliers()->get(['status']);
        $totalSuppliers = $invites->count();

        if ($totalSuppliers === 0) {
            $rfq->forceFill(['status' => SupplierQuoteRequest::STATUS_DRAFT])->save();

            return;
        }

        $respondedCount = $invites->where('status', SupplierQuoteRequestSupplier::STATUS_RESPONDED)->count();
        $sentCount = $invites->whereIn('status', [
            SupplierQuoteRequestSupplier::STATUS_SENT,
            SupplierQuoteRequestSupplier::STATUS_RESPONDED,
            SupplierQuoteRequestSupplier::STATUS_DECLINED,
            SupplierQuoteRequestSupplier::STATUS_NO_RESPONSE,
        ])->count();

        $status = SupplierQuoteRequest::STATUS_DRAFT;
        if ($respondedCount === $totalSuppliers) {
            $status = SupplierQuoteRequest::STATUS_RECEIVED;
        } elseif ($respondedCount > 0) {
            $status = SupplierQuoteRequest::STATUS_PARTIALLY_RECEIVED;
        } elseif ($sentCount > 0) {
            $status = SupplierQuoteRequest::STATUS_SENT;
        }

        if ($rfq->status !== $status) {
            $rfq->forceFill(['status' => $status])->save();
        }
    }
}

