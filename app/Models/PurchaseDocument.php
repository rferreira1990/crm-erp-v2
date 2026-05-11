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

class PurchaseDocument extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_CANCELLED = 'cancelled';

    public const PAYMENT_STATUS_UNPAID = 'unpaid';
    public const PAYMENT_STATUS_PARTIAL = 'partial';
    public const PAYMENT_STATUS_PAID = 'paid';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'number',
        'supplier_document_number',
        'supplier_id',
        'purchase_order_id',
        'status',
        'payment_status',
        'issue_date',
        'due_date',
        'notes',
        'currency',
        'subtotal',
        'discount_total',
        'tax_total',
        'grand_total',
        'confirmed_at',
        'cancelled_at',
        'created_by',
        'updated_by',
        'cancelled_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'due_date' => 'date',
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
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

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseDocumentItem::class)->orderBy('line_order')->orderBy('id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SupplierPayment::class)
            ->orderByDesc('payment_date')
            ->orderByDesc('id');
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'reference_id')
            ->where('reference_type', StockMovement::REFERENCE_PURCHASE_DOCUMENT)
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
            self::STATUS_CONFIRMED,
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
            self::STATUS_CONFIRMED => 'Confirmado',
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
            self::STATUS_CONFIRMED => 'badge-phoenix-success',
            self::STATUS_CANCELLED => 'badge-phoenix-danger',
            default => 'badge-phoenix-secondary',
        };
    }

    /**
     * @return list<string>
     */
    public static function paymentStatuses(): array
    {
        return [
            self::PAYMENT_STATUS_UNPAID,
            self::PAYMENT_STATUS_PARTIAL,
            self::PAYMENT_STATUS_PAID,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function paymentStatusLabels(): array
    {
        return [
            self::PAYMENT_STATUS_UNPAID => 'Por pagar',
            self::PAYMENT_STATUS_PARTIAL => 'Parcial',
            self::PAYMENT_STATUS_PAID => 'Pago',
        ];
    }

    public function paymentStatusLabel(): string
    {
        $status = (string) ($this->payment_status ?: self::PAYMENT_STATUS_UNPAID);

        return self::paymentStatusLabels()[$status] ?? $status;
    }

    public function paymentStatusBadgeClass(): string
    {
        $status = (string) ($this->payment_status ?: self::PAYMENT_STATUS_UNPAID);

        return match ($status) {
            self::PAYMENT_STATUS_UNPAID => 'badge-phoenix-danger',
            self::PAYMENT_STATUS_PARTIAL => 'badge-phoenix-warning',
            self::PAYMENT_STATUS_PAID => 'badge-phoenix-success',
            default => 'badge-phoenix-secondary',
        };
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isConfirmed(): bool
    {
        return $this->status === self::STATUS_CONFIRMED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function isEditableDraft(): bool
    {
        return $this->isDraft();
    }

    public function canReceivePayments(): bool
    {
        return $this->status === self::STATUS_CONFIRMED;
    }

    public function issuedPaymentsTotal(): float
    {
        return round((float) $this->payments()
            ->where('status', SupplierPayment::STATUS_ISSUED)
            ->sum('amount'), 2);
    }

    public function openAmount(): float
    {
        $open = round((float) $this->grand_total - $this->issuedPaymentsTotal(), 2);

        return $open > 0 ? $open : 0.0;
    }

    public function hasStockMovements(): bool
    {
        return $this->stockMovements()->exists();
    }

    public function canTransitionTo(string $toStatus): bool
    {
        $target = strtolower(trim($toStatus));
        if (! in_array($target, self::statuses(), true) || $target === $this->status) {
            return false;
        }

        return match ($this->status) {
            self::STATUS_DRAFT => in_array($target, [self::STATUS_CONFIRMED, self::STATUS_CANCELLED], true),
            self::STATUS_CONFIRMED => in_array($target, [self::STATUS_CANCELLED], true),
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
            throw new DomainException('Invalid purchase document status transition.');
        }

        return match ($target) {
            self::STATUS_CONFIRMED => [
                'status' => self::STATUS_CONFIRMED,
                'confirmed_at' => now(),
            ],
            self::STATUS_CANCELLED => [
                'status' => self::STATUS_CANCELLED,
                'cancelled_at' => now(),
            ],
            default => throw new DomainException('Invalid purchase document status transition.'),
        };
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
        $sequence = PurchaseDocumentNumberSequence::query()
            ->where('company_id', $companyId)
            ->where('year', $year)
            ->lockForUpdate()
            ->first();

        if (! $sequence) {
            $sequence = PurchaseDocumentNumberSequence::query()->create([
                'company_id' => $companyId,
                'year' => $year,
                'last_number' => 0,
            ]);
        }

        $next = ((int) $sequence->last_number) + 1;
        $sequence->forceFill(['last_number' => $next])->save();

        return sprintf('DC-%d-%04d', $year, $next);
    }

    public static function createWithGeneratedNumber(int $companyId, array $payload): self
    {
        return DB::transaction(function () use ($companyId, $payload): self {
            $issueDate = isset($payload['issue_date']) ? Carbon::parse((string) $payload['issue_date']) : Carbon::now();
            $payload['number'] = self::generateNextNumber($companyId, (int) $issueDate->year);
            $payload['company_id'] = $companyId;

            /** @var self $purchaseDocument */
            $purchaseDocument = self::query()->create($payload);

            return $purchaseDocument;
        });
    }
}
