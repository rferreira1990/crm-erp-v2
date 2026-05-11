<?php

namespace App\Services\Admin;

use App\Models\Article;
use App\Models\PurchaseDocument;
use App\Models\PurchaseDocumentItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\Unit;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseDocumentCreationService
{
    public function __construct(
        private readonly PurchaseDocumentNumberService $numberService
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function createDraft(int $companyId, int $createdBy, array $payload): PurchaseDocument
    {
        return DB::transaction(function () use ($companyId, $createdBy, $payload): PurchaseDocument {
            $prepared = $this->prepareDocumentData($companyId, $payload);

            $number = $this->numberService->next($companyId, (int) Carbon::parse($prepared['issue_date'])->year);

            /** @var PurchaseDocument $document */
            $document = PurchaseDocument::query()->create([
                'company_id' => $companyId,
                'number' => $number,
                'supplier_document_number' => $prepared['supplier_document_number'],
                'supplier_id' => $prepared['supplier_id'],
                'purchase_order_id' => $prepared['purchase_order_id'],
                'status' => PurchaseDocument::STATUS_DRAFT,
                'payment_status' => PurchaseDocument::PAYMENT_STATUS_UNPAID,
                'issue_date' => $prepared['issue_date'],
                'due_date' => $prepared['due_date'],
                'notes' => $prepared['notes'],
                'currency' => 'EUR',
                'subtotal' => $prepared['subtotal'],
                'discount_total' => $prepared['discount_total'],
                'tax_total' => $prepared['tax_total'],
                'grand_total' => $prepared['grand_total'],
                'confirmed_at' => null,
                'cancelled_at' => null,
                'created_by' => $createdBy,
                'updated_by' => null,
                'cancelled_by' => null,
            ]);

            $document->items()->createMany($prepared['items_payload']);

            return $document->fresh([
                'supplier:id,name',
                'purchaseOrder:id,number,supplier_id',
                'items' => fn ($query) => $query->orderBy('line_order')->orderBy('id'),
            ]);
        });
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function updateDraft(int $companyId, int $documentId, int $updatedBy, array $payload): PurchaseDocument
    {
        return DB::transaction(function () use ($companyId, $documentId, $updatedBy, $payload): PurchaseDocument {
            /** @var PurchaseDocument $document */
            $document = PurchaseDocument::query()
                ->forCompany($companyId)
                ->whereKey($documentId)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $document->isEditableDraft()) {
                abort(404);
            }

            $hasIssuedPayments = $document->payments()
                ->where('status', \App\Models\SupplierPayment::STATUS_ISSUED)
                ->exists();

            if ($hasIssuedPayments) {
                throw ValidationException::withMessages([
                    'purchase_document' => 'Nao e possivel editar o documento porque ja tem pagamentos emitidos.',
                ]);
            }

            $prepared = $this->prepareDocumentData($companyId, $payload);

            $document->items()->lockForUpdate()->get(['id']);
            $document->items()->delete();
            $document->items()->createMany($prepared['items_payload']);

            $document->forceFill([
                'supplier_document_number' => $prepared['supplier_document_number'],
                'supplier_id' => $prepared['supplier_id'],
                'purchase_order_id' => $prepared['purchase_order_id'],
                'issue_date' => $prepared['issue_date'],
                'due_date' => $prepared['due_date'],
                'notes' => $prepared['notes'],
                'subtotal' => $prepared['subtotal'],
                'discount_total' => $prepared['discount_total'],
                'tax_total' => $prepared['tax_total'],
                'grand_total' => $prepared['grand_total'],
                'updated_by' => $updatedBy,
            ])->save();

            return $document->fresh([
                'supplier:id,name',
                'purchaseOrder:id,number,supplier_id',
                'items' => fn ($query) => $query->orderBy('line_order')->orderBy('id'),
            ]);
        });
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{
     *   supplier_document_number:?string,
     *   supplier_id:int,
     *   purchase_order_id:?int,
     *   issue_date:string,
     *   due_date:?string,
     *   notes:?string,
     *   subtotal:float,
     *   discount_total:float,
     *   tax_total:float,
     *   grand_total:float,
     *   items_payload:array<int, array<string, mixed>>
     * }
     */
    private function prepareDocumentData(int $companyId, array $payload): array
    {
        /** @var Supplier $supplier */
        $supplier = Supplier::query()
            ->forCompany($companyId)
            ->whereKey((int) ($payload['supplier_id'] ?? 0))
            ->lockForUpdate()
            ->firstOrFail();

        $purchaseOrder = null;
        $purchaseOrderId = isset($payload['purchase_order_id']) ? (int) $payload['purchase_order_id'] : null;
        if ($purchaseOrderId && $purchaseOrderId > 0) {
            $purchaseOrder = PurchaseOrder::query()
                ->forCompany($companyId)
                ->whereKey($purchaseOrderId)
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $purchaseOrder->supplier_id !== (int) $supplier->id) {
                throw ValidationException::withMessages([
                    'supplier_id' => 'O fornecedor nao coincide com a encomenda selecionada.',
                ]);
            }
        }

        $lineInputs = $this->normalizeLineInputs((array) ($payload['items'] ?? []));
        if ($lineInputs->isEmpty()) {
            throw ValidationException::withMessages([
                'items' => 'Tem de adicionar pelo menos uma linha.',
            ]);
        }

        $purchaseOrderItemIds = $lineInputs
            ->pluck('purchase_order_item_id')
            ->filter(fn ($id): bool => (int) $id > 0)
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($purchaseOrder === null && $purchaseOrderItemIds !== []) {
            throw ValidationException::withMessages([
                'items' => 'Linhas com referencia de encomenda exigem uma encomenda associada.',
            ]);
        }

        /** @var Collection<int, PurchaseOrderItem> $purchaseOrderItemsById */
        $purchaseOrderItemsById = collect();
        if ($purchaseOrder !== null && $purchaseOrderItemIds !== []) {
            $purchaseOrderItemsById = PurchaseOrderItem::query()
                ->forCompany($companyId)
                ->where('purchase_order_id', (int) $purchaseOrder->id)
                ->whereIn('id', $purchaseOrderItemIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if (count($purchaseOrderItemIds) !== $purchaseOrderItemsById->count()) {
                abort(404);
            }
        }

        $articleIds = $lineInputs
            ->pluck('article_id')
            ->filter(fn ($id): bool => (int) $id > 0)
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        $unitIds = $lineInputs
            ->pluck('unit_id')
            ->filter(fn ($id): bool => (int) $id > 0)
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        /** @var Collection<int, Article> $articlesById */
        $articlesById = Article::query()
            ->forCompany($companyId)
            ->with('unit:id,code,name')
            ->whereIn('id', $articleIds)
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        if (count($articleIds) !== $articlesById->count()) {
            abort(404);
        }

        /** @var Collection<int, Unit> $unitsById */
        $unitsById = Unit::query()
            ->visibleToCompany($companyId)
            ->whereIn('id', $unitIds)
            ->get()
            ->keyBy('id');

        if (count($unitIds) !== $unitsById->count()) {
            abort(404);
        }

        $lineOrder = 1;
        $itemPayloads = [];
        $subtotal = 0.0;
        $discountTotal = 0.0;
        $taxTotal = 0.0;

        foreach ($lineInputs as $line) {
            $purchaseOrderItemId = (int) ($line['purchase_order_item_id'] ?? 0);
            if ($purchaseOrderItemId > 0 && ! $purchaseOrderItemsById->has($purchaseOrderItemId)) {
                abort(404);
            }

            $articleId = (int) ($line['article_id'] ?? 0);
            $article = $articleId > 0 ? $articlesById->get($articleId) : null;

            $resolvedUnitId = (int) ($line['unit_id'] ?? 0);
            if ($resolvedUnitId <= 0 && $article?->unit_id) {
                $resolvedUnitId = (int) $article->unit_id;
            }

            $unit = $resolvedUnitId > 0 ? $unitsById->get($resolvedUnitId) : null;
            if ($resolvedUnitId > 0 && ! $unit && $article?->unit?->id === $resolvedUnitId) {
                $unit = $article->unit;
            }

            $quantity = round((float) $line['quantity'], 3);
            $unitPrice = round((float) $line['unit_price'], 4);
            $discountPercent = round((float) ($line['discount_percent'] ?? 0), 2);
            $taxRate = round((float) ($line['tax_rate'] ?? 0), 2);

            $amounts = PurchaseDocumentItem::calculateAmounts($quantity, $unitPrice, $discountPercent, $taxRate);

            $subtotal += $amounts['line_subtotal'];
            $discountTotal += $amounts['line_discount_total'];
            $taxTotal += $amounts['line_tax_total'];

            $description = $this->normalizeNullableString($line['description'] ?? null)
                ?: ($article?->designation ?: '-');

            $itemPayloads[] = [
                'company_id' => $companyId,
                'line_order' => $lineOrder++,
                'purchase_order_item_id' => $purchaseOrderItemId > 0 ? $purchaseOrderItemId : null,
                'article_id' => $article?->id,
                'description' => $description,
                'unit_id' => $unit?->id,
                'unit_name_snapshot' => $this->resolveUnitSnapshot($unit, $article, $line['unit_name_snapshot'] ?? null),
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'discount_percent' => $discountPercent,
                'line_subtotal' => $amounts['line_subtotal'],
                'line_discount_total' => $amounts['line_discount_total'],
                'tax_rate' => $taxRate,
                'line_tax_total' => $amounts['line_tax_total'],
                'line_total' => $amounts['line_total'],
            ];
        }

        $subtotal = round($subtotal, 2);
        $discountTotal = round($discountTotal, 2);
        $taxTotal = round($taxTotal, 2);

        return [
            'supplier_document_number' => $this->normalizeNullableString($payload['supplier_document_number'] ?? null),
            'supplier_id' => (int) $supplier->id,
            'purchase_order_id' => $purchaseOrder?->id,
            'issue_date' => (string) $payload['issue_date'],
            'due_date' => $payload['due_date'] ?? null,
            'notes' => $this->normalizeNullableString($payload['notes'] ?? null),
            'subtotal' => $subtotal,
            'discount_total' => $discountTotal,
            'tax_total' => $taxTotal,
            'grand_total' => round($subtotal - $discountTotal + $taxTotal, 2),
            'items_payload' => $itemPayloads,
        ];
    }

    /**
     * @param array<int, mixed> $lines
     * @return Collection<int, array<string, mixed>>
     */
    private function normalizeLineInputs(array $lines): Collection
    {
        return collect($lines)
            ->filter(fn ($line): bool => is_array($line))
            ->map(function (array $line): array {
                return [
                    'purchase_order_item_id' => isset($line['purchase_order_item_id']) ? (int) $line['purchase_order_item_id'] : null,
                    'article_id' => isset($line['article_id']) ? (int) $line['article_id'] : null,
                    'description' => $this->normalizeNullableString($line['description'] ?? null),
                    'unit_id' => isset($line['unit_id']) ? (int) $line['unit_id'] : null,
                    'unit_name_snapshot' => $this->normalizeNullableString($line['unit_name_snapshot'] ?? null),
                    'quantity' => round((float) ($line['quantity'] ?? 0), 3),
                    'unit_price' => round((float) ($line['unit_price'] ?? 0), 4),
                    'discount_percent' => round((float) ($line['discount_percent'] ?? 0), 2),
                    'tax_rate' => round((float) ($line['tax_rate'] ?? 0), 2),
                ];
            })
            ->values();
    }

    private function resolveUnitSnapshot(?Unit $unit, ?Article $article, mixed $fallback): ?string
    {
        if ($unit) {
            $code = trim((string) $unit->code);
            if ($code !== '') {
                return $code;
            }

            $name = trim((string) $unit->name);
            if ($name !== '') {
                return $name;
            }
        }

        if ($article?->unit) {
            $articleCode = trim((string) $article->unit->code);
            if ($articleCode !== '') {
                return $articleCode;
            }

            $articleName = trim((string) $article->unit->name);
            if ($articleName !== '') {
                return $articleName;
            }
        }

        return $this->normalizeNullableString($fallback);
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
