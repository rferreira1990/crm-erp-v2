<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class EmailMessage extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'email_account_id',
        'message_uid',
        'message_id',
        'folder',
        'from_email',
        'from_name',
        'to_email',
        'to_name',
        'subject',
        'snippet',
        'body_text',
        'body_html',
        'received_at',
        'is_seen',
        'has_attachments',
        'raw_headers',
        'synced_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'received_at' => 'datetime',
            'is_seen' => 'boolean',
            'has_attachments' => 'boolean',
            'raw_headers' => 'array',
            'synced_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(EmailAccount::class, 'email_account_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(EmailMessageAttachment::class)->orderBy('id');
    }

    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }

    public function senderLabel(): string
    {
        if (is_string($this->from_name) && trim($this->from_name) !== '') {
            return $this->from_name;
        }

        return $this->from_email ?: '-';
    }

    public function subjectLabel(): string
    {
        return $this->subject ?: '(Sem assunto)';
    }

    public function snippetLabel(): string
    {
        if (is_string($this->snippet) && trim($this->snippet) !== '') {
            return $this->snippet;
        }

        if (is_string($this->body_text) && trim($this->body_text) !== '') {
            return Str::limit(preg_replace('/\s+/', ' ', trim($this->body_text)) ?: '', 220);
        }

        return '';
    }
}

