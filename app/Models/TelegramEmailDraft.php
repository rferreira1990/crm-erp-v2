<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelegramEmailDraft extends Model
{
    use HasFactory;

    public const STATUS_COLLECTING_RECIPIENT = 'collecting_recipient';
    public const STATUS_COLLECTING_SUBJECT = 'collecting_subject';
    public const STATUS_COLLECTING_BODY = 'collecting_body';
    public const STATUS_COLLECTING_ATTACHMENTS = 'collecting_attachments';
    public const STATUS_AI_IMPROVEMENT_OFFER = 'ai_improvement_offer';
    public const STATUS_AI_IMPROVEMENT_PREVIEW = 'ai_improvement_preview';
    public const STATUS_AWAITING_FINAL_APPROVAL = 'awaiting_final_approval';
    public const STATUS_SENT = 'sent';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_EXPIRED = 'expired';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'user_id',
        'telegram_user_id',
        'telegram_chat_id',
        'status',
        'to_email',
        'subject',
        'original_body',
        'improved_body',
        'selected_body',
        'attachments',
        'expires_at',
        'sent_at',
        'cancelled_at',
        'metadata',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'telegram_user_id' => 'integer',
            'attachments' => 'array',
            'expires_at' => 'datetime',
            'sent_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->whereNotIn('status', [
                self::STATUS_SENT,
                self::STATUS_CANCELLED,
                self::STATUS_EXPIRED,
            ])
            ->where('expires_at', '>', now());
    }
}

