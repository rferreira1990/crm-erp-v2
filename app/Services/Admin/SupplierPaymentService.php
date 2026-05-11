<?php

namespace App\Services\Admin;

use App\Models\PaymentMethod;
use App\Models\PurchaseDocument;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SupplierPaymentService
{
    private const EPSILON = 0.005;

    public function __construct(
        private readonly PurchaseDocumentPaymentStatusService $paymentStatusService,
        private readonly SupplierPaymentNumberService $numberService
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function issuePayment(int $companyId, int $purchaseDocumentId, int $createdBy, array $payload): SupplierPayment
    {
        return DB::transaction(function () use ($companyId, $purchaseDocumentId, $createdBy, $payload): SupplierPayment {
            /** @var PurchaseDocument $document */
            $document = PurchaseDocument::query()
                ->forCompany($companyId)
                ->whereKey($purchaseDocumentId)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $document->canReceivePayments()) {
                throw ValidationException::withMessages([
                    'purchase_document' => 'So e possivel registar pagamento para Documento de Compra confirmado.',
                ]);
            }

            if (! $document->supplier_id) {
                throw ValidationException::withMessages([
                    'purchase_document' => 'O Documento de Compra nao tem fornecedor associado.',
                ]);
            }

            Supplier::query()
                ->forCompany($companyId)
                ->whereKey((int) $document->supplier_id)
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
                    'amount' => 'O valor do pagamento deve ser superior a zero.',
                ]);
            }

            if ($amount > ($openAmount + self::EPSILON)) {
                throw ValidationException::withMessages([
                    'amount' => 'O valor do pagamento nao pode ser superior ao valor em aberto.',
                ]);
            }

            $number = $this->numberService->next($companyId, (int) Carbon::parse((string) $payload['payment_date'])->year);

            /** @var SupplierPayment $payment */
            $payment = SupplierPayment::query()->create([
                'company_id' => $companyId,
                'number' => $number,
                'purchase_document_id' => (int) $document->id,
                'supplier_id' => (int) $document->supplier_id,
                'payment_date' => (string) $payload['payment_date'],
                'payment_method_id' => $paymentMethodId,
                'amount' => $amount,
                'notes' => $this->normalizeNullableString($payload['notes'] ?? null),
                'pdf_path' => null,
                'email_last_sent_to' => null,
                'email_last_sent_at' => null,
                'status' => SupplierPayment::STATUS_ISSUED,
                'issued_at' => now(),
                'cancelled_at' => null,
                'created_by' => $createdBy,
                'cancelled_by' => null,
            ]);

            $this->paymentStatusService->recalculateForDocument(
                companyId: $companyId,
                purchaseDocumentId: (int) $document->id,
                updatedBy: $createdBy
            );

            return $payment->fresh([
                'purchaseDocument:id,company_id,number,status,payment_status,grand_total',
                'supplier:id,name',
                'paymentMethod:id,name',
                'creator:id,name',
            ]);
        });
    }

    public function cancelPayment(int $companyId, int $paymentId, int $cancelledBy): SupplierPayment
    {
        return DB::transaction(function () use ($companyId, $paymentId, $cancelledBy): SupplierPayment {
            /** @var SupplierPayment $payment */
            $payment = SupplierPayment::query()
                ->forCompany($companyId)
                ->whereKey($paymentId)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $payment->canCancel()) {
                throw ValidationException::withMessages([
                    'payment' => 'Apenas pagamentos emitidos podem ser cancelados.',
                ]);
            }

            /** @var PurchaseDocument $document */
            $document = PurchaseDocument::query()
                ->forCompany($companyId)
                ->whereKey((int) $payment->purchase_document_id)
                ->lockForUpdate()
                ->firstOrFail();

            $payment->forceFill([
                'status' => SupplierPayment::STATUS_CANCELLED,
                'cancelled_at' => now(),
                'cancelled_by' => $cancelledBy,
            ])->save();

            $this->paymentStatusService->recalculateForDocument(
                companyId: $companyId,
                purchaseDocumentId: (int) $document->id,
                updatedBy: $cancelledBy
            );

            return $payment->fresh([
                'purchaseDocument:id,company_id,number,status,payment_status,grand_total',
                'supplier:id,name',
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
