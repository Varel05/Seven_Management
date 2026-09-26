<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\CostSheetController;
use App\Http\Controllers\CustomSuitOrderController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\JournalExportController;
use App\Http\Controllers\MaterialController;
use App\Http\Controllers\ProductionOrderController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RecurringTransactionController;
use App\Http\Controllers\RetailController;
use App\Models\JournalEntry;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/dashboard', [DashboardController::class, 'index'])->middleware(['auth', 'verified'])->name('dashboard');
Route::get('/dashboard/live-data', [DashboardController::class, 'liveData'])->middleware(['auth', 'verified'])->name('dashboard.live-data');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Tindakan Verifikasi / Tolak Jurnal Akuntansi dari Dashboard
    Route::patch('/journal-entries/{journalEntry}/verify', function (JournalEntry $journalEntry) {
        $journalEntry->update(['status' => 'verified']);

        return back()->with('success', 'Transaksi '.$journalEntry->reference.' berhasil diverifikasi ke buku besar.');
    })->name('journal-entries.verify');

    Route::patch('/journal-entries/{journalEntry}/reject', function (JournalEntry $journalEntry) {
        $journalEntry->update(['status' => 'rejected']);

        return back()->with('warning', 'Transaksi '.$journalEntry->reference.' ditandai ditolak (rejected).');
    })->name('journal-entries.reject');

    // Ekspor Buku Jurnal Transaksi Bulanan ke Excel (.xls)
    Route::get('/journal-entries/export-monthly', [JournalExportController::class, 'exportMonthly'])
        ->name('journal-entries.export-monthly');

    // Manajemen Bagan Akun Keuangan (Chart of Accounts)
    Route::post('/accounts', [AccountController::class, 'store'])->name('accounts.store');
    Route::put('/accounts/{account}', [AccountController::class, 'update'])->name('accounts.update');
    Route::delete('/accounts/{account}', [AccountController::class, 'destroy'])->name('accounts.destroy');

    // Manajemen Pengeluaran Rutin & Langganan (Recurring Transactions)
    Route::post('/recurring-transactions', [RecurringTransactionController::class, 'store'])->name('recurring-transactions.store');
    Route::put('/recurring-transactions/{recurringTransaction}', [RecurringTransactionController::class, 'update'])->name('recurring-transactions.update');
    Route::delete('/recurring-transactions/{recurringTransaction}', [RecurringTransactionController::class, 'destroy'])->name('recurring-transactions.destroy');
    Route::post('/recurring-transactions/{recurringTransaction}/approve', [RecurringTransactionController::class, 'approve'])->name('recurring-transactions.approve');

    // Manajemen Data Karyawan & Payroll
    Route::get('/employees', [EmployeeController::class, 'index'])->name('employees.index');
    Route::post('/employees', [EmployeeController::class, 'store'])->name('employees.store');
    Route::put('/employees/{employee}', [EmployeeController::class, 'update'])->name('employees.update');
    Route::patch('/employees/{employee}/points', [EmployeeController::class, 'updatePoints'])->name('employees.points');
    Route::delete('/employees/{employee}', [EmployeeController::class, 'destroy'])->name('employees.destroy');
    Route::post('/employees/{employee}/pay', [EmployeeController::class, 'pay'])->name('employees.pay');

    // Manajemen Retail Pakaian & Kasir
    Route::get('/retail', [RetailController::class, 'index'])->name('retail.index');
    Route::post('/retail/products', [RetailController::class, 'storeProduct'])->name('retail.products.store');
    Route::put('/retail/products/{product}', [RetailController::class, 'updateProduct'])->name('retail.products.update');
    Route::post('/retail/products/update-points', [RetailController::class, 'updateItemPoints'])->name('retail.products.update-points');
    Route::post('/retail/point-settings', [RetailController::class, 'updatePointSettings'])->name('retail.point-settings.update');
    Route::delete('/retail/products/{product}', [RetailController::class, 'destroyProduct'])->name('retail.products.destroy');
    Route::post('/retail/sales', [RetailController::class, 'storeSale'])->name('retail.sales.store');
    Route::post('/retail/rentals/{sale}/return', [RetailController::class, 'returnRental'])->name('retail.rentals.return');

    // Manajemen Jasa Pembuatan Jas Custom & AI Material Estimator
    Route::get('/custom-orders', [CustomSuitOrderController::class, 'index'])->name('custom-orders.index');
    Route::get('/custom-orders/create', [CustomSuitOrderController::class, 'create'])->name('custom-orders.create');
    Route::post('/custom-orders', [CustomSuitOrderController::class, 'store'])->name('custom-orders.store');
    Route::get('/custom-orders/{order}', [CustomSuitOrderController::class, 'show'])->name('custom-orders.show');
    Route::post('/custom-orders/estimate', [CustomSuitOrderController::class, 'calculateEstimate'])->name('custom-orders.estimate');
    Route::patch('/custom-orders/{order}/status', [CustomSuitOrderController::class, 'updateStatus'])->name('custom-orders.status');
    Route::post('/custom-orders/{order}/payment', [CustomSuitOrderController::class, 'recordPayment'])->name('custom-orders.payment');
    Route::post('/custom-orders/{order}/cut-material', [CustomSuitOrderController::class, 'cutMaterial'])->name('custom-orders.cut-material');

    // Manajemen Stok Bahan Baku & Komponen Biaya
    Route::get('/materials', MaterialController::class.'@index')->name('materials.index');
    Route::post('/materials', MaterialController::class.'@store')->name('materials.store');
    Route::put('/materials/{material}', MaterialController::class.'@update')->name('materials.update');
    Route::post('/materials/{material}/restock', MaterialController::class.'@restock')->name('materials.restock');
    Route::delete('/materials/{material}', MaterialController::class.'@destroy')->name('materials.destroy');

    // Kartu HPP & Bill of Materials (BOM)
    Route::get('/cost-sheets', CostSheetController::class.'@index')->name('cost-sheets.index');
    Route::post('/cost-sheets', CostSheetController::class.'@store')->name('cost-sheets.store');
    Route::get('/cost-sheets/{costSheet}', CostSheetController::class.'@show')->name('cost-sheets.show');
    Route::post('/cost-sheets/{costSheet}/variants', CostSheetController::class.'@storeVariant')->name('cost-sheets.variants.store');
    Route::post('/cost-sheet-variants/{variant}/items', CostSheetController::class.'@storeItem')->name('cost-sheets.items.store');
    Route::delete('/cost-sheet-items/{item}', CostSheetController::class.'@destroyItem')->name('cost-sheets.items.destroy');
    Route::post('/cost-sheet-variants/{variant}/sync-product', CostSheetController::class.'@syncProduct')->name('cost-sheets.sync-product');

    // Form Manual Produksi Baju (Work Order & Eksekusi Potong Bahan)
    Route::get('/production/create', ProductionOrderController::class.'@create')->name('production.create');
    Route::post('/production', ProductionOrderController::class.'@store')->name('production.store');
});

require __DIR__.'/auth.php';
