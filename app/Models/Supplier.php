<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Supplier extends Model
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
        'supplier_type',
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
        'payment_term_id',
        'default_vat_rate_id',
        'default_payment_method_id',
        'bank_name',
        'bic_swift',
        'iban',
        'payment_notes',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
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

    public function defaultVatRate(): BelongsTo
    {
        return $this->belongsTo(VatRate::class, 'default_vat_rate_id');
    }

    public function defaultPaymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'default_payment_method_id');
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(SupplierContact::class);
    }

    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * @return list<string>
     */
    public static function supplierTypes(): array
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
    public static function supplierTypeLabels(): array
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

    public function supplierTypeLabel(): string
    {
        return self::supplierTypeLabels()[$this->supplier_type] ?? $this->supplier_type;
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

    public function setIbanAttribute(?string $value): void
    {
        $this->attributes['iban'] = $this->normalizeNullableString($value);
    }

    public function setBankNameAttribute(?string $value): void
    {
        $this->attributes['bank_name'] = $this->normalizeNullableString($value);
    }

    public function setBicSwiftAttribute(?string $value): void
    {
        $this->attributes['bic_swift'] = $this->normalizeNullableString($value);
    }

    public function setPaymentNotesAttribute(?string $value): void
    {
        $this->attributes['payment_notes'] = $this->normalizeNullableString($value);
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
