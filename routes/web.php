<?php

use App\Http\Controllers\SuperAdmin\CompanyController;
use App\Http\Controllers\SuperAdmin\EmailSettingsController;
use App\Http\Controllers\InvitationAcceptanceController;
use App\Http\Controllers\Admin\CompanyUserController;
use App\Http\Controllers\Admin\CompanyUserInvitationController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\BrandController;
use App\Http\Controllers\Admin\PaymentMethodController;
use App\Http\Controllers\Admin\PaymentTermController;
use App\Http\Controllers\Admin\UnitController;
use App\Http\Controllers\Admin\VatExemptionReasonController;
use App\Http\Controllers\Admin\VatRateController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SuperAdmin\SuperAdminInvitationController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (! auth()->check()) {
        if (Route::has('login')) {
            return redirect()->route('login');
        }

        return view('welcome');
    }

    return auth()->user()?->isSuperAdmin()
        ? redirect()->route('superadmin.companies.index')
        : redirect()->route('admin.dashboard');
});

Route::get('/dashboard', function () {
    return auth()->user()?->isSuperAdmin()
        ? redirect()->route('superadmin.companies.index')
        : redirect()->route('admin.dashboard');
})->middleware(['auth'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['no.cache'])->group(function () {
    Route::get('/invitations/accept', [InvitationAcceptanceController::class, 'create'])
        ->name('invitations.accept.create');
    Route::post('/invitations/accept', [InvitationAcceptanceController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('invitations.accept.store');
});

Route::middleware(['auth', 'company.context', 'not.superadmin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/dashboard', function () {
            return view('admin.dashboard.index');
        })->name('dashboard');

        Route::get('/dashboard/version-old', function () {
            return view('admin.dashboard.version_old');
        })->name('dashboard.version_old');

        Route::get('/users', [CompanyUserController::class, 'index'])->name('users.index');
        Route::patch('/users/{companyUser}', [CompanyUserController::class, 'update'])
            ->whereNumber('companyUser')
            ->name('users.update');
        Route::patch('/users/{companyUser}/toggle-active', [CompanyUserController::class, 'toggleActive'])
            ->whereNumber('companyUser')
            ->name('users.toggle-active');

        Route::get('/user-invitations/create', [CompanyUserInvitationController::class, 'create'])->name('user-invitations.create');
        Route::post('/user-invitations', [CompanyUserInvitationController::class, 'store'])
            ->middleware('throttle:company-user-invitations')
            ->name('user-invitations.store');
        Route::delete('/user-invitations/{invitation}', [CompanyUserInvitationController::class, 'destroy'])
            ->whereNumber('invitation')
            ->name('user-invitations.destroy');

        Route::get('/units', [UnitController::class, 'index'])->name('units.index');
        Route::get('/units/create', [UnitController::class, 'create'])->name('units.create');
        Route::post('/units', [UnitController::class, 'store'])->name('units.store');
        Route::get('/units/{unit}/edit', [UnitController::class, 'edit'])
            ->whereNumber('unit')
            ->name('units.edit');
        Route::patch('/units/{unit}', [UnitController::class, 'update'])
            ->whereNumber('unit')
            ->name('units.update');
        Route::delete('/units/{unit}', [UnitController::class, 'destroy'])
            ->whereNumber('unit')
            ->name('units.destroy');

        Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
        Route::get('/categories/create', [CategoryController::class, 'create'])->name('categories.create');
        Route::post('/categories', [CategoryController::class, 'store'])->name('categories.store');
        Route::get('/categories/{category}/edit', [CategoryController::class, 'edit'])
            ->whereNumber('category')
            ->name('categories.edit');
        Route::patch('/categories/{category}', [CategoryController::class, 'update'])
            ->whereNumber('category')
            ->name('categories.update');
        Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])
            ->whereNumber('category')
            ->name('categories.destroy');

        Route::get('/brands', [BrandController::class, 'index'])->name('brands.index');
        Route::get('/brands/create', [BrandController::class, 'create'])->name('brands.create');
        Route::post('/brands', [BrandController::class, 'store'])->name('brands.store');
        Route::get('/brands/{brand}/edit', [BrandController::class, 'edit'])
            ->whereNumber('brand')
            ->name('brands.edit');
        Route::patch('/brands/{brand}', [BrandController::class, 'update'])
            ->whereNumber('brand')
            ->name('brands.update');
        Route::delete('/brands/{brand}', [BrandController::class, 'destroy'])
            ->whereNumber('brand')
            ->name('brands.destroy');
        Route::delete('/brands/{brand}/files/{brandFile}', [BrandController::class, 'destroyFile'])
            ->whereNumber('brand')
            ->whereNumber('brandFile')
            ->name('brands.files.destroy');

        Route::get('/payment-methods', [PaymentMethodController::class, 'index'])->name('payment-methods.index');
        Route::get('/payment-methods/create', [PaymentMethodController::class, 'create'])->name('payment-methods.create');
        Route::post('/payment-methods', [PaymentMethodController::class, 'store'])->name('payment-methods.store');
        Route::get('/payment-methods/{paymentMethod}/edit', [PaymentMethodController::class, 'edit'])
            ->whereNumber('paymentMethod')
            ->name('payment-methods.edit');
        Route::patch('/payment-methods/{paymentMethod}', [PaymentMethodController::class, 'update'])
            ->whereNumber('paymentMethod')
            ->name('payment-methods.update');
        Route::delete('/payment-methods/{paymentMethod}', [PaymentMethodController::class, 'destroy'])
            ->whereNumber('paymentMethod')
            ->name('payment-methods.destroy');

        Route::get('/payment-terms', [PaymentTermController::class, 'index'])->name('payment-terms.index');
        Route::get('/payment-terms/create', [PaymentTermController::class, 'create'])->name('payment-terms.create');
        Route::post('/payment-terms', [PaymentTermController::class, 'store'])->name('payment-terms.store');
        Route::get('/payment-terms/{paymentTerm}/edit', [PaymentTermController::class, 'edit'])
            ->whereNumber('paymentTerm')
            ->name('payment-terms.edit');
        Route::patch('/payment-terms/{paymentTerm}', [PaymentTermController::class, 'update'])
            ->whereNumber('paymentTerm')
            ->name('payment-terms.update');
        Route::delete('/payment-terms/{paymentTerm}', [PaymentTermController::class, 'destroy'])
            ->whereNumber('paymentTerm')
            ->name('payment-terms.destroy');
        Route::patch('/payment-terms/{paymentTerm}/deactivate-system', [PaymentTermController::class, 'deactivateSystemTerm'])
            ->whereNumber('paymentTerm')
            ->name('payment-terms.deactivate-system');
        Route::patch('/payment-terms/{paymentTerm}/reactivate-system', [PaymentTermController::class, 'reactivateSystemTerm'])
            ->whereNumber('paymentTerm')
            ->name('payment-terms.reactivate-system');

        Route::get('/vat-rates', [VatRateController::class, 'index'])->name('vat-rates.index');
        Route::get('/vat-rates/create', [VatRateController::class, 'create'])->name('vat-rates.create');
        Route::post('/vat-rates', [VatRateController::class, 'store'])->name('vat-rates.store');
        Route::get('/vat-rates/{vatRate}/edit', [VatRateController::class, 'edit'])
            ->whereNumber('vatRate')
            ->name('vat-rates.edit');
        Route::patch('/vat-rates/{vatRate}', [VatRateController::class, 'update'])
            ->whereNumber('vatRate')
            ->name('vat-rates.update');
        Route::delete('/vat-rates/{vatRate}', [VatRateController::class, 'destroy'])
            ->whereNumber('vatRate')
            ->name('vat-rates.destroy');

        Route::get('/vat-exemption-reasons', [VatExemptionReasonController::class, 'index'])->name('vat-exemption-reasons.index');
        Route::get('/vat-exemption-reasons/create', [VatExemptionReasonController::class, 'create'])->name('vat-exemption-reasons.create');
        Route::post('/vat-exemption-reasons', [VatExemptionReasonController::class, 'store'])->name('vat-exemption-reasons.store');
        Route::get('/vat-exemption-reasons/{vatExemptionReason}/edit', [VatExemptionReasonController::class, 'edit'])
            ->whereNumber('vatExemptionReason')
            ->name('vat-exemption-reasons.edit');
        Route::patch('/vat-exemption-reasons/{vatExemptionReason}', [VatExemptionReasonController::class, 'update'])
            ->whereNumber('vatExemptionReason')
            ->name('vat-exemption-reasons.update');
        Route::delete('/vat-exemption-reasons/{vatExemptionReason}', [VatExemptionReasonController::class, 'destroy'])
            ->whereNumber('vatExemptionReason')
            ->name('vat-exemption-reasons.destroy');
    });

