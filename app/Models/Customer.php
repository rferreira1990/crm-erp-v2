<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Customer extends Model
{
    use HasFactory;

    public const TYPE_INDIVIDUAL = 'individual';
    public const TYPE_COMPANY = 'company';
    public const TYPE_OTHER = 'other';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'customer_type',
        'name',
        'address',
        'postal_code',
        'locality',
        'city',
        'country_id',
        'nif',
        'phone',
        'mobile',
        'email',
        'website',
        'notes',
        'logo_path',
        'price_tier_id',
        'payment_term_id',
        'default_vat_rate_id',
        'default_commercial_discount',
        'has_credit_limit',
        'credit_limit',
        'print_comments',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'default_commercial_discount' => 'decimal:2',
            'has_credit_limit' => 'boolean',
            'credit_limit' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function paymentTerm(): BelongsTo
    {
        return $this->belongsTo(PaymentTerm::class);
    }

    public function priceTier(): BelongsTo
    {
        return $this->belongsTo(PriceTier::class);
    }

    public function defaultVatRate(): BelongsTo
    {
        return $this->belongsTo(VatRate::class, 'default_vat_rate_id');
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(CustomerContact::class);
    }

    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * @return list<string>
     */
    public static function customerTypes(): array
    {
        return [
            self::TYPE_INDIVIDUAL,
            self::TYPE_COMPANY,
            self::TYPE_OTHER,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function customerTypeLabels(): array
    {
        return [
            self::TYPE_INDIVIDUAL => 'Particular',
            self::TYPE_COMPANY => 'Empresa',
            self::TYPE_OTHER => 'Outro',
        ];
    }

    public static function defaultCountryId(): ?int
    {
        return Country::query()
            ->where('iso_code', 'PT')
            ->orWhereRaw('LOWER(name) = ?', ['portugal'])
            ->orderByRaw("CASE WHEN iso_code = 'PT' THEN 0 ELSE 1 END")
            ->value('id');
    }

    public static function defaultPaymentTermIdForCompany(int $companyId): ?int
    {
        $transferTerm = PaymentTerm::query()
            ->visibleToCompany($companyId)
            ->whereRaw('LOWER(name) LIKE ?', ['%transfer%'])
            ->orderByRaw('CASE WHEN company_id = ? THEN 0 ELSE 1 END', [$companyId])
            ->value('id');

        if ($transferTerm !== null) {
            return (int) $transferTerm;
        }

        return null;
    }

    public static function defaultPriceTierIdForCompany(int $companyId): ?int
    {
        return PriceTier::query()
            ->visibleToCompany($companyId)
            ->active()
            ->where('is_system', true)
            ->where('is_default', true)
            ->orderBy('id')
            ->value('id');
    }

    public function customerTypeLabel(): string
    {
        return self::customerTypeLabels()[$this->customer_type] ?? $this->customer_type;
    }

    public function setNameAttribute(?string $value): void
    {
        $this->attributes['name'] = $value !== null
            ? preg_replace('/\s+/', ' ', trim($value))
            : null;
    }

    public function setAddressAttribute(?string $value): void
    {
        $this->attributes['address'] = $this->normalizeNullableString($value);
    }

    public function setPostalCodeAttribute(?string $value): void
    {
        $this->attributes['postal_code'] = $this->normalizeNullableString($value);
    }

    public function setLocalityAttribute(?string $value): void
    {
        $this->attributes['locality'] = $this->normalizeNullableString($value);
    }

    public function setCityAttribute(?string $value): void
    {
        $this->attributes['city'] = $this->normalizeNullableString($value);
    }

    public function setNifAttribute(?string $value): void
    {
        $this->attributes['nif'] = $this->normalizeNullableString($value);
    }

    public function setPhoneAttribute(?string $value): void
    {
        $this->attributes['phone'] = $this->normalizeNullableString($value);
    }

    public function setMobileAttribute(?string $value): void
    {
        $this->attributes['mobile'] = $this->normalizeNullableString($value);
    }

    public function setEmailAttribute(?string $value): void
    {
        $normalized = $this->normalizeNullableString($value);
        $this->attributes['email'] = $normalized !== null
            ? Str::lower($normalized)
            : null;
    }

    public function setWebsiteAttribute(?string $value): void
    {
        $this->attributes['website'] = $this->normalizeNullableString($value);
    }

    public function setNotesAttribute(?string $value): void
    {
        $this->attributes['notes'] = $this->normalizeNullableString($value);
    }

    public function setPrintCommentsAttribute(?string $value): void
    {
        $this->attributes['print_comments'] = $this->normalizeNullableString($value);
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
