<?php

namespace App\Models;

use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    use HasFactory;

    public const TYPE_PURCHASE_RECEIPT = 'purchase_receipt';
    public const TYPE_MANUAL_ADJUSTMENT = 'manual_adjustment';
    public const TYPE_SALE = 'sale';
    public const TYPE_RETURN = 'return';
    public const TYPE_STOCK_COUNT = 'stock_count';

    public const DIRECTION_IN = 'in';
    public const DIRECTION_OUT = 'out';

    public const REFERENCE_PURCHASE_ORDER_RECEIPT = 'purchase_order_receipt';

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new DomainException('Stock movement history is immutable.');
        });

        static::deleting(function (): void {
            throw new DomainException('Stock movement history is immutable.');
        });
    }

    /**
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'article_id',
        'type',
        'direction',
        'quantity',
        'unit_cost',
        'reference_type',
        'reference_id',
        'reference_line_id',
        'movement_date',
        'notes',
        'performed_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_cost' => 'decimal:4',
            'movement_date' => 'date',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeFromPurchaseReceipt(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_PURCHASE_RECEIPT)
            ->where('reference_type', self::REFERENCE_PURCHASE_ORDER_RECEIPT);
    }
}