Route::middleware(['auth', 'superadmin.only'])
    ->prefix('superadmin')
    ->name('superadmin.')
    ->group(function () {
        Route::get('/', function () {
            return redirect()->route('superadmin.companies.index');
        })->name('home');

        Route::patch('/companies/{company}/toggle-active', [CompanyController::class, 'toggleActive'])
            ->name('companies.toggle-active');
        Route::resource('companies', CompanyController::class)->except(['show', 'destroy']);

        Route::get('/invitations', [SuperAdminInvitationController::class, 'index'])
            ->name('invitations.index');
        Route::get('/invitations/create', [SuperAdminInvitationController::class, 'create'])
            ->name('invitations.create');
        Route::post('/invitations', [SuperAdminInvitationController::class, 'store'])
            ->middleware('throttle:superadmin-invitations')
            ->name('invitations.store');
        Route::delete('/invitations/{invitation}', [SuperAdminInvitationController::class, 'destroy'])
            ->name('invitations.destroy');

        Route::get('/settings/email', [EmailSettingsController::class, 'edit'])
            ->name('settings.email.edit');
        Route::put('/settings/email', [EmailSettingsController::class, 'update'])
            ->name('settings.email.update');
        Route::post('/settings/email/test-smtp', [EmailSettingsController::class, 'sendTestSmtp'])
            ->middleware('throttle:6,1')
            ->name('settings.email.test-smtp');
        Route::post('/settings/email/reset', [EmailSettingsController::class, 'reset'])
            ->middleware('throttle:3,1')
            ->name('settings.email.reset');
    });

require __DIR__.'/auth.php';
