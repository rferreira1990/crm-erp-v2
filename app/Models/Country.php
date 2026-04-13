<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Country extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'iso_code',
        'is_system',
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

    public function districts(): HasMany
    {
        return $this->hasMany(District::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function suppliers(): HasMany
    {
        return $this->hasMany(Supplier::class);
    }

    public function setNameAttribute(?string $value): void
    {
        $this->attributes['name'] = $value !== null
            ? trim($value)
            : null;
    }

    public function setIsoCodeAttribute(?string $value): void
    {
        $this->attributes['iso_code'] = $value !== null
            ? Str::upper(trim($value))
            : null;
    }
}
