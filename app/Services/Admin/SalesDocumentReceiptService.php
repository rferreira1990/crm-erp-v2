<?php

namespace App\Services\Admin;

use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\SalesDocument;
use App\Models\SalesDocumentReceipt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalesDocumentReceiptService
{
    private const EPSILON = 0.005;

    public function __construct(
        private readonly SalesDocumentPaymentStatusService $paymentStatusService
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function issueReceipt(int $companyId, int $salesDocumentId, int $createdBy, array $payload): SalesDocumentReceipt
    {
        return DB::transaction(function () use ($companyId, $salesDocumentId, $createdBy, $payload): SalesDocumentReceipt {
            /** @var SalesDocument $document */
            $document = SalesDocument::query()
                ->forCompany($companyId)
                ->whereKey($salesDocumentId)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $document->canReceivePayments()) {
                throw ValidationException::withMessages([
                    'sales_document' => 'So e possivel emitir recibo para Documento de Venda emitido.',
                ]);
            }

            if (! $document->customer_id) {
                throw ValidationException::withMessages([
                    'sales_document' => 'O Documento de Venda nao tem cliente associado.',
                ]);
            }

            Customer::query()
                ->forCompany($companyId)
                ->whereKey((int) $document->customer_id)
                ->firstOrFail();

            $paymentMethodId = isset($payload['payment_method_id']) ? (int) $payload['payment_method_id'] : null;
            if ($paymentMethodId !== null && $paymentMethodId > 0) {
                PaymentMethod::query()
                    ->visibleToCompany($companyId)
                    ->whereKey($paymentMethodId)
                    ->firstOrFail();
            } else {
                $paymentMethodId = null;
            }

            $openAmount = $this->paymentStatusService->openAmount($document);
            $amount = round((float) ($payload['amount'] ?? 0), 2);

            if ($amount <= 0) {
                throw ValidationException::withMessages([
                    'amount' => 'O valor do recibo deve ser superior a zero.',
                ]);
            }

            if ($amount > ($openAmount + self::EPSILON)) {
                throw ValidationException::withMessages([
                    'amount' => 'O valor do recibo nao pode ser superior ao valor em aberto.',
                ]);
            }

            /** @var SalesDocumentReceipt $receipt */
            $receipt = SalesDocumentReceipt::createWithGeneratedNumber($companyId, [
                'sales_document_id' => (int) $document->id,
                'customer_id' => (int) $document->customer_id,
                'receipt_date' => (string) $payload['receipt_date'],
                'payment_method_id' => $paymentMethodId,
                'amount' => $amount,
                'notes' => $this->normalizeNullableString($payload['notes'] ?? null),
                'status' => SalesDocumentReceipt::STATUS_ISSUED,
                'issued_at' => now(),
                'cancelled_at' => null,
                'created_by' => $createdBy,
                'cancelled_by' => null,
                'pdf_path' => null,
            ]);

            $this->paymentStatusService->recalculateForDocument(
                companyId: $companyId,
                salesDocumentId: (int) $document->id,
                updatedBy: $createdBy
            );

            return $receipt->fresh([
                'salesDocument:id,company_id,number,status,payment_status,grand_total',
                'customer:id,name',
                'paymentMethod:id,name',
                'creator:id,name',
            ]);
        });
    }

    public function cancelReceipt(int $companyId, int $receiptId, int $cancelledBy): SalesDocumentReceipt
    {
        return DB::transaction(function () use ($companyId, $receiptId, $cancelledBy): SalesDocumentReceipt {
            /** @var SalesDocumentReceipt $receipt */
            $receipt = SalesDocumentReceipt::query()
                ->forCompany($companyId)
                ->whereKey($receiptId)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $receipt->canCancel()) {
                throw ValidationException::withMessages([
                    'receipt' => 'Apenas recibos emitidos podem ser cancelados.',
                ]);
            }

            /** @var SalesDocument $document */
            $document = SalesDocument::query()
                ->forCompany($companyId)
                ->whereKey((int) $receipt->sales_document_id)
                ->lockForUpdate()
                ->firstOrFail();

            $receipt->forceFill([
                'status' => SalesDocumentReceipt::STATUS_CANCELLED,
                'cancelled_at' => now(),
                'cancelled_by' => $cancelledBy,
            ])->save();

            $this->paymentStatusService->recalculateForDocument(
                companyId: $companyId,
                salesDocumentId: (int) $document->id,
                updatedBy: $cancelledBy
            );

            return $receipt->fresh([
                'salesDocument:id,company_id,number,status,payment_status,grand_total',
                'customer:id,name',
                'paymentMethod:id,name',
                'creator:id,name',
                'canceller:id,name',
            ]);
        });
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }
}
