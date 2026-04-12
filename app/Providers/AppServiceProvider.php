<?php

namespace App\Providers;

use App\Models\Company;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Invitation;
use App\Models\PaymentMethod;
use App\Models\PaymentTerm;
use App\Models\Unit;
use App\Models\User;
use App\Policies\CategoryPolicy;
use App\Policies\BrandPolicy;
use App\Policies\CompanyUserPolicy;
use App\Policies\CompanyPolicy;
use App\Policies\InvitationPolicy;
use App\Policies\PaymentMethodPolicy;
use App\Policies\PaymentTermPolicy;
use App\Policies\UnitPolicy;
use App\Support\CurrentCompany;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(CurrentCompany::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Company::class, CompanyPolicy::class);
        Gate::policy(Brand::class, BrandPolicy::class);
        Gate::policy(Category::class, CategoryPolicy::class);
        Gate::policy(Invitation::class, InvitationPolicy::class);
        Gate::policy(PaymentMethod::class, PaymentMethodPolicy::class);
        Gate::policy(PaymentTerm::class, PaymentTermPolicy::class);
        Gate::policy(Unit::class, UnitPolicy::class);
        Gate::policy(User::class, CompanyUserPolicy::class);

        RateLimiter::for('superadmin-invitations', function (Request $request) {
            $key = $request->user()?->id
                ? 'user:'.$request->user()->id
                : 'ip:'.$request->ip();

            return Limit::perMinute(15)->by($key);
        });

        RateLimiter::for('company-user-invitations', function (Request $request) {
            $key = $request->user()?->id
                ? 'company-user:'.$request->user()->id
                : 'ip:'.$request->ip();

            return Limit::perMinute(10)->by($key);
        });

        $this->applyPlatformMailBranding();
    }

    private function applyPlatformMailBranding(): void
    {
        $mailer = setting('mail.mailer');

        if (is_string($mailer) && $mailer !== '') {
            config(['mail.default' => $mailer]);
        }

        $smtpHost = setting('mail.host');

        if (is_string($smtpHost) && $smtpHost !== '') {
            config(['mail.mailers.smtp.host' => $smtpHost]);
        }

        $smtpPort = setting('mail.port');

        if ($smtpPort !== null && $smtpPort !== '' && is_numeric($smtpPort) && (int) $smtpPort > 0) {
            config(['mail.mailers.smtp.port' => (int) $smtpPort]);
        }

        $smtpUsername = setting('mail.username');

        if (is_string($smtpUsername) && $smtpUsername !== '') {
            config(['mail.mailers.smtp.username' => $smtpUsername]);
        }

        $smtpPassword = setting('mail.password');

        if (is_string($smtpPassword) && $smtpPassword !== '') {
            $resolvedPassword = $this->decryptMailPassword($smtpPassword);

            if ($resolvedPassword !== null) {
                config(['mail.mailers.smtp.password' => $resolvedPassword]);
            }
        }

        $smtpEncryption = setting('mail.encryption');

        if ($smtpEncryption === 'null') {
            config(['mail.mailers.smtp.encryption' => null]);
        } elseif (is_string($smtpEncryption) && $smtpEncryption !== '') {
            config(['mail.mailers.smtp.encryption' => $smtpEncryption]);
        }

        $fromAddress = setting('mail.from_address');
        $fromName = setting('mail.from_name');

        if (is_string($fromAddress) && $fromAddress !== '') {
            config(['mail.from.address' => $fromAddress]);
        }

        if (is_string($fromName) && $fromName !== '') {
            config(['mail.from.name' => $fromName]);
        }

        $replyTo = setting('mail.reply_to');

        if (is_string($replyTo) && $replyTo !== '') {
            config([
                'mail.reply_to' => [
                    'address' => $replyTo,
                    'name' => config('mail.from.name'),
                ],
            ]);
        }

        $appName = setting('app.name');

        if (is_string($appName) && $appName !== '') {
            config(['app.name' => $appName]);
        }
    }

    private function decryptMailPassword(string $value): ?string
    {
        try {
            return Crypt::decryptString($value);
        } catch (Throwable $exception) {
            Log::warning('Failed to decrypt SMTP password from settings, using .env fallback', [
                'context' => 'mail_runtime_config',
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }
}
