<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\JournalExportController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RecurringTransactionController;
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
});

require __DIR__.'/auth.php';
