<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrderItem extends Model
{
    use HasFactory;

    public const LINE_TYPE_ARTICLE = 'article';
    public const LINE_TYPE_TEXT = 'text';
    public const LINE_TYPE_SECTION = 'section';
    public const LINE_TYPE_NOTE = 'note';

    public const STOCK_RESOLUTION_PENDING = 'pending';
    public const STOCK_RESOLUTION_RESOLVED_ARTICLE = 'resolved_article';
    public const STOCK_RESOLUTION_NON_STOCKABLE = 'non_stockable';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'purchase_order_id',
        'source_award_item_id',
        'source_supplier_quote_item_id',
        'line_type',
        'stock_resolution_status',
        'line_order',
        'article_id',
        'article_code',
        'description',
        'unit_name',
        'quantity',
        'unit_price',
        'discount_percent',
        'vat_percent',
        'line_subtotal',
        'line_discount_total',
        'line_tax_total',
        'line_total',
        'is_alternative',
        'alternative_description',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_price' => 'decimal:4',
            'discount_percent' => 'decimal:2',
            'vat_percent' => 'decimal:2',
            'line_subtotal' => 'decimal:2',
            'line_discount_total' => 'decimal:2',
            'line_tax_total' => 'decimal:2',
            'line_total' => 'decimal:2',
            'is_alternative' => 'boolean',
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function sourceAwardItem(): BelongsTo
    {
        return $this->belongsTo(SupplierQuoteAwardItem::class, 'source_award_item_id');
    }

    public function sourceSupplierQuoteItem(): BelongsTo
    {
        return $this->belongsTo(SupplierQuoteItem::class, 'source_supplier_quote_item_id');
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    public function receiptItems(): HasMany
    {
        return $this->hasMany(PurchaseOrderReceiptItem::class);
    }

    public function totalReceivedQuantity(bool $postedOnly = true): float
    {
        if ($this->relationLoaded('receiptItems')) {
            return round((float) $this->receiptItems
                ->filter(function (PurchaseOrderReceiptItem $item) use ($postedOnly): bool {
                    if (! $postedOnly) {
                        return true;
                    }

                    return $item->relationLoaded('receipt')
                        ? $item->receipt?->status === PurchaseOrderReceipt::STATUS_POSTED
                        : ($item->receipt()->value('status') === PurchaseOrderReceipt::STATUS_POSTED);
                })
                ->sum(fn (PurchaseOrderReceiptItem $item): float => (float) $item->received_quantity), 3);
        }

        $query = PurchaseOrderReceiptItem::query()
            ->where('purchase_order_item_id', $this->id);

        if ($postedOnly) {
            $query->whereIn('purchase_order_receipt_id', function ($subQuery): void {
                $subQuery->select('id')
                    ->from('purchase_order_receipts')
                    ->where('status', PurchaseOrderReceipt::STATUS_POSTED);
            });
        }

        return round((float) $query->sum('received_quantity'), 3);
    }

    public function remainingQuantity(bool $postedOnly = true): float
    {
        $ordered = round((float) ($this->quantity ?? 0), 3);
        $received = $this->totalReceivedQuantity($postedOnly);

        return round(max(0, $ordered - $received), 3);
    }

    public function requiresStockResolution(): bool
    {
        return $this->line_type === self::LINE_TYPE_TEXT
            && $this->article_id === null
            && $this->stock_resolution_status === self::STOCK_RESOLUTION_PENDING;
    }

    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }
}
