<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplierQuote extends Model
{
    use HasFactory;

    public const STATUS_RECEIVED = 'received';
    public const STATUS_DRAFT = 'draft';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'supplier_quote_request_supplier_id',
        'status',
        'subtotal',
        'discount_total',
        'shipping_cost',
        'tax_total',
        'grand_total',
        'delivery_days',
        'payment_terms_text',
        'valid_until',
        'notes',
        'received_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'shipping_cost' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'delivery_days' => 'integer',
            'valid_until' => 'date',
            'received_at' => 'datetime',
        ];
    }

    public function rfqSupplier(): BelongsTo
    {
        return $this->belongsTo(SupplierQuoteRequestSupplier::class, 'supplier_quote_request_supplier_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SupplierQuoteItem::class)
            ->orderBy('id');
    }

    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }
}

