<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesDocumentItem extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'sales_document_id',
        'line_order',
        'article_id',
        'article_code',
        'description',
        'unit_id',
        'unit_name_snapshot',
        'quantity',
        'unit_price',
        'discount_percent',
        'line_subtotal',
        'line_discount_total',
        'tax_rate',
        'line_tax_total',
        'line_total',
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
            'line_subtotal' => 'decimal:2',
            'line_discount_total' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'line_tax_total' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    public function salesDocument(): BelongsTo
    {
        return $this->belongsTo(SalesDocument::class);
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * @return array{
     *   line_subtotal: float,
     *   line_discount_total: float,
     *   line_tax_total: float,
     *   line_total: float
     * }
     */
    public static function calculateAmounts(
        float $quantity,
        float $unitPrice,
        float $discountPercent,
        float $taxRate
    ): array {
        $lineSubtotal = round($quantity * $unitPrice, 2);
        $lineDiscountTotal = round($lineSubtotal * ($discountPercent / 100), 2);
        $taxableBase = round($lineSubtotal - $lineDiscountTotal, 2);
        $lineTaxTotal = round($taxableBase * ($taxRate / 100), 2);
        $lineTotal = round($taxableBase + $lineTaxTotal, 2);

        return [
            'line_subtotal' => $lineSubtotal,
            'line_discount_total' => $lineDiscountTotal,
            'line_tax_total' => $lineTaxTotal,
            'line_total' => $lineTotal,
        ];
    }
}

