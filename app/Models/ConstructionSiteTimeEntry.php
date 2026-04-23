<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConstructionSiteTimeEntry extends Model
{
    use HasFactory;

    public const TASK_INSTALLATION = 'installation';
    public const TASK_MAINTENANCE = 'maintenance';
    public const TASK_TRAVEL = 'travel';
    public const TASK_PREPARATION = 'preparation';
    public const TASK_SUPPORT = 'support';
    public const TASK_OTHER = 'other';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'construction_site_id',
        'user_id',
        'work_date',
        'hours',
        'hourly_cost',
        'total_cost',
        'description',
        'task_type',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'hours' => 'decimal:2',
            'hourly_cost' => 'decimal:4',
            'total_cost' => 'decimal:4',
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

    public function worker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * @return list<string>
     */
    public static function taskTypes(): array
    {
        return [
            self::TASK_INSTALLATION,
            self::TASK_MAINTENANCE,
            self::TASK_TRAVEL,
            self::TASK_PREPARATION,
            self::TASK_SUPPORT,
            self::TASK_OTHER,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function taskTypeLabels(): array
    {
        return [
            self::TASK_INSTALLATION => 'Instalacao',
            self::TASK_MAINTENANCE => 'Manutencao',
            self::TASK_TRAVEL => 'Deslocacao',
            self::TASK_PREPARATION => 'Preparacao',
            self::TASK_SUPPORT => 'Suporte',
            self::TASK_OTHER => 'Outro',
        ];
    }

    public function taskTypeLabel(): ?string
    {
        if (! $this->task_type) {
            return null;
        }

        return self::taskTypeLabels()[$this->task_type] ?? $this->task_type;
    }

    public function taskTypeBadgeClass(): string
    {
        return match ($this->task_type) {
            self::TASK_INSTALLATION => 'badge-phoenix-primary',
            self::TASK_MAINTENANCE => 'badge-phoenix-info',
            self::TASK_TRAVEL => 'badge-phoenix-warning',
            self::TASK_PREPARATION => 'badge-phoenix-secondary',
            self::TASK_SUPPORT => 'badge-phoenix-success',
            self::TASK_OTHER => 'badge-phoenix-secondary',
            default => 'badge-phoenix-secondary',
        };
    }
}
