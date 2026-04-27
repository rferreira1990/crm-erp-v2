<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SalesDocumentReceipt extends Model
{
    use HasFactory;

    public const STATUS_ISSUED = 'issued';
    public const STATUS_CANCELLED = 'cancelled';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'number',
        'sales_document_id',
        'customer_id',
        'receipt_date',
        'payment_method_id',
        'amount',
        'notes',
        'status',
        'issued_at',
        'cancelled_at',
        'created_by',
        'cancelled_by',
        'pdf_path',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'receipt_date' => 'date',
            'amount' => 'decimal:2',
            'issued_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function salesDocument(): BelongsTo
    {
        return $this->belongsTo(SalesDocument::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeIssued(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ISSUED);
    }

    /**
     * @return list<string>
     */
    public static function statuses(): array
    {
        return [
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
            self::STATUS_ISSUED => 'badge-phoenix-success',
            self::STATUS_CANCELLED => 'badge-phoenix-danger',
            default => 'badge-phoenix-secondary',
        };
    }

    public function isIssued(): bool
    {
        return $this->status === self::STATUS_ISSUED;
    }

    public function canCancel(): bool
    {
        return $this->status === self::STATUS_ISSUED;
    }

    public static function generateNextNumber(int $companyId, int $year): string
    {
        $sequence = SalesDocumentReceiptNumberSequence::query()
            ->where('company_id', $companyId)
            ->where('year', $year)
            ->lockForUpdate()
            ->first();

        if (! $sequence) {
            $sequence = SalesDocumentReceiptNumberSequence::query()->create([
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
            $receiptDate = isset($payload['receipt_date'])
                ? Carbon::parse((string) $payload['receipt_date'])
                : Carbon::now();

            $payload['number'] = self::generateNextNumber($companyId, (int) $receiptDate->year);
            $payload['company_id'] = $companyId;

            /** @var self $receipt */
            $receipt = self::query()->create($payload);

            return $receipt;
        });
    }
}
