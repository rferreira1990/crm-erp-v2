<?php

namespace App\Services\Admin;

use App\Models\SupplierQuoteItem;
use App\Models\SupplierQuoteRequest;
use App\Models\SupplierQuoteRequestItem;
use App\Models\SupplierQuoteRequestSupplier;
use Illuminate\Support\Collection;

class SupplierQuoteComparisonService
{
    /**
     * @return array{
     *   rfq: SupplierQuoteRequest,
     *   eligible_item_ids: array<int, int>,
     *   suppliers: Collection<int, array<string, mixed>>,
     *   item_matrix: Collection<int, array<string, mixed>>,
     *   cheapest_total_invite_id: ?int,
     *   cheapest_total_amount: ?float,
     *   cheapest_item_selections: array<int, array<string, mixed>>,
     *   unresolved_item_ids: array<int, int>,
     *   has_responses: bool
     * }
     */
    public function build(SupplierQuoteRequest $rfq): array
    {
        $rfq->loadMissing([
            'items' => fn ($query) => $query->orderBy('line_order')->orderBy('id'),
            'invitedSuppliers' => fn ($query) => $query
                ->with([
                    'supplier:id,name,email',
                    'supplierQuote' => fn ($supplierQuoteQuery) => $supplierQuoteQuery->with([
                        'items:id,supplier_quote_id,supplier_quote_request_item_id,quantity,unit_price,line_total,is_available,is_alternative,alternative_description,notes',
                    ]),
                ])
                ->orderBy('id'),
        ]);

        $eligibleItems = $rfq->items->filter(fn (SupplierQuoteRequestItem $item): bool => in_array(
            $item->line_type,
            [SupplierQuoteRequestItem::TYPE_ARTICLE, SupplierQuoteRequestItem::TYPE_TEXT],
            true
        ))->values();
        $eligibleItemIds = $eligibleItems->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $eligibleLookup = array_flip($eligibleItemIds);

        $suppliers = collect();
        $hasResponses = false;
        foreach ($rfq->invitedSuppliers as $invite) {
            $quote = $invite->supplierQuote;
            $quoteItems = $quote?->items ?? collect();
            $itemsByRfqItem = $quoteItems
                ->filter(fn (SupplierQuoteItem $item): bool => isset($eligibleLookup[(int) $item->supplier_quote_request_item_id]))
                ->keyBy('supplier_quote_request_item_id');

            $respondedCount = $itemsByRfqItem->count();
            $unavailableCount = $itemsByRfqItem->where('is_available', false)->count();
            $alternativeCount = $itemsByRfqItem->where('is_alternative', true)->count();

            $isComplete = $quote !== null && $eligibleItems->every(function (SupplierQuoteRequestItem $item) use ($itemsByRfqItem): bool {
                /** @var SupplierQuoteItem|null $quoteItem */
                $quoteItem = $itemsByRfqItem->get($item->id);

                return $quoteItem !== null && $quoteItem->is_available && $quoteItem->line_total !== null;
            });

            $hasResponses = $hasResponses || $quote !== null;

            $suppliers->push([
                'invite' => $invite,
                'quote' => $quote,
                'items_by_rfq_item' => $itemsByRfqItem,
                'responded_count' => $respondedCount,
                'unavailable_count' => $unavailableCount,
                'alternative_count' => $alternativeCount,
                'eligible_items_count' => count($eligibleItemIds),
                'is_complete' => $isComplete,
                'has_comparability_warning' => $alternativeCount > 0,
            ]);
        }

        $cheapestTotalCandidate = $suppliers
            ->filter(fn (array $supplier): bool => $supplier['is_complete'] && $supplier['quote'] !== null)
            ->sortBy(function (array $supplier): array {
                $quote = $supplier['quote'];

                return [
                    (float) $quote->grand_total,
                    (int) $supplier['invite']->id,
                ];
            })
            ->first();

        $cheapestTotalInviteId = $cheapestTotalCandidate ? (int) $cheapestTotalCandidate['invite']->id : null;
        $cheapestTotalAmount = $cheapestTotalCandidate ? (float) $cheapestTotalCandidate['quote']->grand_total : null;

        $itemMatrix = collect();
        $cheapestItemSelections = [];
        $unresolvedItemIds = [];

        foreach ($rfq->items as $rfqItem) {
            $isComparableLine = in_array($rfqItem->line_type, [SupplierQuoteRequestItem::TYPE_ARTICLE, SupplierQuoteRequestItem::TYPE_TEXT], true);
            $cellsByInviteId = [];
            $exactCandidates = [];
            $alternativeCandidates = [];

            foreach ($suppliers as $supplierSummary) {
                /** @var SupplierQuoteRequestSupplier $invite */
                $invite = $supplierSummary['invite'];
                /** @var Collection<int, SupplierQuoteItem> $itemsByRfqItem */
                $itemsByRfqItem = $supplierSummary['items_by_rfq_item'];
                /** @var SupplierQuoteItem|null $quoteItem */
                $quoteItem = $isComparableLine ? $itemsByRfqItem->get($rfqItem->id) : null;

                $status = 'not_applicable';
                if ($isComparableLine) {
                    if (! $quoteItem) {
                        $status = 'no_response';
                    } elseif (! $quoteItem->is_available) {
                        $status = 'unavailable';
                    } elseif ($quoteItem->is_alternative) {
                        $status = 'available_alternative';
                    } else {
                        $status = 'available_exact';
                    }

                    if ($quoteItem && $quoteItem->is_available && $quoteItem->line_total !== null) {
                        $candidate = [
                            'invite_id' => (int) $invite->id,
                            'supplier_id' => (int) $invite->supplier_id,
                            'quote_item_id' => (int) $quoteItem->id,
                            'line_total' => (float) $quoteItem->line_total,
                            'quote_item' => $quoteItem,
                        ];

                        if ($quoteItem->is_alternative) {
                            $alternativeCandidates[] = $candidate;
                        } else {
                            $exactCandidates[] = $candidate;
                        }
                    }
                }

                $cellsByInviteId[(int) $invite->id] = [
                    'status' => $status,
                    'quote_item' => $quoteItem,
                    'is_best_exact' => false,
                ];
            }

            $bestExact = null;
            if ($exactCandidates !== []) {
                usort($exactCandidates, static function (array $a, array $b): int {
                    $lineTotalCompare = $a['line_total'] <=> $b['line_total'];
                    if ($lineTotalCompare !== 0) {
                        return $lineTotalCompare;
                    }

                    return $a['invite_id'] <=> $b['invite_id'];
                });

                $bestExact = $exactCandidates[0];
                $cellsByInviteId[(int) $bestExact['invite_id']]['is_best_exact'] = true;
                $cheapestItemSelections[(int) $rfqItem->id] = $bestExact;
            } elseif ($isComparableLine) {
                $unresolvedItemIds[] = (int) $rfqItem->id;
            }

            $itemMatrix->push([
                'rfq_item' => $rfqItem,
                'is_comparable' => $isComparableLine,
                'cells_by_invite_id' => $cellsByInviteId,
                'best_exact_invite_id' => $bestExact['invite_id'] ?? null,
                'best_exact_line_total' => $bestExact['line_total'] ?? null,
                'has_only_alternatives' => $isComparableLine && $bestExact === null && $alternativeCandidates !== [],
            ]);
        }

        return [
            'rfq' => $rfq,
            'eligible_item_ids' => $eligibleItemIds,
            'suppliers' => $suppliers,
            'item_matrix' => $itemMatrix,
            'cheapest_total_invite_id' => $cheapestTotalInviteId,
            'cheapest_total_amount' => $cheapestTotalAmount,
            'cheapest_item_selections' => $cheapestItemSelections,
            'unresolved_item_ids' => array_values(array_unique($unresolvedItemIds)),
            'has_responses' => $hasResponses,
        ];
    }
}

