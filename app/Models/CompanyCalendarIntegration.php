<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CompanyCalendarIntegration extends Model
{
    use HasFactory;

    public const PROVIDER_CALDAV = 'caldav';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'user_id',
        'provider',
        'name',
        'username',
        'password',
        'base_url',
        'calendar_url',
        'is_active',
        'sync_enabled',
        'last_sync_at',
        'metadata',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'encrypted',
            'is_active' => 'boolean',
            'sync_enabled' => 'boolean',
            'last_sync_at' => 'datetime',
            'metadata' => 'array',
            'user_id' => 'integer',
            'company_id' => 'integer',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function externalSyncs(): HasMany
    {
        return $this->hasMany(CalendarEventExternalSync::class, 'integration_id');
    }

    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }
}

