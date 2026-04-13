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

class Quote extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_SENT = 'sent';
    public const STATUS_VIEWED = 'viewed';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CANCELLED = 'cancelled';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'number',
        'version',
        'status',
        'title',
        'subject',
        'customer_id',
        'customer_contact_id',
        'issue_date',
        'valid_until',
        'sent_at',
        'accepted_at',
        'rejected_at',
        'price_tier_id',
        'payment_term_id',
        'payment_method_id',
        'currency',
        'default_vat_rate_id',
        'subtotal',
        'discount_total',
        'tax_total',
        'grand_total',
        'header_notes',
        'footer_notes',
        'internal_notes',
        'customer_message',
        'print_comments',
        'assigned_user_id',
        'follow_up_date',
        'last_sent_at',
        'last_viewed_at',
        'is_locked',
        'pdf_path',
        'email_last_sent_to',
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
            'valid_until' => 'date',
            'sent_at' => 'datetime',
            'accepted_at' => 'datetime',
            'rejected_at' => 'datetime',
            'follow_up_date' => 'date',
            'last_sent_at' => 'datetime',
            'last_viewed_at' => 'datetime',
            'email_last_sent_at' => 'datetime',
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
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

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function customerContact(): BelongsTo
    {
        return $this->belongsTo(CustomerContact::class, 'customer_contact_id');
    }

    public function priceTier(): BelongsTo
    {
        return $this->belongsTo(PriceTier::class);
    }

    public function paymentTerm(): BelongsTo
    {
        return $this->belongsTo(PaymentTerm::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function defaultVatRate(): BelongsTo
    {
        return $this->belongsTo(VatRate::class, 'default_vat_rate_id');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuoteItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(QuoteStatusLog::class)->orderByDesc('created_at')->orderByDesc('id');
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
            self::STATUS_VIEWED,
            self::STATUS_APPROVED,
            self::STATUS_REJECTED,
            self::STATUS_EXPIRED,
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
            self::STATUS_VIEWED => 'Visto',
            self::STATUS_APPROVED => 'Aprovado',
            self::STATUS_REJECTED => 'Rejeitado',
            self::STATUS_EXPIRED => 'Expirado',
            self::STATUS_CANCELLED => 'Cancelado',
        ];
    }

    public function statusLabel(): string
    {
        return self::statusLabels()[$this->status] ?? $this->status;
    }

    public function isEditable(): bool
    {
        return ! $this->is_locked && $this->status === self::STATUS_DRAFT;
    }

    public function isFinalStatus(): bool
    {
        return in_array($this->status, [
            self::STATUS_APPROVED,
            self::STATUS_REJECTED,
            self::STATUS_EXPIRED,
            self::STATUS_CANCELLED,
        ], true);
    }

    public function canTransitionTo(string $toStatus): bool
    {
        $toStatus = strtolower(trim($toStatus));

        if (! in_array($toStatus, self::statuses(), true) || $toStatus === $this->status) {
            return false;
        }

        return match ($this->status) {
            self::STATUS_DRAFT => in_array($toStatus, [self::STATUS_SENT, self::STATUS_CANCELLED], true),
            self::STATUS_SENT => in_array($toStatus, [
                self::STATUS_DRAFT,
                self::STATUS_VIEWED,
                self::STATUS_APPROVED,
                self::STATUS_REJECTED,
                self::STATUS_EXPIRED,
                self::STATUS_CANCELLED,
            ], true),
            self::STATUS_VIEWED => in_array($toStatus, [
                self::STATUS_DRAFT,
                self::STATUS_APPROVED,
                self::STATUS_REJECTED,
                self::STATUS_EXPIRED,
                self::STATUS_CANCELLED,
            ], true),
            default => false,
        };
    }

    public function applyStatusTransition(string $toStatus): array
    {
        $toStatus = strtolower(trim($toStatus));
        if (! $this->canTransitionTo($toStatus)) {
            throw new DomainException('Invalid quote status transition.');
        }

        $payload = [
            'status' => $toStatus,
            'is_locked' => in_array($toStatus, [
                self::STATUS_APPROVED,
                self::STATUS_REJECTED,
                self::STATUS_EXPIRED,
                self::STATUS_CANCELLED,
            ], true),
        ];

        if ($toStatus === self::STATUS_SENT) {
            $payload['sent_at'] = Carbon::now();
            $payload['last_sent_at'] = Carbon::now();
        }

        if ($toStatus === self::STATUS_APPROVED) {
            $payload['accepted_at'] = Carbon::now();
        }

        if ($toStatus === self::STATUS_REJECTED) {
            $payload['rejected_at'] = Carbon::now();
        }

        if ($toStatus === self::STATUS_DRAFT) {
            $payload['is_locked'] = false;
        }

        return $payload;
    }

    public function recalculateTotals(): void
    {
        $totals = $this->items()
            ->selectRaw('COALESCE(SUM(subtotal), 0) as subtotal_sum')
            ->selectRaw('COALESCE(SUM(discount_amount), 0) as discount_sum')
            ->selectRaw('COALESCE(SUM(tax_amount), 0) as tax_sum')
            ->selectRaw('COALESCE(SUM(total), 0) as total_sum')
            ->first();

        $this->forceFill([
            'subtotal' => round((float) ($totals?->subtotal_sum ?? 0), 2),
            'discount_total' => round((float) ($totals?->discount_sum ?? 0), 2),
            'tax_total' => round((float) ($totals?->tax_sum ?? 0), 2),
            'grand_total' => round((float) ($totals?->total_sum ?? 0), 2),
        ])->save();
    }

    public function addStatusLog(string $toStatus, ?string $fromStatus = null, ?string $message = null, ?int $performedBy = null): void
    {
        $this->statusLogs()->create([
            'company_id' => $this->company_id,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'message' => $message,
            'performed_by' => $performedBy,
        ]);
    }

    public static function generateNextNumber(int $companyId, int $year): string
    {
        $sequence = QuoteNumberSequence::query()
            ->where('company_id', $companyId)
            ->where('year', $year)
            ->lockForUpdate()
            ->first();

        if (! $sequence) {
            $sequence = QuoteNumberSequence::query()->create([
                'company_id' => $companyId,
                'year' => $year,
                'last_number' => 0,
            ]);
        }

        $next = ((int) $sequence->last_number) + 1;
        $sequence->forceFill(['last_number' => $next])->save();

        return sprintf('ORC-%d-%04d', $year, $next);
    }

    public static function createWithGeneratedNumber(int $companyId, array $payload): self
    {
        return DB::transaction(function () use ($companyId, $payload): self {
            $issueDate = isset($payload['issue_date']) ? Carbon::parse((string) $payload['issue_date']) : Carbon::now();
            $payload['number'] = self::generateNextNumber($companyId, (int) $issueDate->year);
            $payload['company_id'] = $companyId;

            /** @var self $quote */
            $quote = self::query()->create($payload);

            return $quote;
        });
    }

    public static function defaultPaymentMethodIdForCompany(int $companyId): ?int
    {
        return PaymentMethod::query()
            ->visibleToCompany($companyId)
            ->orderByRaw('CASE WHEN company_id = ? THEN 0 ELSE 1 END', [$companyId])
            ->orderBy('name')
            ->value('id');
    }

    public static function defaultVatRateIdForCompany(int $companyId): ?int
    {
        /** @var VatRate|null $vat */
        $vat = VatRate::query()
            ->with([
                'companyOverrides' => fn ($query) => $query->where('company_id', $companyId),
            ])
            ->visibleToCompany($companyId)
            ->get(['id', 'region', 'name', 'rate', 'is_exempt'])
            ->filter(fn (VatRate $rate): bool => $rate->isEnabledForCompany($companyId))
            ->sortBy([
                fn (VatRate $rate) => $rate->region === VatRate::REGION_MAINLAND ? 0 : 1,
                fn (VatRate $rate) => $rate->is_exempt ? 1 : 0,
                fn (VatRate $rate) => -1 * (float) $rate->rate,
                fn (VatRate $rate) => $rate->name,
            ])
            ->first();

        return $vat?->id;
    }

    public function setCurrencyAttribute(?string $value): void
    {
        $normalized = $value !== null ? strtoupper(trim($value)) : 'EUR';
        $this->attributes['currency'] = $normalized !== '' ? $normalized : 'EUR';
    }

    public function setTitleAttribute(?string $value): void
    {
        $this->attributes['title'] = $this->normalizeNullableString($value);
    }

    public function setSubjectAttribute(?string $value): void
    {
        $this->attributes['subject'] = $this->normalizeNullableString($value);
    }

    public function setHeaderNotesAttribute(?string $value): void
    {
        $this->attributes['header_notes'] = $this->normalizeNullableString($value);
    }

    public function setFooterNotesAttribute(?string $value): void
    {
        $this->attributes['footer_notes'] = $this->normalizeNullableString($value);
    }

    public function setInternalNotesAttribute(?string $value): void
    {
        $this->attributes['internal_notes'] = $this->normalizeNullableString($value);
    }

    public function setCustomerMessageAttribute(?string $value): void
    {
        $this->attributes['customer_message'] = $this->normalizeNullableString($value);
    }

    public function setPrintCommentsAttribute(?string $value): void
    {
        $this->attributes['print_comments'] = $this->normalizeNullableString($value);
    }

    public function setEmailLastSentToAttribute(?string $value): void
    {
        $normalized = $this->normalizeNullableString($value);
        $this->attributes['email_last_sent_to'] = $normalized !== null ? strtolower($normalized) : null;
    }

    private function normalizeNullableString(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim($value);

        return $normalized !== '' ? $normalized : null;
    }
}

