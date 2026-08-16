<?php

use App\Http\Controllers\CashShiftController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\CompanyMemberController;
use App\Http\Controllers\DianController;
use App\Http\Controllers\DocumentoEmitidoController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PaymentMethodController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\PriceTypeController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductImportController;
use App\Http\Controllers\WarehouseController;
use App\Http\Controllers\SuperadminController;
use App\Http\Controllers\ThirdPartyController;
use App\Livewire\Settings\Appearance;
use App\Livewire\Settings\Language;
use App\Livewire\Settings\Password;
use App\Livewire\Settings\Profile;
use App\Models\Department;
use App\Models\FiscalResponsibility;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
})->name('home');

Route::get('dashboard', function () {
    $companies = auth()->user()->companiesWithMembership();
    $fiscalResponsibilities = FiscalResponsibility::orderBy('codigo')->get();
    $departments = Department::orderBy('descripcion')->get();

    return view('dashboard', compact('companies', 'fiscalResponsibilities', 'departments'));
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Route::get('settings/profile', Profile::class)->name('settings.profile');
    Route::get('settings/password', Password::class)->name('settings.password');
    Route::get('settings/appearance', Appearance::class)->name('settings.appearance');
    Route::get('settings/language', Language::class)->name('settings.language');

    Route::post('dashboard/select-company', [CompanyController::class, 'select'])->name('dashboard.select-company');

    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('notifications/{id}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::post('notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');

    Route::resource('companies', CompanyController::class)->only(['create', 'store', 'update']);
    Route::post('companies/{id}/certificate', [CompanyController::class, 'uploadCertificate'])->name('companies.certificate.upload');
    Route::delete('companies/{id}/certificate', [CompanyController::class, 'destroyCertificate'])->name('companies.certificate.destroy');
    Route::post('companies/{id}/certificate/validate', [CompanyController::class, 'validateExistingCertificate'])->name('companies.certificate.validate');
    Route::post('certificate/validate', [CompanyController::class, 'validateCertificate'])->name('certificate.validate');
    Route::post('companies/{id}/api-token', [CompanyController::class, 'regenerateApiToken'])->name('companies.api-token.regenerate');

    Route::post('companies/{id}/dian/send-test-set', [DianController::class, 'sendTestSet'])->name('companies.dian.send-test-set');
    Route::post('companies/{id}/dian/test-set-status', [DianController::class, 'testSetStatus'])->name('companies.dian.test-set-status');

    Route::middleware(['company.selected'])->prefix('companies')->name('companies.')->group(function () {
        Route::get('members', [CompanyMemberController::class, 'index'])->name('members.index');

        Route::middleware(['company.owner'])->group(function () {
            Route::post('members', [CompanyMemberController::class, 'store'])->name('members.store');
            Route::put('members/{userId}', [CompanyMemberController::class, 'update'])->name('members.update');
            Route::delete('members/{userId}', [CompanyMemberController::class, 'destroy'])->name('members.destroy');
        });
    });

    Route::middleware(['company.selected', 'company.role.any:invoicing:administrador|vendedor|auditor,pos:administrador|cajero|auditor'])
        ->prefix('clients')->name('clients.')->group(function () {
            Route::get('/', [ThirdPartyController::class, 'index'])->defaults('role', 'cliente')->name('index');
            Route::post('/', [ThirdPartyController::class, 'store'])->defaults('role', 'cliente')->name('store');
            Route::put('{thirdParty}', [ThirdPartyController::class, 'update'])->defaults('role', 'cliente')->name('update');
            Route::delete('{thirdParty}', [ThirdPartyController::class, 'destroy'])->defaults('role', 'cliente')->name('destroy');
        });

    Route::middleware(['company.selected', 'company.role:receiving,administrador,comprador,auditor'])
        ->prefix('providers')->name('providers.')->group(function () {
            Route::get('/', [ThirdPartyController::class, 'index'])->defaults('role', 'proveedor')->name('index');
            Route::post('/', [ThirdPartyController::class, 'store'])->defaults('role', 'proveedor')->name('store');
            Route::put('{thirdParty}', [ThirdPartyController::class, 'update'])->defaults('role', 'proveedor')->name('update');
            Route::delete('{thirdParty}', [ThirdPartyController::class, 'destroy'])->defaults('role', 'proveedor')->name('destroy');
        });

    Route::middleware(['company.selected', 'company.role.any:invoicing:administrador|vendedor|auditor,pos:administrador|cajero|auditor'])
        ->prefix('products')->name('products.')->group(function () {
            Route::get('/', [ProductController::class, 'index'])->name('index');
            Route::post('/', [ProductController::class, 'store'])->name('store');
            Route::get('{product}', [ProductController::class, 'show'])->name('show');
            Route::put('{product}', [ProductController::class, 'update'])->name('update');
            Route::post('{product}/image', [ProductController::class, 'updateImage'])->name('image.update');
            Route::delete('{product}', [ProductController::class, 'destroy'])->name('destroy');
            Route::post('{product}/stock-entries', [ProductController::class, 'storeStockEntry'])->name('stock-entries.store');
            Route::post('{product}/average-cost', [ProductController::class, 'correctAverageCost'])->name('average-cost.update');
            Route::get('{product}/kardex', [ProductController::class, 'kardex'])->name('kardex');
            Route::post('import/preview', [ProductImportController::class, 'preview'])->name('import.preview');
            Route::post('import', [ProductImportController::class, 'import'])->name('import');
        });

    Route::middleware(['company.selected', 'company.role.any:invoicing:administrador|vendedor|auditor,pos:administrador|cajero|auditor'])
        ->prefix('warehouses')->name('warehouses.')->group(function () {
            Route::post('/', [WarehouseController::class, 'store'])->name('store');
            Route::put('{warehouse}', [WarehouseController::class, 'update'])->name('update');
            Route::delete('{warehouse}', [WarehouseController::class, 'destroy'])->name('destroy');
        });

    Route::middleware(['company.selected', 'company.role.any:invoicing:administrador|vendedor|auditor,pos:administrador|cajero|auditor'])
        ->prefix('price-types')->name('price-types.')->group(function () {
            Route::post('/', [PriceTypeController::class, 'store'])->name('store');
            Route::put('{priceType}', [PriceTypeController::class, 'update'])->name('update');
            Route::delete('{priceType}', [PriceTypeController::class, 'destroy'])->name('destroy');
        });

    Route::middleware(['company.selected', 'company.role.any:invoicing:administrador|vendedor|auditor,pos:administrador|cajero|auditor'])
        ->prefix('documents')->name('documents.')->group(function () {
            Route::get('/', [DocumentoEmitidoController::class, 'index'])->name('index');
            Route::get('create', [DocumentoEmitidoController::class, 'create'])->name('create');
            Route::get('create/options', [DocumentoEmitidoController::class, 'createOptions'])->name('create-options');
            Route::get('create/factura-lookup', [DocumentoEmitidoController::class, 'facturaLookup'])->name('create-factura-lookup');
            Route::get('create/validate-uuid', [DocumentoEmitidoController::class, 'validateUuid'])->name('create-validate-uuid');
            Route::get('create/client-search', [DocumentoEmitidoController::class, 'clientSearch'])->name('create-client-search');
            Route::get('create/product-search', [DocumentoEmitidoController::class, 'productSearch'])->name('create-product-search');
            Route::post('/', [DocumentoEmitidoController::class, 'store'])->name('store');
            Route::get('{documento}', [DocumentoEmitidoController::class, 'show'])->name('show');
            Route::get('{documento}/receipt.pdf', [DocumentoEmitidoController::class, 'receiptPdf'])->name('receipt-pdf');
        });

    Route::middleware(['company.selected', 'company.role:pos,administrador,cajero,auditor'])
        ->prefix('pos')->name('pos.')->group(function () {
            Route::get('/', [PosController::class, 'create'])->name('create');
            Route::get('shift', [PosController::class, 'shift'])->name('shift');
            Route::post('checkout', [PosController::class, 'checkout'])->name('checkout');
            Route::get('sales', [PosController::class, 'sales'])->name('sales.index');
            Route::get('sales/{sale}', [PosController::class, 'showSale'])->name('sales.show');
            Route::get('sales/{sale}/receipt.pdf', [PosController::class, 'receiptPdf'])->name('sales.receipt-pdf');
            Route::post('sales/{sale}/issue-electronic', [PosController::class, 'issueElectronic'])->name('sales.issue-electronic');
            Route::post('shifts', [CashShiftController::class, 'store'])->name('shifts.store');
            Route::get('shifts/{shift}', [CashShiftController::class, 'show'])->name('shifts.show');
            Route::post('shifts/{shift}/close', [CashShiftController::class, 'close'])->name('shifts.close');
            Route::get('payment-methods', [PaymentMethodController::class, 'index'])->name('payment-methods.index');
            Route::post('payment-methods', [PaymentMethodController::class, 'store'])->name('payment-methods.store');
            Route::put('payment-methods/{paymentMethod}', [PaymentMethodController::class, 'update'])->name('payment-methods.update');
            Route::delete('payment-methods/{paymentMethod}', [PaymentMethodController::class, 'destroy'])->name('payment-methods.destroy');
        });

    Route::middleware(['company.selected', 'company.role.any:invoicing:administrador|vendedor|auditor,pos:administrador|cajero|auditor'])
        ->prefix('dian')->name('dian.')->group(function () {
            Route::get('resolutions', [DianController::class, 'resolutions'])->name('resolutions.index');
            Route::post('resolutions/sync', [DianController::class, 'syncResolutions'])->name('resolutions.sync');
            Route::post('resolutions/manual', [DianController::class, 'storeManualResolution'])->name('resolutions.manual');
            Route::delete('resolutions/{resolutionId}', [DianController::class, 'destroyManualResolution'])->name('resolutions.destroy');
            Route::post('acquirer', [DianController::class, 'acquirer'])->name('acquirer');
        });

    Route::middleware(['superadmin'])->prefix('admin')->name('admin.')->group(function () {
        Route::get('companies', [SuperadminController::class, 'companies'])->name('companies');
        Route::get('companies/{companyId}/edit', [SuperadminController::class, 'editCompany'])->name('companies.edit');
        Route::put('companies/{companyId}/modules', [SuperadminController::class, 'updateModules'])->name('companies.modules.update');
        Route::post('companies/{companyId}/members', [SuperadminController::class, 'storeMember'])->name('companies.members.store');
        Route::put('companies/{companyId}/members/{userId}', [SuperadminController::class, 'updateMember'])->name('companies.members.update');
        Route::delete('companies/{companyId}/members/{userId}', [SuperadminController::class, 'destroyMember'])->name('companies.members.destroy');

        Route::get('users', [SuperadminController::class, 'users'])->name('users');
        Route::post('users/{userId}/toggle-superadmin', [SuperadminController::class, 'toggleSuperadmin'])->name('users.toggle-superadmin');

        Route::get('notifications', [SuperadminController::class, 'notificationsCreate'])->name('notifications.create');
        Route::post('notifications', [SuperadminController::class, 'notificationsStore'])->name('notifications.store');
    });
});

require __DIR__.'/auth.php';
