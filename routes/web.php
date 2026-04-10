<?php

use App\Http\Controllers\SuperAdmin\CompanyController;
use App\Http\Controllers\SuperAdmin\EmailSettingsController;
use App\Http\Controllers\InvitationAcceptanceController;
use App\Http\Controllers\Admin\CompanyUserController;
use App\Http\Controllers\Admin\CompanyUserInvitationController;
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
