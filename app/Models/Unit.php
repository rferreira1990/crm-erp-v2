<?php

namespace App\Models;

use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Unit extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::saving(function (self $unit): void {
            if ($unit->is_system) {
                $unit->company_id = null;

                $duplicateSystemCode = self::query()
                    ->where('is_system', true)
                    ->whereNull('company_id')
                    ->where('code', self::normalizeCode((string) $unit->code))
                    ->when($unit->exists, fn (Builder $query) => $query->whereKeyNot($unit->id))
                    ->exists();

                if ($duplicateSystemCode) {
                    throw new DomainException('Duplicate global unit code is not allowed.');
                }
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
            ? trim($value)
            : null;
    }
}
