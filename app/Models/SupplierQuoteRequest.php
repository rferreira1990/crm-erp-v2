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

class SupplierQuoteRequest extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_SENT = 'sent';
    public const STATUS_PARTIALLY_RECEIVED = 'partially_received';
    public const STATUS_RECEIVED = 'received';
    public const STATUS_COMPARED = 'compared';
    public const STATUS_AWARDED = 'awarded';
    public const STATUS_CANCELLED = 'cancelled';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'number',
        'title',
        'status',
        'issue_date',
        'response_deadline',
        'awarded_at',
        'internal_notes',
        'supplier_notes',
        'estimated_total',
        'awarded_total',
        'created_by',
        'assigned_user_id',
        'pdf_path',
        'email_last_sent_at',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'response_deadline' => 'date',
            'awarded_at' => 'datetime',
            'email_last_sent_at' => 'datetime',
            'estimated_total' => 'decimal:2',
            'awarded_total' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SupplierQuoteRequestItem::class)
            ->orderBy('line_order')
            ->orderBy('id');
    }

    public function invitedSuppliers(): HasMany
    {
        return $this->hasMany(SupplierQuoteRequestSupplier::class)
            ->orderBy('id');
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
            self::STATUS_SENT,
            self::STATUS_PARTIALLY_RECEIVED,
            self::STATUS_RECEIVED,
            self::STATUS_COMPARED,
            self::STATUS_AWARDED,
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
            self::STATUS_SENT => 'Enviado',
            self::STATUS_PARTIALLY_RECEIVED => 'Parcialmente recebido',
            self::STATUS_RECEIVED => 'Recebido',
            self::STATUS_COMPARED => 'Comparado',
            self::STATUS_AWARDED => 'Adjudicado',
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
            self::STATUS_SENT, self::STATUS_PARTIALLY_RECEIVED => 'badge-phoenix-info',
            self::STATUS_RECEIVED, self::STATUS_COMPARED, self::STATUS_AWARDED => 'badge-phoenix-success',
            self::STATUS_CANCELLED => 'badge-phoenix-danger',
            default => 'badge-phoenix-secondary',
        };
    }

    public function isEditable(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT], true);
    }

    public static function generateNextNumber(int $companyId, int $year): string
    {
        $sequence = SupplierQuoteRequestNumberSequence::query()
            ->where('company_id', $companyId)
            ->where('year', $year)
            ->lockForUpdate()
            ->first();

        if (! $sequence) {
            $sequence = SupplierQuoteRequestNumberSequence::query()->create([
                'company_id' => $companyId,
                'year' => $year,
                'last_number' => 0,
            ]);
        }

        $next = ((int) $sequence->last_number) + 1;
        $sequence->forceFill(['last_number' => $next])->save();

        return sprintf('RFQ-%d-%04d', $year, $next);
    }

    public static function createWithGeneratedNumber(int $companyId, array $payload): self
    {
        return DB::transaction(function () use ($companyId, $payload): self {
            $issueDate = isset($payload['issue_date'])
                ? Carbon::parse((string) $payload['issue_date'])
                : Carbon::now();

            $payload['number'] = self::generateNextNumber($companyId, (int) $issueDate->year);
            $payload['company_id'] = $companyId;

            /** @var self $rfq */
            $rfq = self::query()->create($payload);

            return $rfq;
        });
    }

    public function applyStatusTransition(string $toStatus): array
    {
        $normalized = strtolower(trim($toStatus));
        if (! in_array($normalized, self::statuses(), true)) {
            throw new DomainException('Invalid RFQ status.');
        }

        return ['status' => $normalized];
    }
}

