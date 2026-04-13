<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierContact extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'supplier_id',
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

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeForSupplier(Builder $query, int $supplierId): Builder
    {
        return $query->where('supplier_id', $supplierId);
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
