<?php

namespace App\Models;

use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ConstructionSiteMaterialUsage extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_POSTED = 'posted';
    public const STATUS_CANCELLED = 'cancelled';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'construction_site_id',
        'number',
        'usage_date',
        'notes',
        'created_by',
        'posted_at',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'usage_date' => 'date',
            'posted_at' => 'datetime',
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

    public function items(): HasMany
    {
        return $this->hasMany(ConstructionSiteMaterialUsageItem::class)
            ->orderBy('id');
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'reference_id')
            ->where('reference_type', StockMovement::REFERENCE_CONSTRUCTION_SITE_MATERIAL_USAGE)
            ->orderByDesc('movement_date')
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
            self::STATUS_POSTED,
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
            self::STATUS_POSTED => 'Fechado',
            self::STATUS_CANCELLED => 'Cancelado',
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
            self::STATUS_POSTED => 'badge-phoenix-success',
            self::STATUS_CANCELLED => 'badge-phoenix-danger',
            default => 'badge-phoenix-secondary',
        };
    }

    public function isEditable(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function canPost(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function canCancel(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function totalLines(): int
    {
        if ($this->relationLoaded('items')) {
            return $this->items->count();
        }

        return (int) $this->items()->count();
    }

    public function totalEstimatedCost(): float
    {
        if ($this->relationLoaded('items')) {
            return round((float) $this->items->sum(fn (ConstructionSiteMaterialUsageItem $item): float => $item->lineTotal()), 4);
        }

        return round(
            (float) $this->items()
                ->selectRaw('COALESCE(SUM(quantity * COALESCE(unit_cost, 0)), 0) as total')
                ->value('total'),
            4
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function applyStatusTransition(string $toStatus): array
    {
        $normalized = strtolower(trim($toStatus));

        if ($normalized === $this->status) {
            throw new DomainException('Invalid construction site material usage status transition.');
        }

        return match ($this->status) {
            self::STATUS_DRAFT => match ($normalized) {
                self::STATUS_POSTED => ['status' => self::STATUS_POSTED, 'posted_at' => now()],
                self::STATUS_CANCELLED => ['status' => self::STATUS_CANCELLED],
                default => throw new DomainException('Invalid construction site material usage status transition.'),
            },
            default => throw new DomainException('Invalid construction site material usage status transition.'),
        };
    }

    public static function generateNextNumber(int $companyId, int $year): string
    {
        $sequence = ConstructionSiteMaterialUsageNumberSequence::query()
            ->where('company_id', $companyId)
            ->where('year', $year)
            ->lockForUpdate()
            ->first();

        if (! $sequence) {
            $sequence = ConstructionSiteMaterialUsageNumberSequence::query()->create([
                'company_id' => $companyId,
                'year' => $year,
                'last_number' => 0,
            ]);
        }

        $next = ((int) $sequence->last_number) + 1;
        $sequence->forceFill(['last_number' => $next])->save();

        return sprintf('CSM-%d-%04d', $year, $next);
    }

    public static function createWithGeneratedNumber(int $companyId, array $payload): self
    {
        return DB::transaction(function () use ($companyId, $payload): self {
            $usageDate = isset($payload['usage_date']) ? Carbon::parse((string) $payload['usage_date']) : Carbon::now();
            $payload['number'] = self::generateNextNumber($companyId, (int) $usageDate->year);
            $payload['company_id'] = $companyId;

            /** @var self $usage */
            $usage = self::query()->create($payload);

            return $usage;
        });
    }
}
