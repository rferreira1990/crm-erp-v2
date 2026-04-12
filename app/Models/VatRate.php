<?php

namespace App\Models;

use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class VatRate extends Model
{
    use HasFactory;

    public const REGION_MAINLAND = 'mainland';
    public const REGION_MADEIRA = 'madeira';
    public const REGION_AZORES = 'azores';

    protected static function booted(): void
    {
        static::saving(function (self $vatRate): void {
            if ($vatRate->is_system) {
                $vatRate->company_id = null;
            }

            if ($vatRate->company_id === null && ! $vatRate->is_system) {
                throw new DomainException('Global VAT rates must be system records.');
            }

            if ($vatRate->is_system) {
                $duplicateSystemRate = self::query()
                    ->where('is_system', true)
                    ->whereNull('company_id')
                    ->where('region', $vatRate->region)
                    ->whereRaw('LOWER(name) = ?', [self::normalizeNameKey((string) $vatRate->name)])
                    ->when($vatRate->exists, fn (Builder $query) => $query->whereKeyNot($vatRate->id))
                    ->exists();

                if ($duplicateSystemRate) {
                    throw new DomainException('Duplicate global VAT rate name is not allowed in the same region.');
                }
            }

            if (! $vatRate->is_exempt && $vatRate->vat_exemption_reason_id !== null) {
                throw new DomainException('Non-exempt VAT rate cannot have an exemption reason.');
            }

            if ($vatRate->is_exempt && (float) $vatRate->rate !== 0.0) {
                throw new DomainException('Exempt VAT rate must have rate 0.');
            }

            if ($vatRate->is_exempt && ! $vatRate->is_system && $vatRate->vat_exemption_reason_id === null) {
                throw new DomainException('Exempt company VAT rate must have an exemption reason.');
            }
        });
    }

    /**
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'is_system',
        'name',
        'region',
        'rate',
        'is_exempt',
        'vat_exemption_reason_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rate' => 'decimal:2',
            'is_system' => 'boolean',
            'is_exempt' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function vatExemptionReason(): BelongsTo
    {
        return $this->belongsTo(VatExemptionReason::class);
    }

    public function scopeVisibleToCompany(Builder $query, int $companyId): Builder
    {
        return $query->where(function (Builder $builder) use ($companyId): void {
            $builder->where(function (Builder $systemQuery): void {
                $systemQuery->where('is_system', true)
                    ->whereNull('company_id');
            })->orWhere('company_id', $companyId);
        });
    }

    /**
     * @return list<string>
     */
    public static function regions(): array
    {
        return [
            self::REGION_MAINLAND,
            self::REGION_MADEIRA,
            self::REGION_AZORES,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function regionLabels(): array
    {
        return [
            self::REGION_MAINLAND => 'Continente',
            self::REGION_MADEIRA => 'Madeira',
            self::REGION_AZORES => 'Acores',
        ];
    }

    public static function normalizeName(string $name): string
    {
        return preg_replace('/\s+/', ' ', trim($name)) ?? '';
    }

    public static function normalizeNameKey(string $name): string
    {
        return Str::lower(self::normalizeName($name));
    }

    public function isSystem(): bool
    {
        return $this->is_system;
    }

    public function regionLabel(): string
    {
        if ($this->region === null) {
            return '-';
        }

        return self::regionLabels()[$this->region] ?? $this->region;
    }

    public function setNameAttribute(?string $value): void
    {
        $this->attributes['name'] = $value !== null
            ? self::normalizeName($value)
            : null;
    }

    public function setRegionAttribute(?string $value): void
    {
        $this->attributes['region'] = $value !== null && trim($value) !== ''
            ? Str::lower(trim($value))
            : null;
    }

    public function calculateVatAmount(float $baseAmount): float
    {
        return round($baseAmount * ((float) $this->rate / 100), 2);
    }
}
