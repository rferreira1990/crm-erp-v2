<?php

namespace App\Models;

use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Article extends Model
{
    use HasFactory;

    public const DEFAULT_CATEGORY_NAME = 'Produto';
    public const DEFAULT_UNIT_CODE = 'UN';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'code',
        'designation',
        'abbreviation',
        'product_family_id',
        'brand_id',
        'category_id',
        'unit_id',
        'vat_rate_id',
        'vat_exemption_reason_id',
        'supplier_id',
        'supplier_reference',
        'ean',
        'internal_notes',
        'print_notes',
        'cost_price',
        'sale_price',
        'default_margin',
        'direct_discount',
        'max_discount',
        'moves_stock',
        'stock_alert_enabled',
        'minimum_stock',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cost_price' => 'decimal:4',
            'sale_price' => 'decimal:4',
            'default_margin' => 'decimal:2',
            'direct_discount' => 'decimal:2',
            'max_discount' => 'decimal:2',
            'minimum_stock' => 'decimal:3',
            'moves_stock' => 'boolean',
            'stock_alert_enabled' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function productFamily(): BelongsTo
    {
        return $this->belongsTo(ProductFamily::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
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

    public function images(): HasMany
    {
        return $this->hasMany(ArticleImage::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(ArticleFile::class);
    }

    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }

    public function setDesignationAttribute(?string $value): void
    {
        $this->attributes['designation'] = $value !== null
            ? preg_replace('/\s+/', ' ', trim($value))
            : null;
    }

    public function setAbbreviationAttribute(?string $value): void
    {
        $normalized = $value !== null ? trim($value) : null;
        $this->attributes['abbreviation'] = $normalized !== '' ? $normalized : null;
    }

    public function setSupplierReferenceAttribute(?string $value): void
    {
        $normalized = $value !== null ? trim($value) : null;
        $this->attributes['supplier_reference'] = $normalized !== '' ? $normalized : null;
    }

    public function setEanAttribute(?string $value): void
    {
        $normalized = $value !== null ? trim($value) : null;
        $this->attributes['ean'] = $normalized !== '' ? $normalized : null;
    }

    public function setInternalNotesAttribute(?string $value): void
    {
        $normalized = $value !== null ? trim($value) : null;
        $this->attributes['internal_notes'] = $normalized !== '' ? $normalized : null;
    }

    public function setPrintNotesAttribute(?string $value): void
    {
        $normalized = $value !== null ? trim($value) : null;
        $this->attributes['print_notes'] = $normalized !== '' ? $normalized : null;
    }

    public static function defaultCategoryIdForCompany(int $companyId): ?int
    {
        return Category::query()
            ->visibleToCompany($companyId)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower(self::DEFAULT_CATEGORY_NAME)])
            ->orderByRaw('CASE WHEN company_id = ? THEN 0 ELSE 1 END', [$companyId])
            ->value('id');
    }

    public static function defaultUnitIdForCompany(int $companyId): ?int
    {
        return Unit::query()
            ->visibleToCompany($companyId)
            ->where('code', self::DEFAULT_UNIT_CODE)
            ->orderByRaw('CASE WHEN company_id = ? THEN 0 ELSE 1 END', [$companyId])
            ->value('id');
    }

    public static function generateNextCodeForFamily(int $companyId, int $productFamilyId): string
    {
        /** @var ProductFamily|null $family */
        $family = ProductFamily::query()
            ->where('company_id', $companyId)
            ->where('is_system', false)
            ->whereKey($productFamilyId)
            ->lockForUpdate()
            ->first();

        if (! $family) {
            throw new DomainException('Product family not found for article code generation.');
        }

        $familyCode = (string) ($family->family_code ?? '');
        if (! preg_match('/^\d{2}$/', $familyCode)) {
            throw new DomainException('Product family does not have a valid 2-digit family code.');
        }

        $lastCode = self::query()
            ->where('company_id', $companyId)
            ->where('product_family_id', $family->id)
            ->lockForUpdate()
            ->orderByDesc('id')
            ->value('code');

        $nextSequence = 1;
        if (is_string($lastCode) && preg_match('/-(\d{4})$/', $lastCode, $matches) === 1) {
            $nextSequence = ((int) $matches[1]) + 1;
        }

        if ($nextSequence > 9999) {
            throw new DomainException('Article code limit reached for this product family.');
        }

        return sprintf('%s-%04d', $familyCode, $nextSequence);
    }

    public static function createWithGeneratedCode(int $companyId, array $attributes): self
    {
        return DB::transaction(function () use ($companyId, $attributes): self {
            $productFamilyId = (int) ($attributes['product_family_id'] ?? 0);
            $attributes['code'] = self::generateNextCodeForFamily($companyId, $productFamilyId);
            $attributes['company_id'] = $companyId;

            /** @var self $article */
            $article = self::query()->create($attributes);

            return $article;
        });
    }

    public function marginPercent(): ?float
    {
        $cost = $this->cost_price !== null ? (float) $this->cost_price : null;
        $sale = $this->sale_price !== null ? (float) $this->sale_price : null;

        if ($cost === null || $sale === null || $cost <= 0) {
            return null;
        }

        return round((($sale - $cost) / $cost) * 100, 2);
    }
}

