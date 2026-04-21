<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PurchaseOrder extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_SENT = 'sent';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_PARTIALLY_RECEIVED = 'partially_received';
    public const STATUS_RECEIVED = 'received';
    public const STATUS_CANCELLED = 'cancelled';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'number',
        'status',
        'supplier_quote_request_id',
        'supplier_quote_award_id',
        'supplier_id',
        'supplier_name_snapshot',
        'supplier_email_snapshot',
        'supplier_phone_snapshot',
        'supplier_address_snapshot',
        'issue_date',
        'expected_delivery_date',
        'sent_at',
        'currency',
        'subtotal',
        'discount_total',
        'shipping_total',
        'tax_total',
        'grand_total',
        'internal_notes',
        'supplier_notes',
        'created_by',
        'assigned_user_id',
        'pdf_path',
        'email_last_sent_to',
        'email_last_sent_at',
        'is_locked',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'expected_delivery_date' => 'date',
            'sent_at' => 'datetime',
            'email_last_sent_at' => 'datetime',
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'shipping_total' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'is_locked' => 'boolean',
            'is_active' => 'boolean',
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

    public function rfq(): BelongsTo
    {
        return $this->belongsTo(SupplierQuoteRequest::class, 'supplier_quote_request_id');
    }

    public function award(): BelongsTo
    {
        return $this->belongsTo(SupplierQuoteAward::class, 'supplier_quote_award_id');
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
        return $this->hasMany(PurchaseOrderItem::class)->orderBy('line_order')->orderBy('id');
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
            self::STATUS_CONFIRMED,
            self::STATUS_PARTIALLY_RECEIVED,
            self::STATUS_RECEIVED,
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
            self::STATUS_SENT => 'Enviada',
            self::STATUS_CONFIRMED => 'Confirmada',
            self::STATUS_PARTIALLY_RECEIVED => 'Parcialmente recebida',
            self::STATUS_RECEIVED => 'Recebida',
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
            self::STATUS_SENT, self::STATUS_CONFIRMED => 'badge-phoenix-info',
            self::STATUS_PARTIALLY_RECEIVED, self::STATUS_RECEIVED => 'badge-phoenix-success',
            self::STATUS_CANCELLED => 'badge-phoenix-danger',
            default => 'badge-phoenix-secondary',
        };
    }

    public function isEditable(): bool
    {
        return ! $this->is_locked && $this->status === self::STATUS_DRAFT;
    }

    public static function generateNextNumber(int $companyId, int $year): string
    {
        $sequence = PurchaseOrderNumberSequence::query()
            ->where('company_id', $companyId)
            ->where('year', $year)
            ->lockForUpdate()
            ->first();

        if (! $sequence) {
            $sequence = PurchaseOrderNumberSequence::query()->create([
                'company_id' => $companyId,
                'year' => $year,
                'last_number' => 0,
            ]);
        }

        $next = ((int) $sequence->last_number) + 1;
        $sequence->forceFill(['last_number' => $next])->save();

        return sprintf('ECF-%d-%04d', $year, $next);
    }

    public static function createWithGeneratedNumber(int $companyId, array $payload): self
    {
        return DB::transaction(function () use ($companyId, $payload): self {
            $issueDate = isset($payload['issue_date']) ? Carbon::parse((string) $payload['issue_date']) : Carbon::now();
            $payload['number'] = self::generateNextNumber($companyId, (int) $issueDate->year);
            $payload['company_id'] = $companyId;

            /** @var self $purchaseOrder */
            $purchaseOrder = self::query()->create($payload);

            return $purchaseOrder;
        });
    }
}

