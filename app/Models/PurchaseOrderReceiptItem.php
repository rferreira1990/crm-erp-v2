<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrderReceiptItem extends Model
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
        'purchase_order_receipt_id',
        'purchase_order_item_id',
        'line_order',
        'source_line_type',
        'stock_resolution_status',
        'article_id',
        'article_code',
        'description',
        'unit_name',
        'ordered_quantity',
        'previously_received_quantity',
        'received_quantity',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ordered_quantity' => 'decimal:3',
            'previously_received_quantity' => 'decimal:3',
            'received_quantity' => 'decimal:3',
        ];
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderReceipt::class, 'purchase_order_receipt_id');
    }

    public function purchaseOrderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class);
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'reference_line_id')
            ->where('reference_type', StockMovement::REFERENCE_PURCHASE_ORDER_RECEIPT)
            ->orderByDesc('movement_date')
            ->orderByDesc('id');
    }

    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }

    public function requiresStockResolutionDecision(float $epsilon = 0.0005): bool
    {
        return $this->source_line_type === self::LINE_TYPE_TEXT
            && (float) ($this->received_quantity ?? 0) > $epsilon
            && $this->article_id === null
            && $this->stock_resolution_status === self::STOCK_RESOLUTION_PENDING;
    }
}
