<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class PaymentTerm extends Model
{
    use HasFactory;

    public const CALCULATION_FIXED_DAYS = 'fixed_days';
    public const CALCULATION_END_OF_MONTH_PLUS_DAYS = 'end_of_month_plus_days';

    protected static function booted(): void
    {
        static::saving(function (self $paymentTerm): void {
            if ($paymentTerm->is_system) {
                $paymentTerm->company_id = null;
            }

            if ($paymentTerm->company_id === null && ! $paymentTerm->is_system) {
                throw new DomainException('Global payment terms must be system records.');
            }

            if ($paymentTerm->is_system) {
                $duplicateSystemName = self::query()
                    ->where('is_system', true)
                    ->whereNull('company_id')
                    ->whereRaw('LOWER(name) = ?', [self::normalizeNameKey((string) $paymentTerm->name)])
                    ->when($paymentTerm->exists, fn (Builder $query) => $query->whereKeyNot($paymentTerm->id))
                    ->exists();

                if ($duplicateSystemName) {
                    throw new DomainException('Duplicate global payment term name is not allowed.');
                }
            }

            if (! in_array($paymentTerm->calculation_type, self::calculationTypes(), true)) {
                throw new DomainException('Invalid payment term calculation type.');
            }
        });
    }

    /**
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'name',
        'calculation_type',
        'days',
        'is_system',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'calculation_type' => 'string',
            'days' => 'integer',
            'is_system' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function overrides(): HasMany
    {
        return $this->hasMany(CompanyPaymentTermOverride::class);
    }

    public function scopeVisibleToCompany(Builder $query, int $companyId): Builder
    {
        return $query->where(function (Builder $builder) use ($companyId): void {
            $builder
                ->where('company_id', $companyId)
                ->orWhere(function (Builder $systemQuery) use ($companyId): void {
                    $systemQuery
                        ->where('is_system', true)
                        ->whereNull('company_id')
                        ->whereNotExists(function ($subQuery) use ($companyId): void {
                            $subQuery
                                ->selectRaw('1')
                                ->from('company_payment_term_overrides as cpto')
                                ->whereColumn('cpto.payment_term_id', 'payment_terms.id')
                                ->where('cpto.company_id', $companyId)
                                ->where('cpto.is_enabled', false);
                        });
                });
        });
    }

    public static function normalizeName(string $name): string
    {
        return preg_replace('/\s+/', ' ', trim($name)) ?? '';
    }

    /**
     * @return list<string>
     */
    public static function calculationTypes(): array
    {
        return [
            self::CALCULATION_FIXED_DAYS,
            self::CALCULATION_END_OF_MONTH_PLUS_DAYS,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function calculationTypeLabels(): array
    {
        return [
            self::CALCULATION_FIXED_DAYS => 'Dias fixos',
            self::CALCULATION_END_OF_MONTH_PLUS_DAYS => 'Final do mes + dias',
        ];
    }

    public static function normalizeNameKey(string $name): string
    {
        return Str::lower(self::normalizeName($name));
    }

    public function isSystem(): bool
    {
        return $this->is_system;
    }

    public function calculationTypeLabel(): string
    {
        return self::calculationTypeLabels()[$this->calculation_type] ?? $this->calculation_type;
    }

    public function calculateDueDate(CarbonInterface|string $referenceDate): CarbonImmutable
    {
        $baseDate = $referenceDate instanceof CarbonInterface
            ? CarbonImmutable::instance($referenceDate)
            : CarbonImmutable::parse($referenceDate);

        return match ($this->calculation_type) {
            self::CALCULATION_END_OF_MONTH_PLUS_DAYS => $baseDate->endOfMonth()->addDays($this->days),
            default => $baseDate->addDays($this->days),
        };
    }

    public function setNameAttribute(?string $value): void
    {
        $this->attributes['name'] = $value !== null
            ? self::normalizeName($value)
            : null;
    }

    public function setCalculationTypeAttribute(?string $value): void
    {
        $this->attributes['calculation_type'] = $value !== null
            ? Str::lower(trim($value))
            : null;
    }
}
