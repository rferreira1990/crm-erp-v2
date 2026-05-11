<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CalendarEventExternalSync extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_SYNCED = 'synced';
    public const STATUS_FAILED = 'failed';
    public const STATUS_DELETED = 'deleted';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'calendar_event_id',
        'integration_id',
        'external_uid',
        'external_href',
        'external_etag',
        'last_synced_at',
        'sync_status',
        'last_error',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'calendar_event_id' => 'integer',
            'integration_id' => 'integer',
            'last_synced_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function calendarEvent(): BelongsTo
    {
        return $this->belongsTo(CalendarEvent::class, 'calendar_event_id');
    }

    public function integration(): BelongsTo
    {
        return $this->belongsTo(CompanyCalendarIntegration::class, 'integration_id');
    }

    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }
}

