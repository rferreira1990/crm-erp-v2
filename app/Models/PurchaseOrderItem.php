<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderItem extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'purchase_order_id',
        'source_award_item_id',
        'source_supplier_quote_item_id',
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

    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }
}

