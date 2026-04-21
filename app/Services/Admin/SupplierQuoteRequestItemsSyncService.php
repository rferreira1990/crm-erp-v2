<?php

namespace App\Services\Admin;

use App\Models\Article;
use App\Models\Supplier;
use App\Models\SupplierQuoteRequest;
use App\Models\SupplierQuoteRequestItem;
use App\Models\SupplierQuoteRequestSupplier;
use Illuminate\Support\Collection;

class SupplierQuoteRequestItemsSyncService
{
    /**
     * @param array<int, array<string, mixed>> $items
     */
    public function syncItems(SupplierQuoteRequest $rfq, array $items, int $companyId): void
    {
        $rfq->items()->delete();

        $articleIds = collect($items)
            ->pluck('article_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        /** @var Collection<int, Article> $articles */
        $articles = Article::query()
            ->forCompany($companyId)
            ->whereIn('id', $articleIds)
            ->with(['unit:id,code,name'])
            ->get()
            ->keyBy('id');

        foreach ($items as $index => $item) {
            $lineType = (string) ($item['line_type'] ?? SupplierQuoteRequestItem::TYPE_ARTICLE);
            $articleId = isset($item['article_id']) ? (int) $item['article_id'] : null;
            $article = $articleId !== null ? $articles->get($articleId) : null;

            $description = trim((string) ($item['description'] ?? ''));
            if ($description === '' && $article) {
                $description = (string) $article->designation;
            }

            $quantity = isset($item['quantity']) ? (float) $item['quantity'] : 1.0;
            if (in_array($lineType, [SupplierQuoteRequestItem::TYPE_SECTION, SupplierQuoteRequestItem::TYPE_NOTE], true)) {
                $quantity = 1.0;
            }

            $unitName = trim((string) ($item['unit_name'] ?? ''));
            if ($unitName === '' && $article?->unit?->code) {
                $unitName = (string) $article->unit->code;
            }

            $rfq->items()->create([
                'company_id' => $companyId,
                'line_order' => (int) ($item['line_order'] ?? ($index + 1)),
                'line_type' => $lineType,
                'article_id' => $article?->id,
                'article_code' => $article?->code,
                'description' => $description !== '' ? $description : '-',
                'unit_name' => $unitName !== '' ? $unitName : null,
                'quantity' => $quantity,
                'internal_notes' => $this->normalizeNullableString($item['internal_notes'] ?? null),
            ]);
        }
    }

    /**
     * @param array<int, int|string> $supplierIds
     */
    public function syncSuppliers(SupplierQuoteRequest $rfq, array $supplierIds, int $companyId): void
    {
        $normalizedSupplierIds = collect($supplierIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        /** @var Collection<int, Supplier> $suppliers */
        $suppliers = Supplier::query()
            ->forCompany($companyId)
            ->whereIn('id', $normalizedSupplierIds)
            ->get()
            ->keyBy('id');

        $existingBySupplier = $rfq->invitedSuppliers()
            ->get()
            ->keyBy('supplier_id');

        foreach ($normalizedSupplierIds as $supplierId) {
            $supplier = $suppliers->get($supplierId);
            if (! $supplier) {
                continue;
            }

            /** @var SupplierQuoteRequestSupplier|null $existing */
            $existing = $existingBySupplier->get($supplierId);
            $snapshotPayload = [
                'supplier_name' => (string) $supplier->name,
                'supplier_email' => $this->normalizeNullableString($supplier->email),
            ];

            if ($existing) {
                if ($existing->status === SupplierQuoteRequestSupplier::STATUS_DRAFT) {
                    $existing->forceFill($snapshotPayload)->save();
                }

                continue;
            }

            $rfq->invitedSuppliers()->create([
                'company_id' => $companyId,
                'supplier_id' => $supplierId,
                'status' => SupplierQuoteRequestSupplier::STATUS_DRAFT,
                ...$snapshotPayload,
            ]);
        }

        $selected = array_flip($normalizedSupplierIds);
        foreach ($existingBySupplier as $supplierId => $existing) {
            if (isset($selected[(int) $supplierId])) {
                continue;
            }

            $canDelete = $existing->status === SupplierQuoteRequestSupplier::STATUS_DRAFT
                && $existing->supplierQuote()->doesntExist();

            if ($canDelete) {
                $existing->delete();
            }
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
}

