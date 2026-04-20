<?php

use App\Http\Controllers\SuperAdmin\CompanyController;
use App\Http\Controllers\SuperAdmin\EmailSettingsController;
use App\Http\Controllers\InvitationAcceptanceController;
use App\Http\Controllers\Admin\CompanyUserController;
use App\Http\Controllers\Admin\CompanyUserInvitationController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\BrandController;
use App\Http\Controllers\Admin\ArticleController;
use App\Http\Controllers\Admin\ArticleMediaController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\CustomerContactController;
use App\Http\Controllers\Admin\PaymentMethodController;
use App\Http\Controllers\Admin\PaymentTermController;
use App\Http\Controllers\Admin\PriceTierController;
use App\Http\Controllers\Admin\QuoteDashboardController;
use App\Http\Controllers\Admin\ProductFamilyController;
use App\Http\Controllers\Admin\QuoteController;
use App\Http\Controllers\Admin\SupplierController;
use App\Http\Controllers\Admin\SupplierContactController;
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

        Route::get('/product-families', [ProductFamilyController::class, 'index'])->name('product-families.index');
        Route::get('/product-families/create', [ProductFamilyController::class, 'create'])->name('product-families.create');
        Route::post('/product-families', [ProductFamilyController::class, 'store'])->name('product-families.store');
        Route::get('/product-families/{productFamily}/edit', [ProductFamilyController::class, 'edit'])
            ->whereNumber('productFamily')
            ->name('product-families.edit');
        Route::patch('/product-families/{productFamily}', [ProductFamilyController::class, 'update'])
            ->whereNumber('productFamily')
            ->name('product-families.update');
        Route::delete('/product-families/{productFamily}', [ProductFamilyController::class, 'destroy'])
            ->whereNumber('productFamily')
            ->name('product-families.destroy');

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

        Route::get('/articles', [ArticleController::class, 'index'])->name('articles.index');
        Route::get('/articles/create', [ArticleController::class, 'create'])->name('articles.create');
        Route::post('/articles', [ArticleController::class, 'store'])->name('articles.store');
        Route::get('/articles/{article}/edit', [ArticleController::class, 'edit'])
            ->whereNumber('article')
            ->name('articles.edit');
        Route::patch('/articles/{article}', [ArticleController::class, 'update'])
            ->whereNumber('article')
            ->name('articles.update');
        Route::delete('/articles/{article}', [ArticleController::class, 'destroy'])
            ->whereNumber('article')
            ->name('articles.destroy');
        Route::delete('/articles/{article}/images/{articleImage}', [ArticleController::class, 'destroyImage'])
            ->whereNumber('article')
            ->whereNumber('articleImage')
            ->name('articles.images.destroy');
        Route::get('/articles/{article}/images/{articleImage}', [ArticleMediaController::class, 'showImage'])
            ->whereNumber('article')
            ->whereNumber('articleImage')
            ->name('articles.images.show');
        Route::delete('/articles/{article}/files/{articleFile}', [ArticleController::class, 'destroyFile'])
            ->whereNumber('article')
            ->whereNumber('articleFile')
            ->name('articles.files.destroy');
        Route::get('/articles/{article}/files/{articleFile}/download', [ArticleMediaController::class, 'showFile'])
            ->whereNumber('article')
            ->whereNumber('articleFile')
            ->name('articles.files.download');

        Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
        Route::get('/customers/create', [CustomerController::class, 'create'])->name('customers.create');
        Route::post('/customers', [CustomerController::class, 'store'])->name('customers.store');
        Route::get('/customers/{customer}', [CustomerController::class, 'show'])
            ->whereNumber('customer')
            ->name('customers.show');
        Route::get('/customers/{customer}/edit', [CustomerController::class, 'edit'])
            ->whereNumber('customer')
            ->name('customers.edit');
        Route::patch('/customers/{customer}', [CustomerController::class, 'update'])
            ->whereNumber('customer')
            ->name('customers.update');
        Route::delete('/customers/{customer}', [CustomerController::class, 'destroy'])
            ->whereNumber('customer')
            ->name('customers.destroy');
        Route::get('/customers/{customer}/logo', [CustomerController::class, 'showLogo'])
            ->whereNumber('customer')
            ->name('customers.logo.show');
        Route::get('/customers/{customer}/contacts/create', [CustomerContactController::class, 'create'])
            ->whereNumber('customer')
            ->name('customers.contacts.create');
        Route::post('/customers/{customer}/contacts', [CustomerContactController::class, 'store'])
            ->whereNumber('customer')
            ->name('customers.contacts.store');
        Route::get('/customers/{customer}/contacts/{contact}/edit', [CustomerContactController::class, 'edit'])
            ->whereNumber('customer')
            ->whereNumber('contact')
            ->name('customers.contacts.edit');
        Route::patch('/customers/{customer}/contacts/{contact}', [CustomerContactController::class, 'update'])
            ->whereNumber('customer')
            ->whereNumber('contact')
            ->name('customers.contacts.update');
        Route::delete('/customers/{customer}/contacts/{contact}', [CustomerContactController::class, 'destroy'])
            ->whereNumber('customer')
            ->whereNumber('contact')
            ->name('customers.contacts.destroy');

        Route::get('/suppliers', [SupplierController::class, 'index'])->name('suppliers.index');
        Route::get('/suppliers/create', [SupplierController::class, 'create'])->name('suppliers.create');
        Route::post('/suppliers', [SupplierController::class, 'store'])->name('suppliers.store');
        Route::get('/suppliers/{supplier}', [SupplierController::class, 'show'])
            ->whereNumber('supplier')
            ->name('suppliers.show');
        Route::get('/suppliers/{supplier}/edit', [SupplierController::class, 'edit'])
            ->whereNumber('supplier')
            ->name('suppliers.edit');
        Route::patch('/suppliers/{supplier}', [SupplierController::class, 'update'])
            ->whereNumber('supplier')
            ->name('suppliers.update');
        Route::delete('/suppliers/{supplier}', [SupplierController::class, 'destroy'])
            ->whereNumber('supplier')
            ->name('suppliers.destroy');
        Route::get('/suppliers/{supplier}/logo', [SupplierController::class, 'showLogo'])
            ->whereNumber('supplier')
            ->name('suppliers.logo.show');
        Route::get('/suppliers/{supplier}/contacts/create', [SupplierContactController::class, 'create'])
            ->whereNumber('supplier')
            ->name('suppliers.contacts.create');
        Route::post('/suppliers/{supplier}/contacts', [SupplierContactController::class, 'store'])
            ->whereNumber('supplier')
            ->name('suppliers.contacts.store');
        Route::get('/suppliers/{supplier}/contacts/{contact}/edit', [SupplierContactController::class, 'edit'])
            ->whereNumber('supplier')
            ->whereNumber('contact')
            ->name('suppliers.contacts.edit');
        Route::patch('/suppliers/{supplier}/contacts/{contact}', [SupplierContactController::class, 'update'])
            ->whereNumber('supplier')
            ->whereNumber('contact')
            ->name('suppliers.contacts.update');
        Route::delete('/suppliers/{supplier}/contacts/{contact}', [SupplierContactController::class, 'destroy'])
            ->whereNumber('supplier')
            ->whereNumber('contact')
            ->name('suppliers.contacts.destroy');

        Route::get('/quotes', [QuoteController::class, 'index'])->name('quotes.index');
        Route::get('/quotes/dashboard', QuoteDashboardController::class)->name('quotes.dashboard');
        Route::get('/quotes/create', [QuoteController::class, 'create'])->name('quotes.create');
        Route::post('/quotes', [QuoteController::class, 'store'])->name('quotes.store');
        Route::get('/quotes/{quote}', [QuoteController::class, 'show'])
            ->whereNumber('quote')
            ->name('quotes.show');
        Route::get('/quotes/{quote}/edit', [QuoteController::class, 'edit'])
            ->whereNumber('quote')
            ->name('quotes.edit');
        Route::patch('/quotes/{quote}', [QuoteController::class, 'update'])
            ->whereNumber('quote')
            ->name('quotes.update');
        Route::delete('/quotes/{quote}', [QuoteController::class, 'destroy'])
            ->whereNumber('quote')
            ->name('quotes.destroy');
        Route::post('/quotes/{quote}/duplicate', [QuoteController::class, 'duplicate'])
            ->whereNumber('quote')
            ->name('quotes.duplicate');
        Route::post('/quotes/{quote}/status', [QuoteController::class, 'changeStatus'])
            ->whereNumber('quote')
            ->name('quotes.status.change');
        Route::post('/quotes/{quote}/pdf/generate', [QuoteController::class, 'generatePdf'])
            ->whereNumber('quote')
            ->name('quotes.pdf.generate');
        Route::get('/quotes/{quote}/pdf/download', [QuoteController::class, 'downloadPdf'])
            ->whereNumber('quote')
            ->name('quotes.pdf.download');
        Route::post('/quotes/{quote}/email/send', [QuoteController::class, 'sendEmail'])
            ->whereNumber('quote')
            ->name('quotes.email.send');

        Route::get('/price-tiers', [PriceTierController::class, 'index'])->name('price-tiers.index');
        Route::get('/price-tiers/create', [PriceTierController::class, 'create'])->name('price-tiers.create');
        Route::post('/price-tiers', [PriceTierController::class, 'store'])->name('price-tiers.store');
        Route::get('/price-tiers/{priceTier}/edit', [PriceTierController::class, 'edit'])
            ->whereNumber('priceTier')
            ->name('price-tiers.edit');
        Route::patch('/price-tiers/{priceTier}', [PriceTierController::class, 'update'])
            ->whereNumber('priceTier')
            ->name('price-tiers.update');
        Route::delete('/price-tiers/{priceTier}', [PriceTierController::class, 'destroy'])
            ->whereNumber('priceTier')
            ->name('price-tiers.destroy');

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
        Route::patch('/vat-rates/{vatRate}/enable', [VatRateController::class, 'enable'])
            ->whereNumber('vatRate')
            ->name('vat-rates.enable');
        Route::patch('/vat-rates/{vatRate}/disable', [VatRateController::class, 'disable'])
            ->whereNumber('vatRate')
            ->name('vat-rates.disable');

        Route::get('/vat-exemption-reasons', [VatExemptionReasonController::class, 'index'])->name('vat-exemption-reasons.index');
        Route::patch('/vat-exemption-reasons/{vatExemptionReason}/enable', [VatExemptionReasonController::class, 'enable'])
            ->whereNumber('vatExemptionReason')
            ->name('vat-exemption-reasons.enable');
        Route::patch('/vat-exemption-reasons/{vatExemptionReason}/disable', [VatExemptionReasonController::class, 'disable'])
            ->whereNumber('vatExemptionReason')
            ->name('vat-exemption-reasons.disable');
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
