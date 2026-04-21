<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierQuoteItem extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'supplier_quote_id',
        'supplier_quote_request_item_id',
        'quantity',
        'unit_price',
        'discount_percent',
        'vat_percent',
        'line_total',
        'alternative_description',
        'brand',
        'is_available',
        'is_alternative',
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
            'line_total' => 'decimal:2',
            'is_available' => 'boolean',
            'is_alternative' => 'boolean',
        ];
    }

    public function supplierQuote(): BelongsTo
    {
        return $this->belongsTo(SupplierQuote::class);
    }

    public function rfqItem(): BelongsTo
    {
        return $this->belongsTo(SupplierQuoteRequestItem::class, 'supplier_quote_request_item_id');
    }

    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }
}

