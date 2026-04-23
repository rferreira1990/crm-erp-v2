<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasRoles;

    public const ROLE_COMPANY_ADMIN = 'company_admin';
    public const ROLE_COMPANY_USER = 'company_user';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'invited_by',
        'name',
        'email',
        'password',
        'is_super_admin',
        'is_active',
        'hourly_cost',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_super_admin' => 'boolean',
            'is_active' => 'boolean',
            'hourly_cost' => 'decimal:2',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'invited_by');
    }

    public function invitedUsers(): HasMany
    {
        return $this->hasMany(self::class, 'invited_by');
    }

    public function sentInvitations(): HasMany
    {
        return $this->hasMany(Invitation::class, 'invited_by');
    }

    public function isSuperAdmin(): bool
    {
        return $this->is_super_admin;
    }

    public function isCompanyUser(): bool
    {
        return ! $this->is_super_admin && $this->company_id !== null;
    }

    public function belongsToCompany(Company|int|null $company): bool
    {
        if ($company === null || $this->company_id === null) {
            return false;
        }

        $companyId = $company instanceof Company ? $company->getKey() : $company;

        return (int) $this->company_id === (int) $companyId;
    }

    public function canManageCompanyUsers(): bool
    {
        return $this->isCompanyUser() && $this->hasRole(self::ROLE_COMPANY_ADMIN);
    }

    /**
     * @return list<string>
     */
    public static function companyRoleNames(): array
    {
        return [
            self::ROLE_COMPANY_ADMIN,
            self::ROLE_COMPANY_USER,
        ];
    }
}
