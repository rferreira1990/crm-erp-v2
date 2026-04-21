<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierQuoteRequestItem extends Model
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
        'supplier_quote_request_id',
        'line_order',
        'line_type',
        'article_id',
        'article_code',
        'description',
        'unit_name',
        'quantity',
        'internal_notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
        ];
    }

    public function supplierQuoteRequest(): BelongsTo
    {
        return $this->belongsTo(SupplierQuoteRequest::class);
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->where('company_id', $companyId);
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
            self::TYPE_TEXT => 'Texto',
            self::TYPE_SECTION => 'Secao',
            self::TYPE_NOTE => 'Nota',
        ];
    }
}

