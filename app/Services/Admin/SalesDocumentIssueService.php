<?php

namespace App\Services\Admin;

use App\Models\SalesDocument;
use App\Models\SalesDocumentItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalesDocumentIssueService
{
    public function __construct(
        private readonly SalesDocumentStockService $salesDocumentStockService
    ) {
    }

    public function issue(int $companyId, int $documentId, int $performedBy): SalesDocument
    {
        return DB::transaction(function () use ($companyId, $documentId, $performedBy): SalesDocument {
            /** @var SalesDocument $document */
            $document = SalesDocument::query()
                ->forCompany($companyId)
                ->whereKey($documentId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($document->status !== SalesDocument::STATUS_DRAFT) {
                throw ValidationException::withMessages([
                    'document' => 'Apenas documentos em rascunho podem ser emitidos.',
                ]);
            }

            $document->load([
                'items' => fn ($query) => $query
                    ->orderBy('line_order')
                    ->orderBy('id')
                    ->lockForUpdate(),
            ]);

            if ($document->items->isEmpty()) {
                throw ValidationException::withMessages([
                    'document' => 'Nao e possivel emitir um documento sem linhas.',
                ]);
            }

            foreach ($document->items as $item) {
                $amounts = SalesDocumentItem::calculateAmounts(
                    quantity: round((float) $item->quantity, 3),
                    unitPrice: round((float) $item->unit_price, 4),
                    discountPercent: round((float) ($item->discount_percent ?? 0), 2),
                    taxRate: round((float) ($item->tax_rate ?? 0), 2)
                );

                $item->forceFill([
                    'line_subtotal' => $amounts['line_subtotal'],
                    'line_discount_total' => $amounts['line_discount_total'],
                    'line_tax_total' => $amounts['line_tax_total'],
                    'line_total' => $amounts['line_total'],
                ])->save();
            }

            $document->recalculateTotalsFromItems();

            $this->salesDocumentStockService->moveStockForIssuedDocument($document, $performedBy);

            $document->forceFill([
                ...$document->applyStatusTransition(SalesDocument::STATUS_ISSUED),
                'updated_by' => $performedBy,
            ])->save();

            return $document->fresh([
                'customer:id,name',
                'quote:id,number',
                'constructionSite:id,code,name',
                'items' => fn ($query) => $query->orderBy('line_order')->orderBy('id'),
                'stockMovements' => fn ($query) => $query->orderByDesc('movement_date')->orderByDesc('id'),
            ]);
        });
    }

    public function cancelDraft(int $companyId, int $documentId, int $performedBy): SalesDocument
    {
        return DB::transaction(function () use ($companyId, $documentId, $performedBy): SalesDocument {
            /** @var SalesDocument $document */
            $document = SalesDocument::query()
                ->forCompany($companyId)
                ->whereKey($documentId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($document->status !== SalesDocument::STATUS_DRAFT) {
                throw ValidationException::withMessages([
                    'document' => 'Apenas documentos em rascunho podem ser cancelados.',
                ]);
            }

            if ($document->stockMovements()->exists()) {
                throw ValidationException::withMessages([
                    'document' => 'Nao e possivel cancelar um documento com movimentos de stock.',
                ]);
            }

            $document->forceFill([
                ...$document->applyStatusTransition(SalesDocument::STATUS_CANCELLED),
                'updated_by' => $performedBy,
            ])->save();

            return $document;
        });
    }
}

