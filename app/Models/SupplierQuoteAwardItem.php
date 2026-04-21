<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplierQuoteAwardItem extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'supplier_quote_award_id',
        'supplier_quote_request_item_id',
        'supplier_id',
        'supplier_quote_item_id',
        'quantity',
        'unit_price',
        'line_total',
        'is_cheapest_option',
        'notes',
        'supplier_name',
        'article_code',
        'description',
        'unit_name',
        'line_type',
        'is_alternative',
        'alternative_description',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_price' => 'decimal:4',
            'line_total' => 'decimal:2',
            'is_cheapest_option' => 'boolean',
            'is_alternative' => 'boolean',
        ];
    }

    public function award(): BelongsTo
    {
        return $this->belongsTo(SupplierQuoteAward::class, 'supplier_quote_award_id');
    }

    public function rfqItem(): BelongsTo
    {
        return $this->belongsTo(SupplierQuoteRequestItem::class, 'supplier_quote_request_item_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function supplierQuoteItem(): BelongsTo
    {
        return $this->belongsTo(SupplierQuoteItem::class);
    }

    public function purchaseOrderItems(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class, 'source_award_item_id');
    }

    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }
}
