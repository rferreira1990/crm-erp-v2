<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConstructionSiteLog extends Model
{
    use HasFactory;

    public const TYPE_NOTE = 'note';
    public const TYPE_PROGRESS = 'progress';
    public const TYPE_INCIDENT = 'incident';
    public const TYPE_CLIENT_REQUEST = 'client_request';
    public const TYPE_DELAY = 'delay';
    public const TYPE_MATERIAL_ISSUE = 'material_issue';
    public const TYPE_VISIT = 'visit';
    public const TYPE_OTHER = 'other';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'construction_site_id',
        'log_date',
        'type',
        'title',
        'description',
        'is_important',
        'created_by',
        'assigned_user_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'log_date' => 'date',
            'is_important' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function constructionSite(): BelongsTo
    {
        return $this->belongsTo(ConstructionSite::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(ConstructionSiteLogImage::class)
            ->orderByDesc('is_primary')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function files(): HasMany
    {
        return $this->hasMany(ConstructionSiteLogFile::class)
            ->orderByDesc('id');
    }

    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * @return list<string>
     */
    public static function types(): array
    {
        return [
            self::TYPE_NOTE,
            self::TYPE_PROGRESS,
            self::TYPE_INCIDENT,
            self::TYPE_CLIENT_REQUEST,
            self::TYPE_DELAY,
            self::TYPE_MATERIAL_ISSUE,
            self::TYPE_VISIT,
            self::TYPE_OTHER,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function typeLabels(): array
    {
        return [
            self::TYPE_NOTE => 'Nota',
            self::TYPE_PROGRESS => 'Progresso',
            self::TYPE_INCIDENT => 'Incidente',
            self::TYPE_CLIENT_REQUEST => 'Pedido do cliente',
            self::TYPE_DELAY => 'Atraso',
            self::TYPE_MATERIAL_ISSUE => 'Falta de material',
            self::TYPE_VISIT => 'Visita',
            self::TYPE_OTHER => 'Outro',
        ];
    }

    public function typeLabel(): string
    {
        return self::typeLabels()[$this->type] ?? $this->type;
    }

    public function typeBadgeClass(): string
    {
        return match ($this->type) {
            self::TYPE_PROGRESS => 'badge-phoenix-success',
            self::TYPE_INCIDENT => 'badge-phoenix-danger',
            self::TYPE_CLIENT_REQUEST => 'badge-phoenix-info',
            self::TYPE_DELAY => 'badge-phoenix-warning',
            self::TYPE_MATERIAL_ISSUE => 'badge-phoenix-warning',
            self::TYPE_VISIT => 'badge-phoenix-primary',
            self::TYPE_NOTE => 'badge-phoenix-secondary',
            default => 'badge-phoenix-secondary',
        };
    }
}
