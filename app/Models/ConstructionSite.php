<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class ConstructionSite extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PLANNED = 'planned';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_ON_HOLD = 'on_hold';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'code',
        'name',
        'customer_id',
        'customer_contact_id',
        'quote_id',
        'address',
        'postal_code',
        'locality',
        'city',
        'country_id',
        'assigned_user_id',
        'status',
        'planned_start_date',
        'planned_end_date',
        'actual_start_date',
        'actual_end_date',
        'description',
        'internal_notes',
        'created_by',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'planned_start_date' => 'date',
            'planned_end_date' => 'date',
            'actual_start_date' => 'date',
            'actual_end_date' => 'date',
            'is_active' => 'boolean',
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

    public function customerContact(): BelongsTo
    {
        return $this->belongsTo(CustomerContact::class, 'customer_contact_id');
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function images(): HasMany
    {
        return $this->hasMany(ConstructionSiteImage::class)
            ->orderByDesc('is_primary')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function files(): HasMany
    {
        return $this->hasMany(ConstructionSiteFile::class)
            ->orderByDesc('id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(ConstructionSiteLog::class)
            ->orderByDesc('log_date')
            ->orderByDesc('id');
    }

    public function materialUsages(): HasMany
    {
        return $this->hasMany(ConstructionSiteMaterialUsage::class)
            ->orderByDesc('usage_date')
            ->orderByDesc('id');
    }

    public function timeEntries(): HasMany
    {
        return $this->hasMany(ConstructionSiteTimeEntry::class)
            ->orderByDesc('work_date')
            ->orderByDesc('id');
    }

    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * @return list<string>
     */
    public static function statuses(): array
    {
        return [
            self::STATUS_DRAFT,
            self::STATUS_PLANNED,
            self::STATUS_IN_PROGRESS,
            self::STATUS_ON_HOLD,
            self::STATUS_COMPLETED,
            self::STATUS_CANCELLED,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function statusLabels(): array
    {
        return [
            self::STATUS_DRAFT => 'Rascunho',
            self::STATUS_PLANNED => 'Planeada',
            self::STATUS_IN_PROGRESS => 'Em curso',
            self::STATUS_ON_HOLD => 'Em espera',
            self::STATUS_COMPLETED => 'Concluida',
            self::STATUS_CANCELLED => 'Cancelada',
        ];
    }

    public function statusLabel(): string
    {
        return self::statusLabels()[$this->status] ?? $this->status;
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT => 'badge-phoenix-warning',
            self::STATUS_PLANNED => 'badge-phoenix-info',
            self::STATUS_IN_PROGRESS => 'badge-phoenix-primary',
            self::STATUS_ON_HOLD => 'badge-phoenix-secondary',
            self::STATUS_COMPLETED => 'badge-phoenix-success',
            self::STATUS_CANCELLED => 'badge-phoenix-danger',
            default => 'badge-phoenix-secondary',
        };
    }

    public static function generateNextCode(int $companyId, int $year): string
    {
        $sequence = ConstructionSiteNumberSequence::query()
            ->where('company_id', $companyId)
            ->where('year', $year)
            ->lockForUpdate()
            ->first();

        if (! $sequence) {
            $sequence = ConstructionSiteNumberSequence::query()->create([
                'company_id' => $companyId,
                'year' => $year,
                'last_number' => 0,
            ]);
        }

        $next = ((int) $sequence->last_number) + 1;
        $sequence->forceFill(['last_number' => $next])->save();

        return sprintf('OBR-%d-%04d', $year, $next);
    }

    public static function createWithGeneratedCode(int $companyId, array $payload): self
    {
        return DB::transaction(function () use ($companyId, $payload): self {
            $payload['code'] = self::generateNextCode($companyId, (int) now()->year);
            $payload['company_id'] = $companyId;

            /** @var self $constructionSite */
            $constructionSite = self::query()->create($payload);

            return $constructionSite;
        });
    }
}
