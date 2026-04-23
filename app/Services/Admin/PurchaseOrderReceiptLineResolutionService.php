<?php

namespace App\Services\Admin;

use App\Http\Requests\Admin\ResolvePurchaseOrderReceiptLineRequest;
use App\Models\Article;
use App\Models\Category;
use App\Models\ProductFamily;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseOrderReceipt;
use App\Models\PurchaseOrderReceiptItem;
use App\Models\Unit;
use App\Models\VatRate;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseOrderReceiptLineResolutionService
{
    /**
     * @param array<string, mixed> $payload
     */
    public function resolve(
        PurchaseOrderReceipt $receipt,
        PurchaseOrderReceiptItem $receiptItem,
        array $payload,
        int $userId
    ): PurchaseOrderReceiptItem {
        return DB::transaction(function () use ($receipt, $receiptItem, $payload, $userId): PurchaseOrderReceiptItem {
            /** @var PurchaseOrderReceipt $lockedReceipt */
            $lockedReceipt = PurchaseOrderReceipt::query()
                ->whereKey((int) $receipt->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedReceipt->isEditable()) {
                throw ValidationException::withMessages([
                    'receipt' => 'A resolucao de linhas so e permitida em rececoes em rascunho.',
                ]);
            }

            /** @var PurchaseOrderReceiptItem $lockedReceiptItem */
            $lockedReceiptItem = PurchaseOrderReceiptItem::query()
                ->whereKey((int) $receiptItem->id)
                ->where('purchase_order_receipt_id', (int) $lockedReceipt->id)
                ->lockForUpdate()
                ->firstOrFail();

            /** @var PurchaseOrderItem $lockedPoItem */
            $lockedPoItem = PurchaseOrderItem::query()
                ->whereKey((int) $lockedReceiptItem->purchase_order_item_id)
                ->where('purchase_order_id', (int) $lockedReceipt->purchase_order_id)
                ->lockForUpdate()
                ->firstOrFail();

            $action = (string) ($payload['action'] ?? '');
            if ($action === ResolvePurchaseOrderReceiptLineRequest::ACTION_MARK_NON_STOCKABLE) {
                $this->markLineAsNonStockable($lockedPoItem, $lockedReceiptItem);
            } elseif ($action === ResolvePurchaseOrderReceiptLineRequest::ACTION_ASSIGN_EXISTING) {
                $article = $this->findCompanyArticleOrFail((int) $lockedReceipt->company_id, (int) ($payload['article_id'] ?? 0));
                $this->assignArticleToLine($lockedPoItem, $lockedReceiptItem, $article);
            } elseif ($action === ResolvePurchaseOrderReceiptLineRequest::ACTION_CREATE_NEW) {
                $article = $this->createArticleFromPayload((int) $lockedReceipt->company_id, $payload, $userId);
                $this->assignArticleToLine($lockedPoItem, $lockedReceiptItem, $article);
            } else {
                throw ValidationException::withMessages([
                    'action' => 'Acao de resolucao invalida.',
                ]);
            }

            return $lockedReceiptItem->fresh(['article']);
        });
    }

    private function markLineAsNonStockable(PurchaseOrderItem $poItem, PurchaseOrderReceiptItem $receiptItem): void
    {
        $poItem->forceFill([
            'stock_resolution_status' => PurchaseOrderItem::STOCK_RESOLUTION_NON_STOCKABLE,
        ])->save();

        $receiptItem->forceFill([
            'stock_resolution_status' => PurchaseOrderReceiptItem::STOCK_RESOLUTION_NON_STOCKABLE,
            'article_id' => null,
        ])->save();
    }

    private function assignArticleToLine(PurchaseOrderItem $poItem, PurchaseOrderReceiptItem $receiptItem, Article $article): void
    {
        $poItem->forceFill([
            'article_id' => (int) $article->id,
            'article_code' => $article->code,
            'stock_resolution_status' => PurchaseOrderItem::STOCK_RESOLUTION_RESOLVED_ARTICLE,
        ])->save();

        $receiptItem->forceFill([
            'article_id' => (int) $article->id,
            'article_code' => $article->code,
            'unit_name' => $receiptItem->unit_name ?: ($article->unit?->code ?: null),
            'stock_resolution_status' => PurchaseOrderReceiptItem::STOCK_RESOLUTION_RESOLVED_ARTICLE,
        ])->save();
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function createArticleFromPayload(int $companyId, array $payload, int $userId): Article
    {
        $designation = trim((string) ($payload['designation'] ?? ''));
        if ($designation === '') {
            throw ValidationException::withMessages([
                'designation' => 'A designacao do artigo e obrigatoria.',
            ]);
        }

        $productFamilyId = (int) ($payload['product_family_id'] ?? 0);
        $family = ProductFamily::query()
            ->visibleToCompany($companyId)
            ->whereKey($productFamilyId)
            ->first();

        if (! $family) {
            throw ValidationException::withMessages([
                'product_family_id' => 'A familia selecionada e invalida.',
            ]);
        }

        $categoryId = (int) ($payload['category_id'] ?? Article::defaultCategoryIdForCompany($companyId));
        $category = Category::query()
            ->visibleToCompany($companyId)
            ->whereKey($categoryId)
            ->first();
        if (! $category) {
            throw ValidationException::withMessages([
                'category_id' => 'A categoria selecionada e invalida.',
            ]);
        }

        $unitId = (int) ($payload['unit_id'] ?? Article::defaultUnitIdForCompany($companyId));
        $unit = Unit::query()
            ->visibleToCompany($companyId)
            ->whereKey($unitId)
            ->first();
        if (! $unit) {
            throw ValidationException::withMessages([
                'unit_id' => 'A unidade selecionada e invalida.',
            ]);
        }

        $vatRateId = (int) ($payload['vat_rate_id'] ?? 0);
        $vatRate = VatRate::query()
            ->with([
                'companyOverrides' => fn ($query) => $query->where('company_id', $companyId),
            ])
            ->visibleToCompany($companyId)
            ->whereKey($vatRateId)
            ->first();
        if (! $vatRate || ! $vatRate->isEnabledForCompany($companyId)) {
            throw ValidationException::withMessages([
                'vat_rate_id' => 'A taxa de IVA selecionada e invalida.',
            ]);
        }

        return Article::createWithGeneratedCode($companyId, [
            'designation' => $designation,
            'product_family_id' => (int) $family->id,
            'category_id' => (int) $category->id,
            'unit_id' => (int) $unit->id,
            'vat_rate_id' => (int) $vatRate->id,
            'moves_stock' => (bool) ($payload['moves_stock'] ?? true),
            'stock_alert_enabled' => false,
            'is_active' => (bool) ($payload['is_active'] ?? true),
            'internal_notes' => 'Criado a partir da resolucao de linha livre da rececao por utilizador #'.$userId.'.',
        ]);
    }

    private function findCompanyArticleOrFail(int $companyId, int $articleId): Article
    {
        $article = Article::query()
            ->forCompany($companyId)
            ->whereKey($articleId)
            ->with('unit:id,code')
            ->first();

        if (! $article) {
            throw ValidationException::withMessages([
                'article_id' => 'O artigo selecionado e invalido para a empresa atual.',
            ]);
        }

        return $article;
    }
}
