<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SalesController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FinanceReportController;
use App\Livewire\Reports\CustomerReport;
use App\Livewire\Reports\ProductReport;
use App\Livewire\Reports\CustomerNominalReport;

Route::middleware(['auth', 'verified'])->group(function () {

    // =========================================================================
    // Shared (Super Admin + Admin)
    // =========================================================================
    Route::get('/', function () {
        return redirect()->route('dashboard');
    });
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::view('profile', 'profile.index')->name('profile.index');

    // POS + Sales (admin needs full sales access)
    Route::resource('sales', SalesController::class)->except(['edit', 'update']);
    Route::prefix('sales/{sale}')->name('sales.')->controller(SalesController::class)->group(function () {
        Route::get('print', 'print')->name('print');
        Route::patch('complete', 'complete')->name('complete');
        Route::patch('restore', 'restore')->name('restore');
    });

    // Customers (needed by POS)
    Route::view('master/customers', 'customers.index')->name('customers.index');

    // Internal AJAX (POS dependencies)
    Route::prefix('ajax')->name('ajax.')->group(function () {
        Route::post('products', [\App\Http\Controllers\Api\ProductController::class, 'search'])->name('products.search');
        Route::post('customers', [\App\Http\Controllers\Api\CustomerController::class, 'search'])->name('customers.search');
        Route::post('customers/store', [\App\Http\Controllers\Api\CustomerController::class, 'store'])->name('customers.store');
        Route::post('categories', [\App\Http\Controllers\Api\CategoryController::class, 'search'])->name('categories.search');
        Route::post('units', [\App\Http\Controllers\Api\UnitController::class, 'search'])->name('units.search');
        Route::post('suppliers', [\App\Http\Controllers\Api\SupplierController::class, 'search'])
            ->middleware('super_admin')->name('suppliers.search');
        Route::post('users', [\App\Http\Controllers\Api\UserController::class, 'search'])
            ->middleware('super_admin')->name('users.search');
        Route::post('finance-categories', [\App\Http\Controllers\Api\FinanceCategoryController::class, 'search'])
            ->middleware('super_admin')->name('finance-categories.search');
        Route::post('sales/lookup', [\App\Http\Controllers\Api\SaleController::class, 'lookup'])
            ->middleware('super_admin')->name('sales.lookup');
    });

    // =========================================================================
    // Super Admin only
    // =========================================================================
    Route::middleware('super_admin')->group(function () {

        // Master Data
        Route::prefix('master')->group(function () {
            Route::view('suppliers', 'suppliers.index')->name('suppliers.index');
            Route::view('categories', 'categories.index')->name('categories.index');
            Route::view('units', 'units.index')->name('units.index');
            Route::view('products', 'products.index')->name('products.index');
        });

        Route::get('import-products', [\App\Http\Controllers\Api\ProductController::class, 'showImportForm']);
        Route::post('import-products', [\App\Http\Controllers\Api\ProductController::class, 'import'])->name('product.import');
        Route::get('products/template', [\App\Http\Controllers\Api\ProductController::class, 'downloadTemplate'])->name('products.template');

        // Purchases
        Route::resource('purchases', PurchaseController::class);
        Route::prefix('purchases/{purchase}')->name('purchases.')->controller(PurchaseController::class)->group(function () {
            Route::patch('ordered', 'markOrdered')->name('mark-ordered');
            Route::patch('received', 'markReceived')->name('mark-received');
            Route::patch('paid', 'markPaid')->name('mark-paid');
            Route::patch('cancel', 'cancel')->name('cancel');
            Route::patch('restore-draft', 'restoreToDraft')->name('restore-draft');
        });

        // Finance
        Route::prefix('finance')->name('finance.')->group(function () {
            Route::view('categories', 'finance-categories.index')->name('categories.index');
            Route::view('transactions', 'finance-transactions.index')->name('transactions.index');
            Route::get('transactions/print/{printId}', [FinanceReportController::class, 'print'])->name('transactions.print');
        });

        // Reports
        Route::prefix('reports')->name('reports.')->group(function () {
            Route::get('customers', CustomerReport::class)->name('customers');
            Route::get('products', ProductReport::class)->name('products');
            Route::get('customer-nominal', CustomerNominalReport::class)->name('customer-nominal');
        });

        // Sale Returns
        Route::resource('sale-returns', \App\Http\Controllers\SaleReturnController::class)
            ->except(['edit', 'update']);
        Route::get('sale-returns/{saleReturn}/print', [\App\Http\Controllers\SaleReturnController::class, 'print'])
            ->name('sale-returns.print');

        // Settings & Users
        Route::view('users', 'users.index')->name('users.index');
        Route::view('settings', 'settings.index')->name('settings.index');
    });
});

require __DIR__.'/auth.php';
