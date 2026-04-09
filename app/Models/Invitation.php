<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Invitation extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'invited_by',
        'email',
        'role',
        'token',
        'expires_at',
        'accepted_at',
        'cancelled_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query
            ->whereNull('accepted_at')
            ->whereNull('cancelled_at')
            ->whereNotNull('expires_at')
            ->where('expires_at', '>', now());
    }

    public function isPending(): bool
    {
        return ! $this->isAccepted()
            && ! $this->isCancelled()
            && ! $this->isExpired();
    }

    public function isAccepted(): bool
    {
        return $this->accepted_at !== null;
    }

    public function isExpired(): bool
    {
        if ($this->expires_at === null) {
            return true;
        }

        return $this->expires_at->isPast();
    }

    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }

    public function markAsCancelled(): bool
    {
        if (! $this->isPending()) {
            return false;
        }

        return $this->forceFill([
            'cancelled_at' => now(),
        ])->save();
    }

    public function markAsAccepted(): bool
    {
        if (! $this->isPending()) {
            return false;
        }

        return $this->forceFill([
            'accepted_at' => now(),
        ])->save();
    }

    public function status(): string
    {
        if ($this->isAccepted()) {
            return 'accepted';
        }

        if ($this->isCancelled()) {
            return 'cancelled';
        }

        if ($this->isExpired()) {
            return 'expired';
        }

        return 'pending';
    }

    public static function normalizeEmail(string $email): string
    {
        return Str::lower(trim($email));
    }

    public static function hashToken(string $plainToken): string
    {
        return hash('sha256', trim($plainToken));
    }

    public function matchesToken(string $plainToken): bool
    {
        return hash_equals($this->token, self::hashToken($plainToken));
    }

    public function setEmailAttribute(?string $value): void
    {
        $this->attributes['email'] = $value !== null
            ? self::normalizeEmail($value)
            : null;
    }
}
