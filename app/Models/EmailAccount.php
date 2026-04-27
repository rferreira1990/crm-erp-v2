<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;
use Throwable;

class EmailAccount extends Model
{
    use HasFactory;

    public const ENCRYPTION_SSL = 'ssl';
    public const ENCRYPTION_TLS = 'tls';
    public const ENCRYPTION_NONE = 'none';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'name',
        'email',
        'imap_host',
        'imap_port',
        'imap_encryption',
        'imap_username',
        'imap_password_encrypted',
        'imap_folder',
        'is_active',
        'smtp_use_custom_settings',
        'smtp_from_name',
        'smtp_from_address',
        'smtp_host',
        'smtp_port',
        'smtp_encryption',
        'smtp_username',
        'smtp_password_encrypted',
        'last_synced_at',
        'last_error',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'imap_password_encrypted',
        'smtp_password_encrypted',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'imap_port' => 'integer',
            'is_active' => 'boolean',
            'smtp_use_custom_settings' => 'boolean',
            'smtp_port' => 'integer',
            'last_synced_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(EmailMessage::class)->orderByDesc('received_at')->orderByDesc('id');
    }

    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * @return list<string>
     */
    public static function encryptions(): array
    {
        return [
            self::ENCRYPTION_SSL,
            self::ENCRYPTION_TLS,
            self::ENCRYPTION_NONE,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function encryptionLabels(): array
    {
        return [
            self::ENCRYPTION_SSL => 'SSL',
            self::ENCRYPTION_TLS => 'TLS',
            self::ENCRYPTION_NONE => 'Sem encriptacao',
        ];
    }

    public function setImapPassword(string $password): void
    {
        $normalized = trim($password);
        if ($normalized === '') {
            return;
        }

        $this->forceFill([
            'imap_password_encrypted' => Crypt::encryptString($normalized),
        ]);
    }

    public function resolveImapPassword(): ?string
    {
        $encrypted = $this->imap_password_encrypted;
        if (! is_string($encrypted) || trim($encrypted) === '') {
            return null;
        }

        try {
            return Crypt::decryptString($encrypted);
        } catch (Throwable) {
            return null;
        }
    }

    public function setSmtpPassword(string $password): void
    {
        $normalized = trim($password);
        if ($normalized === '') {
            return;
        }

        $this->forceFill([
            'smtp_password_encrypted' => Crypt::encryptString($normalized),
        ]);
    }

    public function resolveSmtpPassword(): ?string
    {
        $encrypted = $this->smtp_password_encrypted;
        if (! is_string($encrypted) || trim($encrypted) === '') {
            return null;
        }

        try {
            return Crypt::decryptString($encrypted);
        } catch (Throwable) {
            return null;
        }
    }
}
