<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplierQuoteAward extends Model
{
    use HasFactory;

    public const MODE_CHEAPEST_TOTAL = 'cheapest_total';
    public const MODE_CHEAPEST_ITEM = 'cheapest_item';
    public const MODE_MANUAL_TOTAL = 'manual_total';
    public const MODE_MANUAL_ITEM = 'manual_item';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'supplier_quote_request_id',
        'mode',
        'awarded_supplier_id',
        'award_reason',
        'award_notes',
        'awarded_total',
        'awarded_by',
        'awarded_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'awarded_total' => 'decimal:2',
            'awarded_at' => 'datetime',
        ];
    }

    public function rfq(): BelongsTo
    {
        return $this->belongsTo(SupplierQuoteRequest::class, 'supplier_quote_request_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'awarded_supplier_id');
    }

    public function awardedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'awarded_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SupplierQuoteAwardItem::class)
            ->orderBy('id');
    }

    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * @return list<string>
     */
    public static function modes(): array
    {
        return [
            self::MODE_CHEAPEST_TOTAL,
            self::MODE_CHEAPEST_ITEM,
            self::MODE_MANUAL_TOTAL,
            self::MODE_MANUAL_ITEM,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function modeLabels(): array
    {
        return [
            self::MODE_CHEAPEST_TOTAL => 'Mais barato total',
            self::MODE_CHEAPEST_ITEM => 'Mais barato por item',
            self::MODE_MANUAL_TOTAL => 'Manual global',
            self::MODE_MANUAL_ITEM => 'Manual por item',
        ];
    }

    /**
     * @return list<string>
     */
    public static function reasonOptions(): array
    {
        return [
            'Prazo de entrega melhor',
            'Material equivalente preferido',
            'Melhor qualidade',
            'Fornecedor habitual',
            'Stock imediato',
            'Menor risco logistico',
            'Condicoes de pagamento melhores',
            'Outro',
        ];
    }
}

