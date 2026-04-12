<?php

namespace App\Models;

use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ProductFamily extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::saving(function (self $productFamily): void {
            if ($productFamily->is_system) {
                $productFamily->company_id = null;

                $duplicateSystemName = self::query()
                    ->where('is_system', true)
                    ->whereNull('company_id')
                    ->where('parent_id', $productFamily->parent_id)
                    ->whereRaw('LOWER(name) = ?', [self::normalizeNameKey((string) $productFamily->name)])
                    ->when($productFamily->exists, fn (Builder $query) => $query->whereKeyNot($productFamily->id))
                    ->exists();

                if ($duplicateSystemName) {
                    throw new DomainException('Duplicate global product family name is not allowed for this parent.');
                }
            }
        });
    }

    /**
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'parent_id',
        'is_system',
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

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
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

    public function hierarchyPathLabel(int $maxDepth = 10): string
    {
        $parts = [$this->name];
        $seenIds = [$this->id => true];
        $depth = 0;
        $cursor = $this->parent;

        while ($cursor !== null && $depth < $maxDepth) {
            if (isset($seenIds[$cursor->id])) {
                break;
            }

            $parts[] = $cursor->name;
            $seenIds[$cursor->id] = true;
            $cursor = $cursor->parent;
            $depth++;
        }

        return implode(' > ', array_reverse($parts));
    }

    public function setNameAttribute(?string $value): void
    {
        $this->attributes['name'] = $value !== null
            ? self::normalizeName($value)
            : null;
    }
}

