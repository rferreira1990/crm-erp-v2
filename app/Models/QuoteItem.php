<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuoteItem extends Model
{
    use HasFactory;

    public const TYPE_ARTICLE = 'article';
    public const TYPE_TEXT = 'text';
    public const TYPE_SECTION = 'section';
    public const TYPE_NOTE = 'note';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'quote_id',
        'sort_order',
        'line_type',
        'article_id',
        'article_code',
        'article_designation',
        'description',
        'internal_description',
        'quantity',
        'unit_id',
        'unit_code',
        'unit_name',
        'unit_price',
        'discount_percent',
        'vat_rate_id',
        'vat_rate_name',
        'vat_rate_percentage',
        'vat_exemption_reason_id',
        'vat_exemption_reason_code',
        'vat_exemption_reason_name',
        'subtotal',
        'discount_amount',
        'tax_amount',
        'total',
        'metadata',
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
            'vat_rate_percentage' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'metadata' => 'array',
        ];
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function vatRate(): BelongsTo
    {
        return $this->belongsTo(VatRate::class);
    }

    public function vatExemptionReason(): BelongsTo
    {
        return $this->belongsTo(VatExemptionReason::class);
    }

    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeForQuote(Builder $query, int $quoteId): Builder
    {
        return $query->where('quote_id', $quoteId);
    }

    /**
     * @return list<string>
     */
    public static function lineTypes(): array
    {
        return [
            self::TYPE_ARTICLE,
            self::TYPE_TEXT,
            self::TYPE_SECTION,
            self::TYPE_NOTE,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function lineTypeLabels(): array
    {
        return [
            self::TYPE_ARTICLE => 'Artigo',
            self::TYPE_TEXT => 'Texto livre',
            self::TYPE_SECTION => 'Secao',
            self::TYPE_NOTE => 'Nota',
        ];
    }

    /**
     * @return array{
     *   subtotal: float,
     *   discount_amount: float,
     *   tax_amount: float,
     *   total: float
     * }
     */
    public static function calculateAmounts(
        float $quantity,
        float $unitPrice,
        float $discountPercent,
        float $vatPercent,
        bool $isExempt
    ): array {
        $subtotal = round($quantity * $unitPrice, 2);
        $discountAmount = round($subtotal * ($discountPercent / 100), 2);
        $taxableBase = round($subtotal - $discountAmount, 2);
        $taxAmount = $isExempt ? 0.0 : round($taxableBase * ($vatPercent / 100), 2);
        $total = round($taxableBase + $taxAmount, 2);

        return [
            'subtotal' => $subtotal,
            'discount_amount' => $discountAmount,
            'tax_amount' => $taxAmount,
            'total' => $total,
        ];
    }

    public function setDescriptionAttribute(?string $value): void
    {
        $this->attributes['description'] = $value !== null
            ? trim($value)
            : '';
    }

    public function setInternalDescriptionAttribute(?string $value): void
    {
        $this->attributes['internal_description'] = $this->normalizeNullableString($value);
    }

    private function normalizeNullableString(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim($value);

        return $normalized !== '' ? $normalized : null;
    }
}
