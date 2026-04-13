<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerContact extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'customer_id',
        'name',
        'email',
        'phone',
        'job_title',
        'notes',
        'is_primary',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeForCustomer(Builder $query, int $customerId): Builder
    {
        return $query->where('customer_id', $customerId);
    }

    public function setNameAttribute(?string $value): void
    {
        $this->attributes['name'] = $value !== null
            ? preg_replace('/\s+/', ' ', trim($value))
            : null;
    }

    public function setEmailAttribute(?string $value): void
    {
        $normalized = $this->normalizeNullableString($value);
        $this->attributes['email'] = $normalized !== null
            ? mb_strtolower($normalized)
            : null;
    }

    public function setPhoneAttribute(?string $value): void
    {
        $this->attributes['phone'] = $this->normalizeNullableString($value);
    }

    public function setJobTitleAttribute(?string $value): void
    {
        $this->attributes['job_title'] = $this->normalizeNullableString($value);
    }

    public function setNotesAttribute(?string $value): void
    {
        $this->attributes['notes'] = $this->normalizeNullableString($value);
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
