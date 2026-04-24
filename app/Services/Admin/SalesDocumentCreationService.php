<?php

namespace App\Services\Admin;

use App\Models\Article;
use App\Models\ConstructionSite;
use App\Models\ConstructionSiteMaterialUsage;
use App\Models\ConstructionSiteMaterialUsageItem;
use App\Models\ConstructionSiteTimeEntry;
use App\Models\Customer;
use App\Models\CustomerContact;
use App\Models\Quote;
use App\Models\SalesDocument;
use App\Models\SalesDocumentItem;
use App\Models\Unit;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalesDocumentCreationService
{
    /**
     * @param array<string, mixed> $payload
     */
    public function createDraft(int $companyId, int $createdBy, array $payload): SalesDocument
    {
        return DB::transaction(function () use ($companyId, $createdBy, $payload): SalesDocument {
            $prepared = $this->prepareDocumentData($companyId, $payload);

            $salesDocument = SalesDocument::createWithGeneratedNumber($companyId, [
                'source_type' => $prepared['source_type'],
                'quote_id' => $prepared['quote_id'],
                'construction_site_id' => $prepared['construction_site_id'],
                'customer_id' => $prepared['customer_id'],
                'customer_contact_id' => $prepared['customer_contact_id'],
                'customer_name_snapshot' => $prepared['customer_name_snapshot'],
                'customer_nif_snapshot' => $prepared['customer_nif_snapshot'],
                'customer_email_snapshot' => $prepared['customer_email_snapshot'],
                'customer_phone_snapshot' => $prepared['customer_phone_snapshot'],
                'customer_address_snapshot' => $prepared['customer_address_snapshot'],
                'customer_contact_name_snapshot' => $prepared['customer_contact_name_snapshot'],
                'customer_contact_email_snapshot' => $prepared['customer_contact_email_snapshot'],
                'customer_contact_phone_snapshot' => $prepared['customer_contact_phone_snapshot'],
                'status' => SalesDocument::STATUS_DRAFT,
                'issue_date' => $prepared['issue_date'],
                'due_date' => $prepared['due_date'],
                'notes' => $prepared['notes'],
                'currency' => 'EUR',
                'subtotal' => $prepared['subtotal'],
                'discount_total' => $prepared['discount_total'],
                'tax_total' => $prepared['tax_total'],
                'grand_total' => $prepared['grand_total'],
                'issued_at' => null,
                'created_by' => $createdBy,
                'updated_by' => null,
            ]);

            $salesDocument->items()->createMany($prepared['items_payload']);

            return $salesDocument->fresh([
                'customer:id,name',
                'quote:id,number',
                'constructionSite:id,code,name',
                'items' => fn ($query) => $query->orderBy('line_order')->orderBy('id'),
            ]);
        });
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function updateDraft(int $companyId, int $documentId, int $updatedBy, array $payload): SalesDocument
    {
        return DB::transaction(function () use ($companyId, $documentId, $updatedBy, $payload): SalesDocument {
            /** @var SalesDocument $document */
            $document = SalesDocument::query()
                ->forCompany($companyId)
                ->whereKey($documentId)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $document->isEditableDraft()) {
                abort(404);
            }

            $hasStockMovements = $document->stockMovements()->exists();
            if ($hasStockMovements) {
                throw ValidationException::withMessages([
                    'document' => 'Nao e possivel editar o documento porque ja existem movimentos de stock associados.',
                ]);
            }

            $prepared = $this->prepareDocumentData($companyId, [
                ...$payload,
                'source_type' => $document->source_type,
                'quote_id' => $document->quote_id,
                'construction_site_id' => $document->construction_site_id,
            ]);

            $document->items()->lockForUpdate()->get(['id']);
            $document->items()->delete();
            $document->items()->createMany($prepared['items_payload']);

            $document->forceFill([
                'customer_id' => $prepared['customer_id'],
                'customer_contact_id' => $prepared['customer_contact_id'],
                'customer_name_snapshot' => $prepared['customer_name_snapshot'],
                'customer_nif_snapshot' => $prepared['customer_nif_snapshot'],
                'customer_email_snapshot' => $prepared['customer_email_snapshot'],
                'customer_phone_snapshot' => $prepared['customer_phone_snapshot'],
                'customer_address_snapshot' => $prepared['customer_address_snapshot'],
                'customer_contact_name_snapshot' => $prepared['customer_contact_name_snapshot'],
                'customer_contact_email_snapshot' => $prepared['customer_contact_email_snapshot'],
                'customer_contact_phone_snapshot' => $prepared['customer_contact_phone_snapshot'],
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
                'customer:id,name',
                'quote:id,number',
                'constructionSite:id,code,name',
                'items' => fn ($query) => $query->orderBy('line_order')->orderBy('id'),
            ]);
        });
    }

    /**
     * @return array{
     *   source_type:string,
     *   quote_id:?int,
     *   construction_site_id:?int,
     *   customer_id:?int,
     *   customer_contact_id:?int,
     *   issue_date:string,
     *   due_date:?string,
     *   notes:?string,
     *   items:array<int, array<string, mixed>>
     * }
     */
    public function buildCreateDefaultsForSource(
        int $companyId,
        string $sourceType,
        ?int $quoteId = null,
        ?int $constructionSiteId = null
    ): array {
        $normalizedSource = strtolower(trim($sourceType));
        $today = now()->toDateString();

        if ($normalizedSource === SalesDocument::SOURCE_QUOTE && $quoteId) {
            $quote = Quote::query()
                ->forCompany($companyId)
                ->whereKey($quoteId)
                ->with([
                    'items' => fn ($query) => $query->orderBy('sort_order')->orderBy('id'),
                ])
                ->firstOrFail();

            $items = $quote->items->map(function ($item): array {
                return [
                    'article_id' => $item->article_id ? (int) $item->article_id : null,
                    'description' => (string) ($item->description ?: $item->article_designation ?: '-'),
                    'unit_id' => $item->unit_id ? (int) $item->unit_id : null,
                    'unit_name_snapshot' => $this->normalizeNullableString(
                        $item->unit_code ?? $item->unit_name
                    ),
                    'quantity' => number_format((float) $item->quantity, 3, '.', ''),
                    'unit_price' => number_format((float) $item->unit_price, 4, '.', ''),
                    'discount_percent' => number_format((float) ($item->discount_percent ?? 0), 2, '.', ''),
                    'tax_rate' => number_format((float) ($item->vat_rate_percentage ?? 0), 2, '.', ''),
                ];
            })->values()->all();

            if ($items === []) {
                $items[] = $this->emptyLine();
            }

            return [
                'source_type' => SalesDocument::SOURCE_QUOTE,
                'quote_id' => (int) $quote->id,
                'construction_site_id' => null,
                'customer_id' => $quote->customer_id ? (int) $quote->customer_id : null,
                'customer_contact_id' => $quote->customer_contact_id ? (int) $quote->customer_contact_id : null,
                'issue_date' => $today,
                'due_date' => $quote->valid_until?->toDateString(),
                'notes' => $this->normalizeNullableString($quote->header_notes),
                'items' => $items,
            ];
        }

        if ($normalizedSource === SalesDocument::SOURCE_CONSTRUCTION_SITE && $constructionSiteId) {
            $site = ConstructionSite::query()
                ->forCompany($companyId)
                ->whereKey($constructionSiteId)
                ->firstOrFail();

            $items = $this->buildConstructionSiteDefaultLines($companyId, $site);
            if ($items === []) {
                $items[] = [
                    ...$this->emptyLine(),
                    'description' => 'Documento gerado a partir da obra '.(string) $site->code,
                    'quantity' => '1.000',
                    'unit_price' => '0.0000',
                ];
            }

            return [
                'source_type' => SalesDocument::SOURCE_CONSTRUCTION_SITE,
                'quote_id' => $site->quote_id ? (int) $site->quote_id : null,
                'construction_site_id' => (int) $site->id,
                'customer_id' => $site->customer_id ? (int) $site->customer_id : null,
                'customer_contact_id' => $site->customer_contact_id ? (int) $site->customer_contact_id : null,
                'issue_date' => $today,
                'due_date' => null,
                'notes' => 'Gerado a partir da obra '.$site->code.'.',
                'items' => $items,
            ];
        }

        return [
            'source_type' => SalesDocument::SOURCE_MANUAL,
            'quote_id' => null,
            'construction_site_id' => null,
            'customer_id' => null,
            'customer_contact_id' => null,
            'issue_date' => $today,
            'due_date' => null,
            'notes' => null,
            'items' => [$this->emptyLine()],
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function prepareDocumentData(int $companyId, array $payload): array
    {
        $sourceType = strtolower(trim((string) ($payload['source_type'] ?? SalesDocument::SOURCE_MANUAL)));
        if (! in_array($sourceType, SalesDocument::sources(), true)) {
            throw ValidationException::withMessages([
                'source_type' => 'Origem do documento invalida.',
            ]);
        }

        $quote = null;
        $constructionSite = null;
        if ($sourceType === SalesDocument::SOURCE_QUOTE) {
            $quote = Quote::query()
                ->forCompany($companyId)
                ->whereKey((int) ($payload['quote_id'] ?? 0))
                ->lockForUpdate()
                ->firstOrFail();
        }

        if ($sourceType === SalesDocument::SOURCE_CONSTRUCTION_SITE) {
            $constructionSite = ConstructionSite::query()
                ->forCompany($companyId)
                ->whereKey((int) ($payload['construction_site_id'] ?? 0))
                ->lockForUpdate()
                ->firstOrFail();
        }

        $resolvedCustomerId = match ($sourceType) {
            SalesDocument::SOURCE_QUOTE => $quote?->customer_id ? (int) $quote->customer_id : null,
            SalesDocument::SOURCE_CONSTRUCTION_SITE => $constructionSite?->customer_id ? (int) $constructionSite->customer_id : null,
            default => isset($payload['customer_id']) ? (int) $payload['customer_id'] : null,
        };

        if (! $resolvedCustomerId || $resolvedCustomerId <= 0) {
            throw ValidationException::withMessages([
                'customer_id' => 'O cliente e obrigatorio para criar o documento.',
            ]);
        }

        /** @var Customer $customer */
        $customer = Customer::query()
            ->forCompany($companyId)
            ->whereKey($resolvedCustomerId)
            ->lockForUpdate()
            ->firstOrFail();

        $resolvedContactId = match ($sourceType) {
            SalesDocument::SOURCE_QUOTE => $quote?->customer_contact_id ? (int) $quote->customer_contact_id : null,
            SalesDocument::SOURCE_CONSTRUCTION_SITE => $constructionSite?->customer_contact_id ? (int) $constructionSite->customer_contact_id : null,
            default => isset($payload['customer_contact_id']) ? (int) $payload['customer_contact_id'] : null,
        };

        $customerContact = null;
        if ($resolvedContactId && $resolvedContactId > 0) {
            $customerContact = CustomerContact::query()
                ->forCompany($companyId)
                ->where('customer_id', (int) $customer->id)
                ->whereKey($resolvedContactId)
                ->lockForUpdate()
                ->firstOrFail();
        }

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
            $amounts = SalesDocumentItem::calculateAmounts($quantity, $unitPrice, $discountPercent, $taxRate);

            $subtotal += $amounts['line_subtotal'];
            $discountTotal += $amounts['line_discount_total'];
            $taxTotal += $amounts['line_tax_total'];

            $description = $this->normalizeNullableString($line['description'] ?? null)
                ?: ($article?->designation ?: '-');

            $itemPayloads[] = [
                'company_id' => $companyId,
                'line_order' => $lineOrder++,
                'article_id' => $article?->id,
                'article_code' => $article?->code,
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

        $customerAddressSnapshot = $this->buildCustomerAddressSnapshot($customer);

        return [
            'source_type' => $sourceType,
            'quote_id' => $quote?->id,
            'construction_site_id' => $constructionSite?->id,
            'customer_id' => (int) $customer->id,
            'customer_contact_id' => $customerContact?->id,
            'customer_name_snapshot' => $this->normalizeNullableString($customer->name),
            'customer_nif_snapshot' => $this->normalizeNullableString($customer->nif),
            'customer_email_snapshot' => $this->normalizeNullableString($customer->email),
            'customer_phone_snapshot' => $this->normalizeNullableString($customer->phone ?: $customer->mobile),
            'customer_address_snapshot' => $customerAddressSnapshot,
            'customer_contact_name_snapshot' => $this->normalizeNullableString($customerContact?->name),
            'customer_contact_email_snapshot' => $this->normalizeNullableString($customerContact?->email),
            'customer_contact_phone_snapshot' => $this->normalizeNullableString($customerContact?->phone),
            'issue_date' => (string) $payload['issue_date'],
            'due_date' => $payload['due_date'] ?? null,
            'notes' => $this->normalizeNullableString($payload['notes'] ?? null),
            'subtotal' => round($subtotal, 2),
            'discount_total' => round($discountTotal, 2),
            'tax_total' => round($taxTotal, 2),
            'grand_total' => round($subtotal - $discountTotal + $taxTotal, 2),
            'items_payload' => $itemPayloads,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildConstructionSiteDefaultLines(int $companyId, ConstructionSite $site): array
    {
        if ($site->quote_id) {
            $quote = Quote::query()
                ->forCompany($companyId)
                ->whereKey((int) $site->quote_id)
                ->with([
                    'items' => fn ($query) => $query->orderBy('sort_order')->orderBy('id'),
                ])
                ->first();

            if ($quote && $quote->items->isNotEmpty()) {
                return $quote->items->map(function ($item): array {
                    return [
                        'article_id' => $item->article_id ? (int) $item->article_id : null,
                        'description' => (string) ($item->description ?: $item->article_designation ?: '-'),
                        'unit_id' => $item->unit_id ? (int) $item->unit_id : null,
                        'unit_name_snapshot' => $this->normalizeNullableString($item->unit_code ?? $item->unit_name),
                        'quantity' => number_format((float) $item->quantity, 3, '.', ''),
                        'unit_price' => number_format((float) $item->unit_price, 4, '.', ''),
                        'discount_percent' => number_format((float) ($item->discount_percent ?? 0), 2, '.', ''),
                        'tax_rate' => number_format((float) ($item->vat_rate_percentage ?? 0), 2, '.', ''),
                    ];
                })->values()->all();
            }
        }

        $usageItems = ConstructionSiteMaterialUsageItem::query()
            ->forCompany($companyId)
            ->selectRaw('article_id')
            ->selectRaw('MAX(article_code) as article_code')
            ->selectRaw('MAX(description) as description')
            ->selectRaw('MAX(unit_name) as unit_name')
            ->selectRaw('SUM(quantity) as total_quantity')
            ->selectRaw('CASE WHEN SUM(quantity) > 0 THEN SUM(quantity * COALESCE(unit_cost, 0)) / SUM(quantity) ELSE 0 END as avg_unit_cost')
            ->whereHas('usage', function ($query) use ($site): void {
                $query->where('construction_site_id', (int) $site->id)
                    ->where('status', ConstructionSiteMaterialUsage::STATUS_POSTED);
            })
            ->groupBy('article_id')
            ->orderByRaw('MAX(article_code) ASC')
            ->get();

        $articleIds = $usageItems
            ->pluck('article_id')
            ->filter(fn ($id): bool => (int) $id > 0)
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        /** @var Collection<int, Article> $articlesById */
        $articlesById = Article::query()
            ->forCompany($companyId)
            ->whereIn('id', $articleIds)
            ->get()
            ->keyBy('id');

        $items = [];
        foreach ($usageItems as $usageItem) {
            $articleId = $usageItem->article_id ? (int) $usageItem->article_id : null;
            $article = $articleId ? $articlesById->get($articleId) : null;

            $items[] = [
                'article_id' => $article?->id,
                'description' => (string) ($usageItem->description ?: 'Consumo de material'),
                'unit_id' => $article?->unit_id ? (int) $article->unit_id : null,
                'unit_name_snapshot' => $this->normalizeNullableString($usageItem->unit_name),
                'quantity' => number_format((float) ($usageItem->total_quantity ?? 0), 3, '.', ''),
                'unit_price' => number_format((float) ($usageItem->avg_unit_cost ?? 0), 4, '.', ''),
                'discount_percent' => '0.00',
                'tax_rate' => '0.00',
            ];
        }

        $laborTotal = round((float) ConstructionSiteTimeEntry::query()
            ->forCompany($companyId)
            ->where('construction_site_id', (int) $site->id)
            ->sum('total_cost'), 4);

        if ($laborTotal > 0) {
            $items[] = [
                'article_id' => null,
                'description' => 'Mao de obra agregada da obra '.$site->code,
                'unit_id' => null,
                'unit_name_snapshot' => null,
                'quantity' => '1.000',
                'unit_price' => number_format($laborTotal, 4, '.', ''),
                'discount_percent' => '0.00',
                'tax_rate' => '0.00',
            ];
        }

        return $items;
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

    private function buildCustomerAddressSnapshot(Customer $customer): ?string
    {
        $address = trim((string) $customer->address);
        $location = trim(implode(' ', array_filter([
            $customer->postal_code,
            $customer->locality,
            $customer->city,
        ], fn ($value): bool => trim((string) $value) !== '')));

        $snapshot = trim(implode(' | ', array_filter([
            $address,
            $location,
        ], fn ($value): bool => trim((string) $value) !== '')));

        return $snapshot !== '' ? $snapshot : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyLine(): array
    {
        return [
            'article_id' => null,
            'description' => null,
            'unit_id' => null,
            'unit_name_snapshot' => null,
            'quantity' => '1.000',
            'unit_price' => '0.0000',
            'discount_percent' => '0.00',
            'tax_rate' => '0.00',
        ];
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

