<?php

namespace App\Models;

use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class VatExemptionReason extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::saving(function (self $reason): void {
            if ($reason->is_system) {
                $reason->company_id = null;

                $duplicateSystemCode = self::query()
                    ->where('is_system', true)
                    ->whereNull('company_id')
                    ->where('code', self::normalizeCode((string) $reason->code))
                    ->when($reason->exists, fn (Builder $query) => $query->whereKeyNot($reason->id))
                    ->exists();

                if ($duplicateSystemCode) {
                    throw new DomainException('Duplicate global VAT exemption reason code is not allowed.');
                }
            }

            if ($reason->company_id === null && ! $reason->is_system) {
                throw new DomainException('Global VAT exemption reasons must be system records.');
            }
        });
    }

    /**
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'is_system',
        'code',
        'name',
        'legal_reference',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function vatRates(): HasMany
    {
        return $this->hasMany(VatRate::class);
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

    public static function normalizeCode(string $code): string
    {
        return Str::upper(trim($code));
    }

    public static function normalizeName(string $name): string
    {
        return preg_replace('/\s+/', ' ', trim($name)) ?? '';
    }

    public function isSystem(): bool
    {
        return $this->is_system;
    }

    public function setCodeAttribute(?string $value): void
    {
        $this->attributes['code'] = $value !== null
            ? self::normalizeCode($value)
            : null;
    }

    public function setNameAttribute(?string $value): void
    {
        $this->attributes['name'] = $value !== null
            ? self::normalizeName($value)
            : null;
    }

    public function setLegalReferenceAttribute(?string $value): void
    {
        $this->attributes['legal_reference'] = $value !== null && trim($value) !== ''
            ? self::normalizeName($value)
            : null;
    }
}

