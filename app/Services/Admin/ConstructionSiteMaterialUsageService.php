<?php

namespace App\Services\Admin;

use App\Models\Article;
use App\Models\ConstructionSite;
use App\Models\ConstructionSiteMaterialUsage;
use App\Models\StockMovement;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConstructionSiteMaterialUsageService
{
    private const EPSILON = 0.0005;

    /**
     * @param array<string, mixed> $payload
     */
    public function createDraft(ConstructionSite $constructionSite, int $createdBy, array $payload): ConstructionSiteMaterialUsage
    {
        return DB::transaction(function () use ($constructionSite, $createdBy, $payload): ConstructionSiteMaterialUsage {
            $companyId = (int) $constructionSite->company_id;
            $itemsInput = $this->normalizeItemsInput((array) ($payload['items'] ?? []));

            if ($itemsInput === []) {
                throw ValidationException::withMessages([
                    'items' => 'Tem de indicar pelo menos uma linha de consumo.',
                ]);
            }

            $articlesById = $this->lockAndValidateArticles($companyId, $itemsInput, false);
            $itemsPayload = $this->buildItemsPayload($companyId, $itemsInput, $articlesById);

            $usage = ConstructionSiteMaterialUsage::createWithGeneratedNumber($companyId, [
                'construction_site_id' => (int) $constructionSite->id,
                'usage_date' => (string) $payload['usage_date'],
                'notes' => $this->normalizeNullableString($payload['notes'] ?? null),
                'created_by' => $createdBy,
                'status' => ConstructionSiteMaterialUsage::STATUS_DRAFT,
                'posted_at' => null,
            ]);

            $usage->items()->createMany($itemsPayload);

            return $usage->fresh([
                'constructionSite:id,code,name',
                'creator:id,name',
                'items' => fn ($query) => $query->orderBy('id'),
            ]);
        });
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function updateDraft(ConstructionSiteMaterialUsage $usage, array $payload): ConstructionSiteMaterialUsage
    {
        return DB::transaction(function () use ($usage, $payload): ConstructionSiteMaterialUsage {
            /** @var ConstructionSiteMaterialUsage $lockedUsage */
            $lockedUsage = ConstructionSiteMaterialUsage::query()
                ->whereKey((int) $usage->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedUsage->isEditable()) {
                throw ValidationException::withMessages([
                    'usage' => 'Apenas registos de consumo em rascunho podem ser editados.',
                ]);
            }

            $companyId = (int) $lockedUsage->company_id;
            $itemsInput = $this->normalizeItemsInput((array) ($payload['items'] ?? []));
            if ($itemsInput === []) {
                throw ValidationException::withMessages([
                    'items' => 'Tem de indicar pelo menos uma linha de consumo.',
                ]);
            }

            $articlesById = $this->lockAndValidateArticles($companyId, $itemsInput, false);
            $itemsPayload = $this->buildItemsPayload($companyId, $itemsInput, $articlesById);

            $lockedUsage->forceFill([
                'usage_date' => (string) $payload['usage_date'],
                'notes' => $this->normalizeNullableString($payload['notes'] ?? null),
            ])->save();

            $lockedUsage->items()->delete();
            $lockedUsage->items()->createMany($itemsPayload);

            return $lockedUsage->fresh([
                'constructionSite:id,code,name',
                'creator:id,name',
                'items' => fn ($query) => $query->orderBy('id'),
            ]);
        });
    }

    public function post(ConstructionSiteMaterialUsage $usage, int $performedBy): ConstructionSiteMaterialUsage
    {
        return DB::transaction(function () use ($usage, $performedBy): ConstructionSiteMaterialUsage {
            /** @var ConstructionSiteMaterialUsage $lockedUsage */
            $lockedUsage = ConstructionSiteMaterialUsage::query()
                ->whereKey((int) $usage->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedUsage->canPost()) {
                throw ValidationException::withMessages([
                    'usage' => 'Apenas registos em rascunho podem ser confirmados.',
                ]);
            }

            $existingMovements = StockMovement::query()
                ->forCompany((int) $lockedUsage->company_id)
                ->where('reference_type', StockMovement::REFERENCE_CONSTRUCTION_SITE_MATERIAL_USAGE)
                ->where('reference_id', (int) $lockedUsage->id)
                ->exists();

            if ($existingMovements) {
                throw ValidationException::withMessages([
                    'usage' => 'Este registo ja possui movimentos de stock associados.',
                ]);
            }

            $lockedUsage->load([
                'constructionSite:id,code,name',
                'items' => fn ($query) => $query->orderBy('id')->lockForUpdate(),
            ]);

            if ($lockedUsage->items->isEmpty()) {
                throw ValidationException::withMessages([
                    'usage' => 'O registo nao contem linhas de consumo.',
                ]);
            }

            $itemsInput = $lockedUsage->items
                ->map(fn ($item): array => [
                    'article_id' => (int) $item->article_id,
                    'quantity' => (float) $item->quantity,
                    'unit_cost' => $item->unit_cost !== null ? (float) $item->unit_cost : null,
                    'notes' => $item->notes,
                ])
                ->values()
                ->all();

            $articlesById = $this->lockAndValidateArticles((int) $lockedUsage->company_id, $itemsInput, true);

            foreach ($lockedUsage->items as $item) {
                $article = $articlesById->get((int) $item->article_id);
                if (! $article) {
                    throw ValidationException::withMessages([
                        'usage' => 'Existem artigos invalidos neste registo de consumo.',
                    ]);
                }

                $quantity = round((float) $item->quantity, 3);
                if ($quantity <= self::EPSILON) {
                    throw ValidationException::withMessages([
                        'usage' => 'Todas as linhas devem ter quantidade superior a zero.',
                    ]);
                }

                if (! $article->hasSufficientStockFor($quantity)) {
                    throw ValidationException::withMessages([
                        'usage' => 'Stock insuficiente para o artigo '.$article->code.' - '.$article->designation.'.',
                    ]);
                }
            }

            foreach ($lockedUsage->items as $item) {
                $article = $articlesById->get((int) $item->article_id);
                if (! $article) {
                    continue;
                }

                $quantity = round((float) $item->quantity, 3);

                StockMovement::query()->create([
                    'company_id' => (int) $lockedUsage->company_id,
                    'article_id' => (int) $article->id,
                    'type' => StockMovement::TYPE_CONSTRUCTION_SITE_USAGE,
                    'direction' => StockMovement::DIRECTION_OUT,
                    'reason_code' => StockMovement::REASON_INTERNAL_CONSUMPTION,
                    'quantity' => $quantity,
                    'unit_cost' => $item->unit_cost !== null ? round((float) $item->unit_cost, 4) : null,
                    'reference_type' => StockMovement::REFERENCE_CONSTRUCTION_SITE_MATERIAL_USAGE,
                    'reference_id' => (int) $lockedUsage->id,
                    'reference_line_id' => (int) $item->id,
                    'movement_date' => $lockedUsage->usage_date?->format('Y-m-d') ?? now()->toDateString(),
                    'notes' => $this->buildStockMovementNotes($lockedUsage, $item->notes),
                    'performed_by' => $performedBy,
                ]);

                $article->decreaseStock($quantity);
            }

            $lockedUsage->forceFill(
                $lockedUsage->applyStatusTransition(ConstructionSiteMaterialUsage::STATUS_POSTED)
            )->save();

            return $lockedUsage->fresh([
                'constructionSite:id,code,name',
                'creator:id,name',
                'items' => fn ($query) => $query->orderBy('id'),
                'stockMovements' => fn ($query) => $query->orderByDesc('movement_date')->orderByDesc('id'),
            ]);
        });
    }

    public function cancelDraft(ConstructionSiteMaterialUsage $usage): ConstructionSiteMaterialUsage
    {
        return DB::transaction(function () use ($usage): ConstructionSiteMaterialUsage {
            /** @var ConstructionSiteMaterialUsage $lockedUsage */
            $lockedUsage = ConstructionSiteMaterialUsage::query()
                ->whereKey((int) $usage->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedUsage->canCancel()) {
                throw ValidationException::withMessages([
                    'usage' => 'Apenas registos em rascunho podem ser cancelados.',
                ]);
            }

            $lockedUsage->forceFill(
                $lockedUsage->applyStatusTransition(ConstructionSiteMaterialUsage::STATUS_CANCELLED)
            )->save();

            return $lockedUsage;
        });
    }

    /**
     * @param array<int, array{article_id:int, quantity:float, unit_cost:?float, notes:?string}> $itemsInput
     * @return Collection<int, Article>
     */
    private function lockAndValidateArticles(int $companyId, array $itemsInput, bool $requireStockAvailability): Collection
    {
        $articleIds = collect($itemsInput)
            ->pluck('article_id')
            ->filter(fn ($articleId): bool => (int) $articleId > 0)
            ->map(fn ($articleId): int => (int) $articleId)
            ->unique()
            ->values()
            ->all();

        /** @var Collection<int, Article> $articlesById */
        $articlesById = Article::query()
            ->forCompany($companyId)
            ->whereIn('id', $articleIds)
            ->with('unit:id,name')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        foreach ($itemsInput as $index => $item) {
            $article = $articlesById->get((int) $item['article_id']);
            if (! $article) {
                throw ValidationException::withMessages([
                    "items.$index.article_id" => 'O artigo selecionado nao pertence a empresa atual.',
                ]);
            }

            if (! $article->canMoveStock()) {
                throw ValidationException::withMessages([
                    "items.$index.article_id" => 'O artigo selecionado nao movimenta stock.',
                ]);
            }

            if ((float) $item['quantity'] <= self::EPSILON) {
                throw ValidationException::withMessages([
                    "items.$index.quantity" => 'A quantidade deve ser superior a zero.',
                ]);
            }

            if (
                $requireStockAvailability
                && ! $article->hasSufficientStockFor(round((float) $item['quantity'], 3))
            ) {
                throw ValidationException::withMessages([
                    "items.$index.quantity" => 'Stock insuficiente para o artigo '.$article->code.' - '.$article->designation.'.',
                ]);
            }
        }

        return $articlesById;
    }

    /**
     * @param array<int, array{article_id:int, quantity:float, unit_cost:?float, notes:?string}> $itemsInput
     * @param Collection<int, Article> $articlesById
     * @return array<int, array<string, mixed>>
     */
    private function buildItemsPayload(int $companyId, array $itemsInput, Collection $articlesById): array
    {
        $payload = [];

        foreach ($itemsInput as $item) {
            $article = $articlesById->get((int) $item['article_id']);
            if (! $article) {
                continue;
            }

            $payload[] = [
                'company_id' => $companyId,
                'article_id' => (int) $article->id,
                'article_code' => $article->code,
                'description' => (string) $article->designation,
                'unit_name' => $article->unit?->name,
                'quantity' => round((float) $item['quantity'], 3),
                'unit_cost' => $this->resolveUnitCost($companyId, $article, $item['unit_cost']),
                'notes' => $item['notes'],
            ];
        }

        return $payload;
    }

    /**
     * @param array<int|string, mixed> $items
     * @return array<int, array{article_id:int, quantity:float, unit_cost:?float, notes:?string}>
     */
    private function normalizeItemsInput(array $items): array
    {
        $normalized = [];

        foreach ($items as $line) {
            if (! is_array($line)) {
                continue;
            }

            $articleId = isset($line['article_id']) ? (int) $line['article_id'] : 0;
            if ($articleId <= 0) {
                continue;
            }

            $normalized[] = [
                'article_id' => $articleId,
                'quantity' => round((float) ($line['quantity'] ?? 0), 3),
                'unit_cost' => isset($line['unit_cost']) && $line['unit_cost'] !== null
                    ? round((float) $line['unit_cost'], 4)
                    : null,
                'notes' => $this->normalizeNullableString($line['notes'] ?? null),
            ];
        }

        return $normalized;
    }

    private function resolveUnitCost(int $companyId, Article $article, ?float $explicitUnitCost): ?float
    {
        if ($explicitUnitCost !== null) {
            return round(max(0.0, $explicitUnitCost), 4);
        }

        $lastPurchaseUnitCost = StockMovement::query()
            ->forCompany($companyId)
            ->where('article_id', (int) $article->id)
            ->where('type', StockMovement::TYPE_PURCHASE_RECEIPT)
            ->whereNotNull('unit_cost')
            ->orderByDesc('movement_date')
            ->orderByDesc('id')
            ->value('unit_cost');

        if ($lastPurchaseUnitCost !== null) {
            return round((float) $lastPurchaseUnitCost, 4);
        }

        if ($article->cost_price !== null) {
            return round((float) $article->cost_price, 4);
        }

        $lastUnitCost = StockMovement::query()
            ->forCompany($companyId)
            ->where('article_id', (int) $article->id)
            ->whereNotNull('unit_cost')
            ->orderByDesc('movement_date')
            ->orderByDesc('id')
            ->value('unit_cost');

        if ($lastUnitCost === null) {
            return null;
        }

        return round((float) $lastUnitCost, 4);
    }

    private function buildStockMovementNotes(ConstructionSiteMaterialUsage $usage, ?string $itemNotes): string
    {
        $siteCode = trim((string) $usage->constructionSite?->code);
        $notes = 'Consumo de material';
        if ($siteCode !== '') {
            $notes .= ' obra '.$siteCode;
        }
        $notes .= ' ('.$usage->number.')';

        $lineNotes = $this->normalizeNullableString($itemNotes);
        if ($lineNotes !== null) {
            $notes .= ' - '.$lineNotes;
        }

        return $notes;
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
