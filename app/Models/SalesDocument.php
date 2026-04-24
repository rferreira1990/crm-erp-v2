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

class SalesDocument extends Model
{
    use HasFactory;

    public const SOURCE_MANUAL = 'manual';
    public const SOURCE_QUOTE = 'quote';
    public const SOURCE_CONSTRUCTION_SITE = 'construction_site';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_ISSUED = 'issued';
    public const STATUS_CANCELLED = 'cancelled';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'number',
        'source_type',
        'quote_id',
        'construction_site_id',
        'customer_id',
        'customer_contact_id',
        'customer_name_snapshot',
        'customer_nif_snapshot',
        'customer_email_snapshot',
        'customer_phone_snapshot',
        'customer_address_snapshot',
        'customer_contact_name_snapshot',
        'customer_contact_email_snapshot',
        'customer_contact_phone_snapshot',
        'status',
        'issue_date',
        'due_date',
        'notes',
        'currency',
        'subtotal',
        'discount_total',
        'tax_total',
        'grand_total',
        'issued_at',
        'created_by',
        'updated_by',
        'pdf_path',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'due_date' => 'date',
            'issued_at' => 'datetime',
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'grand_total' => 'decimal:2',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function constructionSite(): BelongsTo
    {
        return $this->belongsTo(ConstructionSite::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function customerContact(): BelongsTo
    {
        return $this->belongsTo(CustomerContact::class, 'customer_contact_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SalesDocumentItem::class)->orderBy('line_order')->orderBy('id');
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'reference_id')
            ->where('reference_type', StockMovement::REFERENCE_SALES_DOCUMENT)
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
    public static function sources(): array
    {
        return [
            self::SOURCE_MANUAL,
            self::SOURCE_QUOTE,
            self::SOURCE_CONSTRUCTION_SITE,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function sourceLabels(): array
    {
        return [
            self::SOURCE_MANUAL => 'Manual',
            self::SOURCE_QUOTE => 'Orcamento',
            self::SOURCE_CONSTRUCTION_SITE => 'Obra',
        ];
    }

    public function sourceLabel(): string
    {
        return self::sourceLabels()[$this->source_type] ?? $this->source_type;
    }

    /**
     * @return list<string>
     */
    public static function statuses(): array
    {
        return [
            self::STATUS_DRAFT,
            self::STATUS_ISSUED,
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
            self::STATUS_ISSUED => 'Emitido',
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
            self::STATUS_ISSUED => 'badge-phoenix-success',
            self::STATUS_CANCELLED => 'badge-phoenix-danger',
            default => 'badge-phoenix-secondary',
        };
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isIssued(): bool
    {
        return $this->status === self::STATUS_ISSUED;
    }

    public function isManualSource(): bool
    {
        return $this->source_type === self::SOURCE_MANUAL;
    }

    public function isEditableDraft(): bool
    {
        return $this->isDraft();
    }

    public function canTransitionTo(string $toStatus): bool
    {
        $target = strtolower(trim($toStatus));
        if (! in_array($target, self::statuses(), true) || $target === $this->status) {
            return false;
        }

        return match ($this->status) {
            self::STATUS_DRAFT => in_array($target, [self::STATUS_ISSUED, self::STATUS_CANCELLED], true),
            default => false,
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function applyStatusTransition(string $toStatus): array
    {
        $target = strtolower(trim($toStatus));
        if (! $this->canTransitionTo($target)) {
            throw new DomainException('Invalid sales document status transition.');
        }

        return match ($target) {
            self::STATUS_ISSUED => [
                'status' => self::STATUS_ISSUED,
                'issued_at' => now(),
            ],
            self::STATUS_CANCELLED => [
                'status' => self::STATUS_CANCELLED,
            ],
            default => throw new DomainException('Invalid sales document status transition.'),
        };
    }

    public function shouldMoveStock(): bool
    {
        return match ($this->source_type) {
            self::SOURCE_MANUAL => true,
            self::SOURCE_CONSTRUCTION_SITE => false,
            self::SOURCE_QUOTE => ! $this->quoteHasPostedConstructionConsumption(),
            default => false,
        };
    }

    public function stockRuleReasonLabel(): string
    {
        if ($this->source_type === self::SOURCE_CONSTRUCTION_SITE) {
            return 'Nao movimenta stock porque a origem e obra.';
        }

        if ($this->source_type === self::SOURCE_QUOTE && $this->quoteHasPostedConstructionConsumption()) {
            return 'Nao movimenta stock porque o orcamento ja tem consumos de obra confirmados.';
        }

        return 'Movimenta stock quando emitido.';
    }

    public function quoteHasPostedConstructionConsumption(): bool
    {
        if ($this->quote_id === null) {
            return false;
        }

        return ConstructionSite::query()
            ->forCompany((int) $this->company_id)
            ->where('quote_id', (int) $this->quote_id)
            ->whereHas('materialUsages', function ($query): void {
                $query->where('status', ConstructionSiteMaterialUsage::STATUS_POSTED);
            })
            ->exists();
    }

    public function recalculateTotalsFromItems(): void
    {
        $totals = $this->items()
            ->reorder()
            ->selectRaw('COALESCE(SUM(line_subtotal), 0) as subtotal_sum')
            ->selectRaw('COALESCE(SUM(line_discount_total), 0) as discount_sum')
            ->selectRaw('COALESCE(SUM(line_tax_total), 0) as tax_sum')
            ->selectRaw('COALESCE(SUM(line_total), 0) as total_sum')
            ->first();

        $this->forceFill([
            'subtotal' => round((float) ($totals?->subtotal_sum ?? 0), 2),
            'discount_total' => round((float) ($totals?->discount_sum ?? 0), 2),
            'tax_total' => round((float) ($totals?->tax_sum ?? 0), 2),
            'grand_total' => round((float) ($totals?->total_sum ?? 0), 2),
        ])->save();
    }

    public static function generateNextNumber(int $companyId, int $year): string
    {
        $sequence = SalesDocumentNumberSequence::query()
            ->where('company_id', $companyId)
            ->where('year', $year)
            ->lockForUpdate()
            ->first();

        if (! $sequence) {
            $sequence = SalesDocumentNumberSequence::query()->create([
                'company_id' => $companyId,
                'year' => $year,
                'last_number' => 0,
            ]);
        }

        $next = ((int) $sequence->last_number) + 1;
        $sequence->forceFill(['last_number' => $next])->save();

        return sprintf('DV-%d-%04d', $year, $next);
    }

    public static function createWithGeneratedNumber(int $companyId, array $payload): self
    {
        return DB::transaction(function () use ($companyId, $payload): self {
            $issueDate = isset($payload['issue_date']) ? Carbon::parse((string) $payload['issue_date']) : Carbon::now();
            $payload['number'] = self::generateNextNumber($companyId, (int) $issueDate->year);
            $payload['company_id'] = $companyId;

            /** @var self $salesDocument */
            $salesDocument = self::query()->create($payload);

            return $salesDocument;
        });
    }
}

