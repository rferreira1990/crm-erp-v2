<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SupplierQuoteRequestSupplier extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_SENT = 'sent';
    public const STATUS_RESPONDED = 'responded';
    public const STATUS_DECLINED = 'declined';
    public const STATUS_NO_RESPONSE = 'no_response';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'supplier_quote_request_id',
        'supplier_id',
        'status',
        'sent_to_email',
        'sent_at',
        'email_subject',
        'email_message',
        'responded_at',
        'declined_at',
        'supplier_name',
        'supplier_email',
        'pdf_path',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'responded_at' => 'datetime',
            'declined_at' => 'datetime',
        ];
    }

    public function supplierQuoteRequest(): BelongsTo
    {
        return $this->belongsTo(SupplierQuoteRequest::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function supplierQuote(): HasOne
    {
        return $this->hasOne(SupplierQuote::class, 'supplier_quote_request_supplier_id');
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
            self::STATUS_RESPONDED,
            self::STATUS_DECLINED,
            self::STATUS_NO_RESPONSE,
        ];
    }
}

