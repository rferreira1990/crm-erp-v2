<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Brand extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'name',
        'description',
        'website_url',
        'logo_path',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(BrandFile::class);
    }

    public function setNameAttribute(?string $value): void
    {
        $this->attributes['name'] = $value !== null ? trim($value) : null;
    }

    public function setDescriptionAttribute(?string $value): void
    {
        $this->attributes['description'] = $value !== null ? trim($value) : null;
    }

    public function setWebsiteUrlAttribute(?string $value): void
    {
        $this->attributes['website_url'] = $value !== null && $value !== '' ? trim($value) : null;
    }
}
