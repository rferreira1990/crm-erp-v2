<?php

namespace App\Models;

use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class PriceTier extends Model
{
    use HasFactory;

    public const SYSTEM_DEFAULT_NAME = 'Normal';

    protected static function booted(): void
    {
        static::saving(function (self $priceTier): void {
            if ($priceTier->is_system) {
                $priceTier->company_id = null;
            }

            if ($priceTier->company_id === null && ! $priceTier->is_system) {
                throw new DomainException('Global price tiers must be system records.');
            }

            if (! $priceTier->is_system && $priceTier->is_default) {
                throw new DomainException('Only system price tiers can be marked as default.');
            }

            if ($priceTier->is_system) {
                $duplicateSystemName = self::query()
                    ->where('is_system', true)
                    ->whereNull('company_id')
                    ->whereRaw('LOWER(name) = ?', [self::normalizeNameKey((string) $priceTier->name)])
                    ->when($priceTier->exists, fn (Builder $query) => $query->whereKeyNot($priceTier->id))
                    ->exists();

                if ($duplicateSystemName) {
                    throw new DomainException('Duplicate global price tier name is not allowed.');
                }

                if ($priceTier->is_default) {
                    $anotherDefaultSystemTier = self::query()
                        ->where('is_system', true)
                        ->whereNull('company_id')
                        ->where('is_default', true)
                        ->when($priceTier->exists, fn (Builder $query) => $query->whereKeyNot($priceTier->id))
                        ->exists();

                    if ($anotherDefaultSystemTier) {
                        throw new DomainException('Only one default global price tier is allowed.');
                    }
                }
            }
        });
    }

    /**
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'name',
        'percentage_adjustment',
        'is_system',
        'is_default',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'percentage_adjustment' => 'decimal:2',
            'is_system' => 'boolean',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
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

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
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

    public function applyToAmount(float $basePrice): float
    {
        $percentage = (float) $this->percentage_adjustment;

        return round($basePrice * (1 + ($percentage / 100)), 2);
    }

    public function setNameAttribute(?string $value): void
    {
        $this->attributes['name'] = $value !== null
            ? self::normalizeName($value)
            : null;
    }
}

