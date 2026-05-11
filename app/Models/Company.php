<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'address',
        'locality',
        'city',
        'postal_code',
        'nif',
        'mobile',
        'email',
        'phone',
        'website',
        'logo_path',
        'bank_name',
        'iban',
        'bic_swift',
        'mail_use_custom_settings',
        'mail_from_name',
        'mail_from_address',
        'mail_host',
        'mail_port',
        'mail_username',
        'mail_password',
        'mail_encryption',
        'mail_signature_html',
        'pdf_layout',
        'ai_monthly_budget_eur',
        'ai_budget_warning_percent',
        'ai_budget_hard_stop_enabled',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'mail_use_custom_settings' => 'boolean',
            'mail_password' => 'encrypted',
            'ai_monthly_budget_eur' => 'decimal:2',
            'ai_budget_warning_percent' => 'integer',
            'ai_budget_hard_stop_enabled' => 'boolean',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class);
    }

    public function aiUsageLogs(): HasMany
    {
        return $this->hasMany(AiUsageLog::class);
    }

    public function telegramUserLinks(): HasMany
    {
        return $this->hasMany(TelegramUserLink::class);
    }

    public function telegramPendingSelections(): HasMany
    {
        return $this->hasMany(TelegramPendingSelection::class);
    }

    public function telegramEmailDrafts(): HasMany
    {
        return $this->hasMany(TelegramEmailDraft::class);
    }

    public function calendarEvents(): HasMany
    {
        return $this->hasMany(CalendarEvent::class);
    }

    public function calendarIntegrations(): HasMany
    {
        return $this->hasMany(CompanyCalendarIntegration::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
