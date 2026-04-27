<?php

namespace App\Services\Admin;

use App\Models\Article;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\Unit;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ManualPurchaseOrderService
{
    /**
     * @param array<string, mixed> $payload
     */
    public function create(int $companyId, int $createdBy, array $payload): PurchaseOrder
    {
        return DB::transaction(function () use ($companyId, $createdBy, $payload): PurchaseOrder {
            /** @var Supplier $supplier */
            $supplier = Supplier::query()
                ->forCompany($companyId)
                ->whereKey((int) $payload['supplier_id'])
                ->lockForUpdate()
                ->firstOrFail();

            $lineInputs = $this->normalizeLineInputs((array) ($payload['items'] ?? []));
            if ($lineInputs->isEmpty()) {
                throw ValidationException::withMessages([
                    'items' => 'Tem de adicionar pelo menos uma linha.',
                ]);
            }

            $articleIds = $lineInputs
                ->pluck('article_id')
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

            $unitsByAlias = $this->unitsByAlias($companyId);
            $lineOrder = 1;
            $itemPayloads = [];
            $subtotal = 0.0;
            $discountTotal = 0.0;
            $taxTotal = 0.0;

            foreach ($lineInputs as $line) {
                $articleId = (int) ($line['article_id'] ?? 0);
                $article = $articleId > 0 ? $articlesById->get($articleId) : null;

                $quantity = round((float) $line['quantity'], 3);
                $unitPrice = round((float) $line['unit_price'], 4);
                $discountPercent = round((float) ($line['discount_percent'] ?? 0), 2);
                $lineSubtotal = round($quantity * $unitPrice, 2);
                $lineDiscountTotal = round($lineSubtotal * ($discountPercent / 100), 2);
                $lineTaxTotal = 0.0;
                $lineTotal = round($lineSubtotal - $lineDiscountTotal + $lineTaxTotal, 2);

                $subtotal += $lineSubtotal;
                $discountTotal += $lineDiscountTotal;
                $taxTotal += $lineTaxTotal;

                $lineType = $article
                    ? PurchaseOrderItem::LINE_TYPE_ARTICLE
                    : PurchaseOrderItem::LINE_TYPE_TEXT;

                $stockResolution = $article
                    ? PurchaseOrderItem::STOCK_RESOLUTION_RESOLVED_ARTICLE
                    : PurchaseOrderItem::STOCK_RESOLUTION_PENDING;

                $description = $this->normalizeNullableString($line['description'] ?? null)
                    ?: ($article?->designation ?: '-');

                $itemPayloads[] = [
                    'company_id' => $companyId,
                    'source_award_item_id' => null,
                    'source_supplier_quote_item_id' => null,
                    'line_type' => $lineType,
                    'stock_resolution_status' => $stockResolution,
                    'line_order' => $lineOrder++,
                    'article_id' => $article?->id,
                    'article_code' => $article?->code,
                    'description' => $description,
                    'unit_name' => $this->resolveUnitName($article, $line['unit_name'] ?? null, $unitsByAlias),
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'discount_percent' => $discountPercent,
                    'vat_percent' => 0,
                    'line_subtotal' => $lineSubtotal,
                    'line_discount_total' => $lineDiscountTotal,
                    'line_tax_total' => $lineTaxTotal,
                    'line_total' => $lineTotal,
                    'is_alternative' => false,
                    'alternative_description' => null,
                    'notes' => $this->normalizeNullableString($line['notes'] ?? null),
                ];
            }

            $subtotal = round($subtotal, 2);
            $discountTotal = round($discountTotal, 2);
            $taxTotal = round($taxTotal, 2);
            $shippingTotal = round((float) ($payload['shipping_total'] ?? 0), 2);
            $grandTotal = round($subtotal - $discountTotal + $taxTotal + $shippingTotal, 2);

            $purchaseOrder = PurchaseOrder::createWithGeneratedNumber($companyId, [
                'status' => PurchaseOrder::STATUS_DRAFT,
                'supplier_quote_request_id' => null,
                'supplier_quote_award_id' => null,
                'supplier_id' => (int) $supplier->id,
                'supplier_name_snapshot' => (string) $supplier->name,
                'supplier_email_snapshot' => $this->normalizeNullableString($supplier->email),
                'supplier_phone_snapshot' => $this->normalizeNullableString($supplier->phone ?: $supplier->mobile),
                'supplier_address_snapshot' => $this->buildSupplierAddressSnapshot($supplier),
                'issue_date' => (string) $payload['issue_date'],
                'expected_delivery_date' => $payload['expected_delivery_date'] ?? null,
                'currency' => 'EUR',
                'subtotal' => $subtotal,
                'discount_total' => $discountTotal,
                'shipping_total' => $shippingTotal,
                'tax_total' => $taxTotal,
                'grand_total' => $grandTotal,
                'internal_notes' => $this->normalizeNullableString($payload['internal_notes'] ?? null),
                'supplier_notes' => $this->normalizeNullableString($payload['supplier_notes'] ?? null),
                'created_by' => $createdBy,
                'assigned_user_id' => $createdBy,
                'is_locked' => false,
                'is_active' => true,
            ]);

            $purchaseOrder->items()->createMany($itemPayloads);

            return $purchaseOrder->fresh([
                'supplier:id,name',
                'items' => fn ($query) => $query->orderBy('line_order')->orderBy('id'),
            ]);
        });
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function update(int $companyId, int $purchaseOrderId, array $payload): PurchaseOrder
    {
        return DB::transaction(function () use ($companyId, $purchaseOrderId, $payload): PurchaseOrder {
            /** @var PurchaseOrder $purchaseOrder */
            $purchaseOrder = PurchaseOrder::query()
                ->forCompany($companyId)
                ->whereKey($purchaseOrderId)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $purchaseOrder->isEditableManualDraft()) {
                abort(404);
            }

            if ($purchaseOrder->receipts()->exists()) {
                throw ValidationException::withMessages([
                    'purchase_order' => 'Nao e possivel editar a encomenda porque ja tem rececoes associadas.',
                ]);
            }

            /** @var Supplier $supplier */
            $supplier = Supplier::query()
                ->forCompany($companyId)
                ->whereKey((int) $payload['supplier_id'])
                ->lockForUpdate()
                ->firstOrFail();

            $lineInputs = $this->normalizeLineInputs((array) ($payload['items'] ?? []));
            if ($lineInputs->isEmpty()) {
                throw ValidationException::withMessages([
                    'items' => 'Tem de adicionar pelo menos uma linha.',
                ]);
            }

            $articleIds = $lineInputs
                ->pluck('article_id')
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

            $unitsByAlias = $this->unitsByAlias($companyId);
            $lineOrder = 1;
            $itemPayloads = [];
            $subtotal = 0.0;
            $discountTotal = 0.0;
            $taxTotal = 0.0;

            foreach ($lineInputs as $line) {
                $articleId = (int) ($line['article_id'] ?? 0);
                $article = $articleId > 0 ? $articlesById->get($articleId) : null;

                $quantity = round((float) $line['quantity'], 3);
                $unitPrice = round((float) $line['unit_price'], 4);
                $discountPercent = round((float) ($line['discount_percent'] ?? 0), 2);
                $lineSubtotal = round($quantity * $unitPrice, 2);
                $lineDiscountTotal = round($lineSubtotal * ($discountPercent / 100), 2);
                $lineTaxTotal = 0.0;
                $lineTotal = round($lineSubtotal - $lineDiscountTotal + $lineTaxTotal, 2);

                $subtotal += $lineSubtotal;
                $discountTotal += $lineDiscountTotal;
                $taxTotal += $lineTaxTotal;

                $lineType = $article
                    ? PurchaseOrderItem::LINE_TYPE_ARTICLE
                    : PurchaseOrderItem::LINE_TYPE_TEXT;

                $stockResolution = $article
                    ? PurchaseOrderItem::STOCK_RESOLUTION_RESOLVED_ARTICLE
                    : PurchaseOrderItem::STOCK_RESOLUTION_PENDING;

                $description = $this->normalizeNullableString($line['description'] ?? null)
                    ?: ($article?->designation ?: '-');

                $itemPayloads[] = [
                    'company_id' => $companyId,
                    'source_award_item_id' => null,
                    'source_supplier_quote_item_id' => null,
                    'line_type' => $lineType,
                    'stock_resolution_status' => $stockResolution,
                    'line_order' => $lineOrder++,
                    'article_id' => $article?->id,
                    'article_code' => $article?->code,
                    'description' => $description,
                    'unit_name' => $this->resolveUnitName($article, $line['unit_name'] ?? null, $unitsByAlias),
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'discount_percent' => $discountPercent,
                    'vat_percent' => 0,
                    'line_subtotal' => $lineSubtotal,
                    'line_discount_total' => $lineDiscountTotal,
                    'line_tax_total' => $lineTaxTotal,
                    'line_total' => $lineTotal,
                    'is_alternative' => false,
                    'alternative_description' => null,
                    'notes' => $this->normalizeNullableString($line['notes'] ?? null),
                ];
            }

            $subtotal = round($subtotal, 2);
            $discountTotal = round($discountTotal, 2);
            $taxTotal = round($taxTotal, 2);
            $shippingTotal = round((float) ($payload['shipping_total'] ?? 0), 2);
            $grandTotal = round($subtotal - $discountTotal + $taxTotal + $shippingTotal, 2);

            $purchaseOrder->items()
                ->lockForUpdate()
                ->get(['id']);

            $purchaseOrder->items()->delete();
            $purchaseOrder->items()->createMany($itemPayloads);

            $purchaseOrder->forceFill([
                'supplier_id' => (int) $supplier->id,
                'supplier_name_snapshot' => (string) $supplier->name,
                'supplier_email_snapshot' => $this->normalizeNullableString($supplier->email),
                'supplier_phone_snapshot' => $this->normalizeNullableString($supplier->phone ?: $supplier->mobile),
                'supplier_address_snapshot' => $this->buildSupplierAddressSnapshot($supplier),
                'issue_date' => (string) $payload['issue_date'],
                'expected_delivery_date' => $payload['expected_delivery_date'] ?? null,
                'subtotal' => $subtotal,
                'discount_total' => $discountTotal,
                'shipping_total' => $shippingTotal,
                'tax_total' => $taxTotal,
                'grand_total' => $grandTotal,
                'internal_notes' => $this->normalizeNullableString($payload['internal_notes'] ?? null),
                'supplier_notes' => $this->normalizeNullableString($payload['supplier_notes'] ?? null),
            ])->save();

            return $purchaseOrder->fresh([
                'supplier:id,name',
                'items' => fn ($query) => $query->orderBy('line_order')->orderBy('id'),
            ]);
        });
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
                    'article_id' => isset($line['article_id']) ? (int) $line['article_id'] : null,
                    'description' => $this->normalizeNullableString($line['description'] ?? null),
                    'unit_name' => $this->normalizeNullableString($line['unit_name'] ?? null),
                    'quantity' => round((float) ($line['quantity'] ?? 0), 3),
                    'unit_price' => round((float) ($line['unit_price'] ?? 0), 4),
                    'discount_percent' => round((float) ($line['discount_percent'] ?? 0), 2),
                    'notes' => $this->normalizeNullableString($line['notes'] ?? null),
                ];
            })
            ->values();
    }

    /**
     * @return Collection<string, string>
     */
    private function unitsByAlias(int $companyId): Collection
    {
        /** @var Collection<int, Unit> $units */
        $units = Unit::query()
            ->visibleToCompany($companyId)
            ->orderBy('code')
            ->get(['id', 'code', 'name']);

        $aliases = collect();
        foreach ($units as $unit) {
            $code = trim((string) $unit->code);
            $name = trim((string) $unit->name);
            $canonical = $code !== '' ? $code : ($name !== '' ? $name : null);
            if ($canonical === null) {
                continue;
            }

            if ($code !== '') {
                $aliases->put(mb_strtolower($code), $canonical);
            }
            if ($name !== '') {
                $aliases->put(mb_strtolower($name), $canonical);
            }
        }

        return $aliases;
    }

    /**
     * @param Collection<string, string> $unitsByAlias
     */
    private function resolveUnitName(?Article $article, mixed $unitName, Collection $unitsByAlias): ?string
    {
        if ($article !== null) {
            $code = trim((string) ($article->unit?->code ?? ''));
            if ($code !== '') {
                return $code;
            }

            $name = trim((string) ($article->unit?->name ?? ''));
            if ($name !== '') {
                return $name;
            }
        }

        $normalized = $this->normalizeNullableString($unitName);
        if ($normalized === null) {
            return null;
        }

        return $unitsByAlias->get(mb_strtolower($normalized), $normalized);
    }

    private function buildSupplierAddressSnapshot(Supplier $supplier): ?string
    {
        $address = trim((string) $supplier->address);
        $location = trim(implode(' ', array_filter([
            $supplier->postal_code,
            $supplier->locality,
            $supplier->city,
        ], fn ($value): bool => trim((string) $value) !== '')));

        $snapshot = trim(implode(' | ', array_filter([
            $address,
            $location,
        ], fn ($value): bool => trim((string) $value) !== '')));

        return $snapshot !== '' ? $snapshot : null;
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
