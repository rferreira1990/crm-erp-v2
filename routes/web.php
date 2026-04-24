<?php

use App\Http\Controllers\SuperAdmin\CompanyController;
use App\Http\Controllers\SuperAdmin\EmailSettingsController;
use App\Http\Controllers\InvitationAcceptanceController;
use App\Http\Controllers\Admin\CompanyUserController;
use App\Http\Controllers\Admin\CompanyUserInvitationController;
use App\Http\Controllers\Admin\CompanySettingsController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\BrandController;
use App\Http\Controllers\Admin\ArticleController;
use App\Http\Controllers\Admin\ArticleMediaController;
use App\Http\Controllers\Admin\ConstructionSiteController;
use App\Http\Controllers\Admin\ConstructionSiteLogController;
use App\Http\Controllers\Admin\ConstructionSiteMaterialUsageController;
use App\Http\Controllers\Admin\ConstructionSiteTimeEntryController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\CustomerContactController;
use App\Http\Controllers\Admin\PaymentMethodController;
use App\Http\Controllers\Admin\PaymentTermController;
use App\Http\Controllers\Admin\PriceTierController;
use App\Http\Controllers\Admin\PurchaseOrderController;
use App\Http\Controllers\Admin\PurchaseOrderGenerationController;
use App\Http\Controllers\Admin\PurchaseOrderReceiptController;
use App\Http\Controllers\Admin\QuoteDashboardController;
use App\Http\Controllers\Admin\ProductFamilyController;
use App\Http\Controllers\Admin\QuoteController;
use App\Http\Controllers\Admin\SupplierController;
use App\Http\Controllers\Admin\SupplierContactController;
use App\Http\Controllers\Admin\SupplierQuoteAwardController;
use App\Http\Controllers\Admin\SupplierQuoteComparisonController;
use App\Http\Controllers\Admin\SupplierQuoteRequestController;
use App\Http\Controllers\Admin\SupplierQuoteResponseController;
use App\Http\Controllers\Admin\StockMovementController;
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

        Route::get('/company-settings', [CompanySettingsController::class, 'edit'])->name('company-settings.edit');
        Route::put('/company-settings', [CompanySettingsController::class, 'update'])->name('company-settings.update');
        Route::post('/company-settings/test-smtp', [CompanySettingsController::class, 'testSmtp'])
            ->middleware('throttle:6,1')
            ->name('company-settings.test-smtp');
        Route::get('/company-settings/logo', [CompanySettingsController::class, 'showLogo'])->name('company-settings.logo.show');

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
        Route::get('/articles/export/csv', [ArticleController::class, 'exportCsv'])->name('articles.export.csv');
        Route::get('/articles/import', [ArticleController::class, 'importForm'])->name('articles.import');
        Route::get('/articles/import/template/csv', [ArticleController::class, 'downloadImportTemplate'])
            ->name('articles.import.template.csv');
        Route::post('/articles/import/csv', [ArticleController::class, 'importCsv'])
            ->middleware('throttle:10,1')
            ->name('articles.import.csv');
        Route::get('/articles/{article}', [ArticleController::class, 'show'])
            ->whereNumber('article')
            ->name('articles.show');
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

        Route::get('/construction-sites', [ConstructionSiteController::class, 'index'])->name('construction-sites.index');
        Route::get('/construction-sites/create', [ConstructionSiteController::class, 'create'])->name('construction-sites.create');
        Route::post('/construction-sites', [ConstructionSiteController::class, 'store'])->name('construction-sites.store');
        Route::get('/construction-sites/{constructionSite}', [ConstructionSiteController::class, 'show'])
            ->whereNumber('constructionSite')
            ->name('construction-sites.show');
        Route::get('/construction-sites/{constructionSite}/edit', [ConstructionSiteController::class, 'edit'])
            ->whereNumber('constructionSite')
            ->name('construction-sites.edit');
        Route::patch('/construction-sites/{constructionSite}', [ConstructionSiteController::class, 'update'])
            ->whereNumber('constructionSite')
            ->name('construction-sites.update');
        Route::delete('/construction-sites/{constructionSite}', [ConstructionSiteController::class, 'destroy'])
            ->whereNumber('constructionSite')
            ->name('construction-sites.destroy');
        Route::get('/construction-sites/{constructionSite}/images/{constructionSiteImage}', [ConstructionSiteController::class, 'showImage'])
            ->whereNumber('constructionSite')
            ->whereNumber('constructionSiteImage')
            ->name('construction-sites.images.show');
        Route::delete('/construction-sites/{constructionSite}/images/{constructionSiteImage}', [ConstructionSiteController::class, 'destroyImage'])
            ->whereNumber('constructionSite')
            ->whereNumber('constructionSiteImage')
            ->name('construction-sites.images.destroy');
        Route::get('/construction-sites/{constructionSite}/files/{constructionSiteFile}/download', [ConstructionSiteController::class, 'downloadFile'])
            ->whereNumber('constructionSite')
            ->whereNumber('constructionSiteFile')
            ->name('construction-sites.files.download');
        Route::delete('/construction-sites/{constructionSite}/files/{constructionSiteFile}', [ConstructionSiteController::class, 'destroyFile'])
            ->whereNumber('constructionSite')
            ->whereNumber('constructionSiteFile')
            ->name('construction-sites.files.destroy');

        Route::get('/construction-sites/{constructionSite}/logs', [ConstructionSiteLogController::class, 'index'])
            ->whereNumber('constructionSite')
            ->name('construction-sites.logs.index');
        Route::get('/construction-sites/{constructionSite}/logs/create', [ConstructionSiteLogController::class, 'create'])
            ->whereNumber('constructionSite')
            ->name('construction-sites.logs.create');
        Route::post('/construction-sites/{constructionSite}/logs', [ConstructionSiteLogController::class, 'store'])
            ->whereNumber('constructionSite')
            ->name('construction-sites.logs.store');
        Route::get('/construction-sites/{constructionSite}/logs/{constructionSiteLog}', [ConstructionSiteLogController::class, 'show'])
            ->whereNumber('constructionSite')
            ->whereNumber('constructionSiteLog')
            ->name('construction-sites.logs.show');
        Route::get('/construction-sites/{constructionSite}/logs/{constructionSiteLog}/edit', [ConstructionSiteLogController::class, 'edit'])
            ->whereNumber('constructionSite')
            ->whereNumber('constructionSiteLog')
            ->name('construction-sites.logs.edit');
        Route::patch('/construction-sites/{constructionSite}/logs/{constructionSiteLog}', [ConstructionSiteLogController::class, 'update'])
            ->whereNumber('constructionSite')
            ->whereNumber('constructionSiteLog')
            ->name('construction-sites.logs.update');
        Route::delete('/construction-sites/{constructionSite}/logs/{constructionSiteLog}', [ConstructionSiteLogController::class, 'destroy'])
            ->whereNumber('constructionSite')
            ->whereNumber('constructionSiteLog')
            ->name('construction-sites.logs.destroy');
        Route::get('/construction-sites/{constructionSite}/logs/{constructionSiteLog}/images/{constructionSiteLogImage}', [ConstructionSiteLogController::class, 'showImage'])
            ->whereNumber('constructionSite')
            ->whereNumber('constructionSiteLog')
            ->whereNumber('constructionSiteLogImage')
            ->name('construction-sites.logs.images.show');
        Route::delete('/construction-sites/{constructionSite}/logs/{constructionSiteLog}/images/{constructionSiteLogImage}', [ConstructionSiteLogController::class, 'destroyImage'])
            ->whereNumber('constructionSite')
            ->whereNumber('constructionSiteLog')
            ->whereNumber('constructionSiteLogImage')
            ->name('construction-sites.logs.images.destroy');
        Route::get('/construction-sites/{constructionSite}/logs/{constructionSiteLog}/files/{constructionSiteLogFile}/download', [ConstructionSiteLogController::class, 'downloadFile'])
            ->whereNumber('constructionSite')
            ->whereNumber('constructionSiteLog')
            ->whereNumber('constructionSiteLogFile')
            ->name('construction-sites.logs.files.download');
        Route::delete('/construction-sites/{constructionSite}/logs/{constructionSiteLog}/files/{constructionSiteLogFile}', [ConstructionSiteLogController::class, 'destroyFile'])
            ->whereNumber('constructionSite')
            ->whereNumber('constructionSiteLog')
            ->whereNumber('constructionSiteLogFile')
            ->name('construction-sites.logs.files.destroy');

        Route::get('/construction-sites/{constructionSite}/material-usages', [ConstructionSiteMaterialUsageController::class, 'index'])
            ->whereNumber('constructionSite')
            ->name('construction-sites.material-usages.index');
        Route::get('/construction-sites/{constructionSite}/material-usages/create', [ConstructionSiteMaterialUsageController::class, 'create'])
            ->whereNumber('constructionSite')
            ->name('construction-sites.material-usages.create');
        Route::post('/construction-sites/{constructionSite}/material-usages', [ConstructionSiteMaterialUsageController::class, 'store'])
            ->whereNumber('constructionSite')
            ->name('construction-sites.material-usages.store');
        Route::get('/construction-sites/{constructionSite}/material-usages/{constructionSiteMaterialUsage}', [ConstructionSiteMaterialUsageController::class, 'show'])
            ->whereNumber('constructionSite')
            ->whereNumber('constructionSiteMaterialUsage')
            ->name('construction-sites.material-usages.show');
        Route::get('/construction-sites/{constructionSite}/material-usages/{constructionSiteMaterialUsage}/edit', [ConstructionSiteMaterialUsageController::class, 'edit'])
            ->whereNumber('constructionSite')
            ->whereNumber('constructionSiteMaterialUsage')
            ->name('construction-sites.material-usages.edit');
        Route::patch('/construction-sites/{constructionSite}/material-usages/{constructionSiteMaterialUsage}', [ConstructionSiteMaterialUsageController::class, 'update'])
            ->whereNumber('constructionSite')
            ->whereNumber('constructionSiteMaterialUsage')
            ->name('construction-sites.material-usages.update');
        Route::post('/construction-sites/{constructionSite}/material-usages/{constructionSiteMaterialUsage}/post', [ConstructionSiteMaterialUsageController::class, 'post'])
            ->whereNumber('constructionSite')
            ->whereNumber('constructionSiteMaterialUsage')
            ->name('construction-sites.material-usages.post');
        Route::post('/construction-sites/{constructionSite}/material-usages/{constructionSiteMaterialUsage}/cancel', [ConstructionSiteMaterialUsageController::class, 'cancel'])
            ->whereNumber('constructionSite')
            ->whereNumber('constructionSiteMaterialUsage')
            ->name('construction-sites.material-usages.cancel');

        Route::get('/construction-site-time-entries', [ConstructionSiteTimeEntryController::class, 'index'])
            ->name('construction-site-time-entries.index');
        Route::get('/construction-sites/{constructionSite}/time-entries/create', [ConstructionSiteTimeEntryController::class, 'create'])
            ->whereNumber('constructionSite')
            ->name('construction-sites.time-entries.create');
        Route::post('/construction-sites/{constructionSite}/time-entries', [ConstructionSiteTimeEntryController::class, 'store'])
            ->whereNumber('constructionSite')
            ->name('construction-sites.time-entries.store');
        Route::get('/construction-sites/{constructionSite}/time-entries/{constructionSiteTimeEntry}', [ConstructionSiteTimeEntryController::class, 'show'])
            ->whereNumber('constructionSite')
            ->whereNumber('constructionSiteTimeEntry')
            ->name('construction-sites.time-entries.show');
        Route::get('/construction-sites/{constructionSite}/time-entries/{constructionSiteTimeEntry}/edit', [ConstructionSiteTimeEntryController::class, 'edit'])
            ->whereNumber('constructionSite')
            ->whereNumber('constructionSiteTimeEntry')
            ->name('construction-sites.time-entries.edit');
        Route::patch('/construction-sites/{constructionSite}/time-entries/{constructionSiteTimeEntry}', [ConstructionSiteTimeEntryController::class, 'update'])
            ->whereNumber('constructionSite')
            ->whereNumber('constructionSiteTimeEntry')
            ->name('construction-sites.time-entries.update');
        Route::delete('/construction-sites/{constructionSite}/time-entries/{constructionSiteTimeEntry}', [ConstructionSiteTimeEntryController::class, 'destroy'])
            ->whereNumber('constructionSite')
            ->whereNumber('constructionSiteTimeEntry')
            ->name('construction-sites.time-entries.destroy');

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

        Route::get('/rfqs', [SupplierQuoteRequestController::class, 'index'])->name('rfqs.index');
        Route::get('/rfqs/create', [SupplierQuoteRequestController::class, 'create'])->name('rfqs.create');
        Route::post('/rfqs', [SupplierQuoteRequestController::class, 'store'])->name('rfqs.store');
        Route::get('/rfqs/{rfq}', [SupplierQuoteRequestController::class, 'show'])
            ->whereNumber('rfq')
            ->name('rfqs.show');
        Route::get('/rfqs/{rfq}/edit', [SupplierQuoteRequestController::class, 'edit'])
            ->whereNumber('rfq')
            ->name('rfqs.edit');
        Route::patch('/rfqs/{rfq}', [SupplierQuoteRequestController::class, 'update'])
            ->whereNumber('rfq')
            ->name('rfqs.update');
        Route::delete('/rfqs/{rfq}', [SupplierQuoteRequestController::class, 'destroy'])
            ->whereNumber('rfq')
            ->name('rfqs.destroy');
        Route::post('/rfqs/{rfq}/pdf/generate', [SupplierQuoteRequestController::class, 'generatePdf'])
            ->whereNumber('rfq')
            ->name('rfqs.pdf.generate');
        Route::get('/rfqs/{rfq}/pdf/download', [SupplierQuoteRequestController::class, 'downloadPdf'])
            ->whereNumber('rfq')
            ->name('rfqs.pdf.download');
        Route::post('/rfqs/{rfq}/email/send', [SupplierQuoteRequestController::class, 'sendEmail'])
            ->whereNumber('rfq')
            ->name('rfqs.email.send');
        Route::get('/rfqs/{rfq}/suppliers/{rfqSupplier}/pdf/download', [SupplierQuoteRequestController::class, 'downloadSupplierPdf'])
            ->whereNumber('rfq')
            ->whereNumber('rfqSupplier')
            ->name('rfqs.suppliers.pdf.download');

        Route::get('/rfqs/{rfq}/suppliers/{rfqSupplier}/response/create', [SupplierQuoteResponseController::class, 'create'])
            ->whereNumber('rfq')
            ->whereNumber('rfqSupplier')
            ->name('rfqs.responses.create');
        Route::post('/rfqs/{rfq}/suppliers/{rfqSupplier}/response', [SupplierQuoteResponseController::class, 'store'])
            ->whereNumber('rfq')
            ->whereNumber('rfqSupplier')
            ->name('rfqs.responses.store');
        Route::get('/rfqs/{rfq}/suppliers/{rfqSupplier}/response/document/download', [SupplierQuoteResponseController::class, 'downloadDocument'])
            ->whereNumber('rfq')
            ->whereNumber('rfqSupplier')
            ->name('rfqs.responses.document.download');
        Route::get('/rfqs/{rfq}/compare', [SupplierQuoteComparisonController::class, 'show'])
            ->whereNumber('rfq')
            ->name('rfqs.compare');
        Route::post('/rfqs/{rfq}/awards', [SupplierQuoteAwardController::class, 'store'])
            ->whereNumber('rfq')
            ->name('rfqs.awards.store');
        Route::post('/rfqs/{rfq}/purchase-orders/generate', [PurchaseOrderGenerationController::class, 'store'])
            ->whereNumber('rfq')
            ->name('rfqs.purchase-orders.generate');

        Route::get('/purchase-orders', [PurchaseOrderController::class, 'index'])->name('purchase-orders.index');
        Route::get('/purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'show'])
            ->whereNumber('purchaseOrder')
            ->name('purchase-orders.show');
        Route::post('/purchase-orders/{purchaseOrder}/pdf/generate', [PurchaseOrderController::class, 'generatePdf'])
            ->whereNumber('purchaseOrder')
            ->name('purchase-orders.pdf.generate');
        Route::get('/purchase-orders/{purchaseOrder}/pdf/download', [PurchaseOrderController::class, 'downloadPdf'])
            ->whereNumber('purchaseOrder')
            ->name('purchase-orders.pdf.download');
        Route::post('/purchase-orders/{purchaseOrder}/email/send', [PurchaseOrderController::class, 'sendEmail'])
            ->whereNumber('purchaseOrder')
            ->name('purchase-orders.email.send');
        Route::post('/purchase-orders/{purchaseOrder}/status', [PurchaseOrderController::class, 'changeStatus'])
            ->whereNumber('purchaseOrder')
            ->name('purchase-orders.status.change');

        Route::get('/purchase-order-receipts', [PurchaseOrderReceiptController::class, 'index'])
            ->name('purchase-order-receipts.index');
        Route::get('/purchase-orders/{purchaseOrder}/receipts/create', [PurchaseOrderReceiptController::class, 'create'])
            ->whereNumber('purchaseOrder')
            ->name('purchase-order-receipts.create');
        Route::post('/purchase-orders/{purchaseOrder}/receipts', [PurchaseOrderReceiptController::class, 'store'])
            ->whereNumber('purchaseOrder')
            ->name('purchase-order-receipts.store');
        Route::get('/purchase-order-receipts/{purchaseOrderReceipt}', [PurchaseOrderReceiptController::class, 'show'])
            ->whereNumber('purchaseOrderReceipt')
            ->name('purchase-order-receipts.show');
        Route::post('/purchase-order-receipts/{purchaseOrderReceipt}/pdf/generate', [PurchaseOrderReceiptController::class, 'generatePdf'])
            ->whereNumber('purchaseOrderReceipt')
            ->name('purchase-order-receipts.pdf.generate');
        Route::get('/purchase-order-receipts/{purchaseOrderReceipt}/pdf/download', [PurchaseOrderReceiptController::class, 'downloadPdf'])
            ->whereNumber('purchaseOrderReceipt')
            ->name('purchase-order-receipts.pdf.download');
        Route::get('/purchase-order-receipts/{purchaseOrderReceipt}/edit', [PurchaseOrderReceiptController::class, 'edit'])
            ->whereNumber('purchaseOrderReceipt')
            ->name('purchase-order-receipts.edit');
        Route::patch('/purchase-order-receipts/{purchaseOrderReceipt}', [PurchaseOrderReceiptController::class, 'update'])
            ->whereNumber('purchaseOrderReceipt')
            ->name('purchase-order-receipts.update');
        Route::post('/purchase-order-receipts/{purchaseOrderReceipt}/post', [PurchaseOrderReceiptController::class, 'post'])
            ->whereNumber('purchaseOrderReceipt')
            ->name('purchase-order-receipts.post');
        Route::post('/purchase-order-receipts/{purchaseOrderReceipt}/cancel', [PurchaseOrderReceiptController::class, 'cancel'])
            ->whereNumber('purchaseOrderReceipt')
            ->name('purchase-order-receipts.cancel');
        Route::post('/purchase-order-receipts/{purchaseOrderReceipt}/lines/{receiptItem}/resolve', [PurchaseOrderReceiptController::class, 'resolveLine'])
            ->whereNumber('purchaseOrderReceipt')
            ->whereNumber('receiptItem')
            ->name('purchase-order-receipts.lines.resolve');

        Route::get('/stock-movements', [StockMovementController::class, 'index'])
            ->name('stock-movements.index');
        Route::get('/stock-movements/create', [StockMovementController::class, 'create'])
            ->name('stock-movements.create');
        Route::post('/stock-movements', [StockMovementController::class, 'store'])
            ->name('stock-movements.store');
        Route::get('/stock-movements/{stockMovement}', [StockMovementController::class, 'show'])
            ->whereNumber('stockMovement')
            ->name('stock-movements.show');

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
