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
    public const TYPE_MANUAL_ADJUSTMENT_IN = 'manual_adjustment_in';
    public const TYPE_MANUAL_ADJUSTMENT_OUT = 'manual_adjustment_out';
    public const TYPE_MANUAL_ISSUE = 'manual_issue';
    public const TYPE_CONSTRUCTION_SITE_USAGE = 'construction_site_usage';
    public const TYPE_MANUAL_ADJUSTMENT = 'manual_adjustment';
    public const TYPE_SALE = 'sale';
    public const TYPE_RETURN = 'return';
    public const TYPE_STOCK_COUNT = 'stock_count';

    public const DIRECTION_IN = 'in';
    public const DIRECTION_OUT = 'out';

    public const REFERENCE_PURCHASE_ORDER_RECEIPT = 'purchase_order_receipt';
    public const REFERENCE_CONSTRUCTION_SITE_MATERIAL_USAGE = 'construction_site_material_usage';
    public const REFERENCE_SALES_DOCUMENT = 'sales_document';
    public const REFERENCE_MANUAL = 'manual';

    public const REASON_STOCK_INITIAL = 'stock_initial';
    public const REASON_CORRECTION_POSITIVE = 'correction_positive';
    public const REASON_INTERNAL_RETURN = 'internal_return';
    public const REASON_BREAKAGE = 'breakage';
    public const REASON_LOSS = 'loss';
    public const REASON_INTERNAL_CONSUMPTION = 'internal_consumption';
    public const REASON_CORRECTION_NEGATIVE = 'correction_negative';
    public const REASON_OTHER = 'other';

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
        'reason_code',
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

    /**
     * @return list<string>
     */
    public static function manualTypes(): array
    {
        return [
            self::TYPE_MANUAL_ADJUSTMENT_IN,
            self::TYPE_MANUAL_ADJUSTMENT_OUT,
            self::TYPE_MANUAL_ISSUE,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function typeLabels(): array
    {
        return [
            self::TYPE_PURCHASE_RECEIPT => 'Entrada compra',
            self::TYPE_MANUAL_ADJUSTMENT_IN => 'Ajuste manual +',
            self::TYPE_MANUAL_ADJUSTMENT_OUT => 'Ajuste manual -',
            self::TYPE_MANUAL_ISSUE => 'Saida manual',
            self::TYPE_CONSTRUCTION_SITE_USAGE => 'Consumo obra',
            self::TYPE_MANUAL_ADJUSTMENT => 'Ajuste manual',
            self::TYPE_SALE => 'Venda',
            self::TYPE_RETURN => 'Devolucao',
            self::TYPE_STOCK_COUNT => 'Contagem',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function directionLabels(): array
    {
        return [
            self::DIRECTION_IN => 'Entrada',
            self::DIRECTION_OUT => 'Saida',
        ];
    }

    public static function directionForType(string $type): ?string
    {
        return match ($type) {
            self::TYPE_MANUAL_ADJUSTMENT_IN => self::DIRECTION_IN,
            self::TYPE_MANUAL_ADJUSTMENT_OUT,
            self::TYPE_MANUAL_ISSUE => self::DIRECTION_OUT,
            default => null,
        };
    }

    /**
     * @return array<string, string>
     */
    public static function reasonLabels(): array
    {
        return [
            self::REASON_STOCK_INITIAL => 'Stock inicial',
            self::REASON_CORRECTION_POSITIVE => 'Correcao positiva',
            self::REASON_INTERNAL_RETURN => 'Devolucao interna',
            self::REASON_BREAKAGE => 'Quebra',
            self::REASON_LOSS => 'Perda',
            self::REASON_INTERNAL_CONSUMPTION => 'Consumo interno',
            self::REASON_CORRECTION_NEGATIVE => 'Correcao negativa',
            self::REASON_OTHER => 'Outro',
        ];
    }

    /**
     * @return list<string>
     */
    public static function reasonCodesForType(string $type): array
    {
        return match ($type) {
            self::TYPE_MANUAL_ADJUSTMENT_IN => [
                self::REASON_STOCK_INITIAL,
                self::REASON_CORRECTION_POSITIVE,
                self::REASON_INTERNAL_RETURN,
                self::REASON_OTHER,
            ],
            self::TYPE_MANUAL_ADJUSTMENT_OUT => [
                self::REASON_BREAKAGE,
                self::REASON_LOSS,
                self::REASON_CORRECTION_NEGATIVE,
                self::REASON_OTHER,
            ],
            self::TYPE_MANUAL_ISSUE => [
                self::REASON_INTERNAL_CONSUMPTION,
                self::REASON_OTHER,
            ],
            default => [],
        };
    }

    public function isManual(): bool
    {
        return in_array($this->type, self::manualTypes(), true);
    }

    public function reasonLabel(): ?string
    {
        if (! is_string($this->reason_code) || $this->reason_code === '') {
            return null;
        }

        return self::reasonLabels()[$this->reason_code] ?? $this->reason_code;
    }
}
