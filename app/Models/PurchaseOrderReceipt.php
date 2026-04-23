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

class PurchaseOrderReceipt extends Model
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
        'purchase_order_id',
        'number',
        'status',
        'receipt_date',
        'supplier_document_number',
        'supplier_document_date',
        'notes',
        'internal_notes',
        'received_by',
        'pdf_path',
        'is_final',
        'stock_posted_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'receipt_date' => 'date',
            'supplier_document_date' => 'date',
            'is_final' => 'boolean',
            'stock_posted_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderReceiptItem::class)
            ->orderBy('line_order')
            ->orderBy('id');
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'reference_id')
            ->where('reference_type', StockMovement::REFERENCE_PURCHASE_ORDER_RECEIPT)
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
            self::STATUS_POSTED => 'Fechada',
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

    public function hasStockMovements(): bool
    {
        if ($this->relationLoaded('stockMovements')) {
            return $this->stockMovements->isNotEmpty();
        }

        return $this->stockMovements()->exists();
    }

    /**
     * @return array<string, mixed>
     */
    public function applyStatusTransition(string $toStatus): array
    {
        $normalized = strtolower(trim($toStatus));

        if ($normalized === $this->status) {
            throw new DomainException('Invalid purchase order receipt status transition.');
        }

        return match ($this->status) {
            self::STATUS_DRAFT => match ($normalized) {
                self::STATUS_POSTED, self::STATUS_CANCELLED => ['status' => $normalized],
                default => throw new DomainException('Invalid purchase order receipt status transition.'),
            },
            default => throw new DomainException('Invalid purchase order receipt status transition.'),
        };
    }

    public static function generateNextNumber(int $companyId, int $year): string
    {
        $sequence = PurchaseOrderReceiptNumberSequence::query()
            ->where('company_id', $companyId)
            ->where('year', $year)
            ->lockForUpdate()
            ->first();

        if (! $sequence) {
            $sequence = PurchaseOrderReceiptNumberSequence::query()->create([
                'company_id' => $companyId,
                'year' => $year,
                'last_number' => 0,
            ]);
        }

        $next = ((int) $sequence->last_number) + 1;
        $sequence->forceFill(['last_number' => $next])->save();

        return sprintf('REC-%d-%04d', $year, $next);
    }

    public static function createWithGeneratedNumber(int $companyId, array $payload): self
    {
        return DB::transaction(function () use ($companyId, $payload): self {
            $receiptDate = isset($payload['receipt_date']) ? Carbon::parse((string) $payload['receipt_date']) : Carbon::now();
            $payload['number'] = self::generateNextNumber($companyId, (int) $receiptDate->year);
            $payload['company_id'] = $companyId;

            /** @var self $receipt */
            $receipt = self::query()->create($payload);

            return $receipt;
        });
    }
}
