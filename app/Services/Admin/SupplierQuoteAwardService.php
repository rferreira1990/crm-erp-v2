<?php

namespace App\Services\Admin;

use App\Models\SupplierQuoteAward;
use App\Models\SupplierQuoteRequest;
use App\Models\SupplierQuoteRequestItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SupplierQuoteAwardService
{
    public function __construct(
        private readonly SupplierQuoteComparisonService $comparisonService
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function award(SupplierQuoteRequest $rfq, int $awardedBy, array $payload): SupplierQuoteAward
    {
        $this->assertAwardableStatus($rfq);
        $comparison = $this->comparisonService->build($rfq);

        $mode = (string) ($payload['mode'] ?? '');

        return DB::transaction(function () use ($rfq, $awardedBy, $payload, $comparison, $mode): SupplierQuoteAward {
            $awardResult = match ($mode) {
                SupplierQuoteAward::MODE_CHEAPEST_TOTAL => $this->buildCheapestTotalAward($comparison),
                SupplierQuoteAward::MODE_CHEAPEST_ITEM => $this->buildCheapestItemAward($comparison),
                SupplierQuoteAward::MODE_MANUAL_TOTAL => $this->buildManualTotalAward($comparison, (int) ($payload['awarded_supplier_id'] ?? 0)),
                SupplierQuoteAward::MODE_MANUAL_ITEM => $this->buildManualItemAward($comparison, (array) ($payload['item_supplier_ids'] ?? [])),
                default => throw ValidationException::withMessages([
                    'mode' => 'Modo de adjudicacao invalido.',
                ]),
            };

            $requiresReason = (bool) ($awardResult['requires_reason'] ?? false);
            $reason = isset($payload['award_reason']) ? trim((string) $payload['award_reason']) : null;
            $notes = isset($payload['award_notes']) ? trim((string) $payload['award_notes']) : null;
            $reason = $reason !== '' ? $reason : null;
            $notes = $notes !== '' ? $notes : null;

            if ($requiresReason && $reason === null) {
                throw ValidationException::withMessages([
                    'award_reason' => 'Ao escolher uma opcao mais cara, o motivo e obrigatorio.',
                ]);
            }

            /** @var SupplierQuoteAward $award */
            $award = SupplierQuoteAward::query()->create([
                'company_id' => (int) $rfq->company_id,
                'supplier_quote_request_id' => (int) $rfq->id,
                'mode' => $mode,
                'awarded_supplier_id' => $awardResult['awarded_supplier_id'],
                'award_reason' => $reason,
                'award_notes' => $notes,
                'awarded_total' => $awardResult['awarded_total'],
                'awarded_by' => $awardedBy,
                'awarded_at' => now(),
            ]);

            $award->items()->createMany($awardResult['items']);

            $rfq->forceFill([
                'status' => SupplierQuoteRequest::STATUS_AWARDED,
                'awarded_total' => $awardResult['awarded_total'],
                'awarded_at' => now(),
            ])->save();

            return $award;
        });
    }

    /**
     * @param array<string, mixed> $comparison
     * @return array<string, mixed>
     */
    private function buildCheapestTotalAward(array $comparison): array
    {
        $cheapestInviteId = $comparison['cheapest_total_invite_id'];
        if (! $cheapestInviteId) {
            throw ValidationException::withMessages([
                'mode' => 'Nao existe proposta completa valida para adjudicacao pelo mais barato total.',
            ]);
        }

        $summary = $this->supplierSummaryByInviteId($comparison, (int) $cheapestInviteId);
        $quote = $summary['quote'];
        if (! $quote) {
            throw ValidationException::withMessages([
                'mode' => 'A proposta mais barata total nao esta disponivel.',
            ]);
        }

        return [
            'awarded_supplier_id' => (int) $summary['invite']->supplier_id,
            'awarded_total' => round((float) $quote->grand_total, 2),
            'requires_reason' => false,
            'items' => $this->buildAwardItemsFromSingleSupplierSummary($summary, $comparison['rfq']->items),
        ];
    }

    /**
     * @param array<string, mixed> $comparison
     * @return array<string, mixed>
     */
    private function buildCheapestItemAward(array $comparison): array
    {
        $unresolvedItemIds = (array) ($comparison['unresolved_item_ids'] ?? []);
        if ($unresolvedItemIds !== []) {
            throw ValidationException::withMessages([
                'mode' => 'Nao e possivel adjudicar por item: existem linhas sem resposta exata disponivel.',
            ]);
        }

        $itemsPayload = [];
        $awardedTotal = 0.0;

        /** @var SupplierQuoteRequestItem $rfqItem */
        foreach ($comparison['rfq']->items as $rfqItem) {
            if (! in_array((int) $rfqItem->id, (array) $comparison['eligible_item_ids'], true)) {
                continue;
            }

            $selection = $comparison['cheapest_item_selections'][(int) $rfqItem->id] ?? null;
            if (! $selection) {
                throw ValidationException::withMessages([
                    'mode' => 'Nao foi possivel determinar o fornecedor mais barato para todas as linhas.',
                ]);
            }

            $summary = $this->supplierSummaryByInviteId($comparison, (int) $selection['invite_id']);
            $quoteItem = $summary['items_by_rfq_item']->get((int) $rfqItem->id);
            if (! $quoteItem) {
                throw ValidationException::withMessages([
                    'mode' => 'Dados incoerentes no calculo do mais barato por item.',
                ]);
            }

            $lineTotal = (float) ($quoteItem->line_total ?? 0);
            $awardedTotal += $lineTotal;

            $itemsPayload[] = $this->buildAwardItemPayload(
                rfqItem: $rfqItem,
                supplierSummary: $summary,
                quoteItem: $quoteItem,
                isCheapestOption: true
            );
        }

        return [
            'awarded_supplier_id' => null,
            'awarded_total' => round($awardedTotal, 2),
            'requires_reason' => false,
            'items' => $itemsPayload,
        ];
    }

    /**
     * @param array<string, mixed> $comparison
     * @return array<string, mixed>
     */
    private function buildManualTotalAward(array $comparison, int $supplierId): array
    {
        if ($supplierId <= 0) {
            throw ValidationException::withMessages([
                'awarded_supplier_id' => 'Selecione um fornecedor para adjudicacao manual global.',
            ]);
        }

        $summary = $this->supplierSummaryBySupplierId($comparison, $supplierId);
        if (! $summary || ! $summary['quote']) {
            throw ValidationException::withMessages([
                'awarded_supplier_id' => 'O fornecedor selecionado nao tem proposta valida para adjudicacao global.',
            ]);
        }

        $cheapestInviteId = (int) ($comparison['cheapest_total_invite_id'] ?? 0);
        $isCheapest = $cheapestInviteId > 0 && $cheapestInviteId === (int) $summary['invite']->id;
        $requiresReason = $cheapestInviteId > 0 && ! $isCheapest;

        return [
            'awarded_supplier_id' => $supplierId,
            'awarded_total' => round((float) $summary['quote']->grand_total, 2),
            'requires_reason' => $requiresReason,
            'items' => $this->buildAwardItemsFromSingleSupplierSummary($summary, $comparison['rfq']->items, $isCheapest),
        ];
    }

    /**
     * @param array<string, mixed> $comparison
     * @param array<int|string, int|string> $itemSupplierIds
     * @return array<string, mixed>
     */
    private function buildManualItemAward(array $comparison, array $itemSupplierIds): array
    {
        $normalizedSelection = collect($itemSupplierIds)
            ->mapWithKeys(fn ($supplierId, $rfqItemId): array => [(int) $rfqItemId => (int) $supplierId])
            ->all();

        $requiresReason = false;
        $awardedTotal = 0.0;
        $itemsPayload = [];

        /** @var SupplierQuoteRequestItem $rfqItem */
        foreach ($comparison['rfq']->items as $rfqItem) {
            $rfqItemId = (int) $rfqItem->id;
            if (! in_array($rfqItemId, (array) $comparison['eligible_item_ids'], true)) {
                continue;
            }

            $selectedSupplierId = (int) ($normalizedSelection[$rfqItemId] ?? 0);
            if ($selectedSupplierId <= 0) {
                throw ValidationException::withMessages([
                    "item_supplier_ids.$rfqItemId" => 'Selecione um fornecedor para todas as linhas comparaveis.',
                ]);
            }

            $summary = $this->supplierSummaryBySupplierId($comparison, $selectedSupplierId);
            if (! $summary) {
                throw ValidationException::withMessages([
                    "item_supplier_ids.$rfqItemId" => 'Fornecedor invalido para adjudicacao manual por item.',
                ]);
            }

            $quoteItem = $summary['items_by_rfq_item']->get($rfqItemId);
            if (! $quoteItem || ! $quoteItem->is_available || $quoteItem->line_total === null) {
                throw ValidationException::withMessages([
                    "item_supplier_ids.$rfqItemId" => 'A linha selecionada nao esta disponivel nesse fornecedor.',
                ]);
            }

            $cheapestSelection = $comparison['cheapest_item_selections'][$rfqItemId] ?? null;
            $isCheapest = $cheapestSelection !== null
                && (int) $cheapestSelection['supplier_id'] === $selectedSupplierId;

            if (! $isCheapest && $cheapestSelection !== null) {
                $requiresReason = true;
            }

            $lineTotal = (float) $quoteItem->line_total;
            $awardedTotal += $lineTotal;

            $itemsPayload[] = $this->buildAwardItemPayload(
                rfqItem: $rfqItem,
                supplierSummary: $summary,
                quoteItem: $quoteItem,
                isCheapestOption: $isCheapest
            );
        }

        return [
            'awarded_supplier_id' => null,
            'awarded_total' => round($awardedTotal, 2),
            'requires_reason' => $requiresReason,
            'items' => $itemsPayload,
        ];
    }

    private function assertAwardableStatus(SupplierQuoteRequest $rfq): void
    {
        if ($rfq->status === SupplierQuoteRequest::STATUS_AWARDED) {
            throw ValidationException::withMessages([
                'mode' => 'Este pedido ja foi adjudicado.',
            ]);
        }

        if (! in_array($rfq->status, [
            SupplierQuoteRequest::STATUS_PARTIALLY_RECEIVED,
            SupplierQuoteRequest::STATUS_RECEIVED,
            SupplierQuoteRequest::STATUS_COMPARED,
        ], true)) {
            throw ValidationException::withMessages([
                'mode' => 'O estado atual do pedido nao permite adjudicacao.',
            ]);
        }
    }

    /**
     * @param array<string, mixed> $comparison
     * @return array<string, mixed>
     */
    private function supplierSummaryByInviteId(array $comparison, int $inviteId): array
    {
        /** @var Collection<int, array<string, mixed>> $suppliers */
        $suppliers = $comparison['suppliers'];
        $summary = $suppliers->first(fn (array $item): bool => (int) $item['invite']->id === $inviteId);

        if (! $summary) {
            throw ValidationException::withMessages([
                'mode' => 'Fornecedor de adjudicacao invalido.',
            ]);
        }

        return $summary;
    }

    /**
     * @param array<string, mixed> $comparison
     * @return array<string, mixed>|null
     */
    private function supplierSummaryBySupplierId(array $comparison, int $supplierId): ?array
    {
        /** @var Collection<int, array<string, mixed>> $suppliers */
        $suppliers = $comparison['suppliers'];

        return $suppliers->first(fn (array $item): bool => (int) $item['invite']->supplier_id === $supplierId);
    }

    /**
     * @param array<string, mixed> $supplierSummary
     * @param Collection<int, SupplierQuoteRequestItem> $rfqItems
     * @return array<int, array<string, mixed>>
     */
    private function buildAwardItemsFromSingleSupplierSummary(
        array $supplierSummary,
        Collection $rfqItems,
        bool $isCheapestDefault = true
    ): array {
        $items = [];
        /** @var Collection<int, \App\Models\SupplierQuoteItem> $itemsByRfqItem */
        $itemsByRfqItem = $supplierSummary['items_by_rfq_item'];

        /** @var SupplierQuoteRequestItem $rfqItem */
        foreach ($rfqItems as $rfqItem) {
            if (! in_array($rfqItem->line_type, [SupplierQuoteRequestItem::TYPE_ARTICLE, SupplierQuoteRequestItem::TYPE_TEXT], true)) {
                continue;
            }

            $quoteItem = $itemsByRfqItem->get((int) $rfqItem->id);
            $items[] = $this->buildAwardItemPayload(
                rfqItem: $rfqItem,
                supplierSummary: $supplierSummary,
                quoteItem: $quoteItem,
                isCheapestOption: $isCheapestDefault
            );
        }

        return $items;
    }

    /**
     * @param array<string, mixed> $supplierSummary
     * @return array<string, mixed>
     */
    private function buildAwardItemPayload(
        SupplierQuoteRequestItem $rfqItem,
        array $supplierSummary,
        mixed $quoteItem,
        bool $isCheapestOption
    ): array {
        $invite = $supplierSummary['invite'];

        return [
            'company_id' => (int) $rfqItem->company_id,
            'supplier_quote_request_item_id' => (int) $rfqItem->id,
            'supplier_id' => (int) $invite->supplier_id,
            'supplier_quote_item_id' => $quoteItem?->id,
            'quantity' => $quoteItem?->quantity ?? $rfqItem->quantity,
            'unit_price' => $quoteItem?->unit_price,
            'line_total' => $quoteItem?->line_total,
            'is_cheapest_option' => $isCheapestOption,
            'notes' => $quoteItem?->notes,
            'supplier_name' => $invite->supplier_name,
            'article_code' => $rfqItem->article_code,
            'description' => $rfqItem->description,
            'unit_name' => $rfqItem->unit_name,
            'line_type' => $rfqItem->line_type,
            'is_alternative' => (bool) ($quoteItem?->is_alternative ?? false),
            'alternative_description' => $quoteItem?->alternative_description,
        ];
    }
}
