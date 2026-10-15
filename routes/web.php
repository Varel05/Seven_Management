<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AllowanceController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\JournalExportController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RecurringTransactionController;
use App\Http\Controllers\TailorController;
use App\Http\Controllers\TailorPayrollController;
use App\Http\Controllers\TaxController;
use App\Models\JournalEntry;
use App\Services\TransactionCancellationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/dashboard', [DashboardController::class, 'index'])->middleware(['auth', 'verified'])->name('dashboard');
Route::get('/dashboard/live-data', [DashboardController::class, 'liveData'])->middleware(['auth', 'verified'])->name('dashboard.live-data');
Route::post('/tax/pay', [DashboardController::class, 'recordTaxPayment'])->middleware(['auth', 'verified'])->name('tax.pay');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Tindakan Verifikasi / Tolak / Batalkan Jurnal Akuntansi dari Dashboard
    Route::patch('/journal-entries/{journalEntry}/verify', function (JournalEntry $journalEntry) {
        $journalEntry->update(['status' => 'verified']);

        return back()->with('success', 'Transaksi '.$journalEntry->reference.' berhasil diverifikasi ke buku besar.');
    })->name('journal-entries.verify');

    Route::patch('/journal-entries/{journalEntry}/reject', function (JournalEntry $journalEntry, Request $request, TransactionCancellationService $cancellationService) {
        $reason = $request->input('reason', 'Ditolak via Dashboard Web');
        $actor = auth()->user()?->name ?? 'Admin Web';
        $result = $cancellationService->cancel($journalEntry, $reason, $actor);

        return back()->with('warning', $result['message']);
    })->name('journal-entries.reject');

    Route::post('/journal-entries/{journalEntry}/cancel', function (JournalEntry $journalEntry, Request $request, TransactionCancellationService $cancellationService) {
        $reason = $request->input('reason', 'Dibatalkan via Dashboard Web');
        $actor = auth()->user()?->name ?? 'Admin Web';
        $result = $cancellationService->cancel($journalEntry, $reason, $actor);

        return back()->with('warning', $result['message']);
    })->name('journal-entries.cancel');

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
    Route::patch('/employees/{employee}/salary', [EmployeeController::class, 'updateSalary'])->name('employees.salary');
    Route::patch('/employees/{employee}/points', [EmployeeController::class, 'updatePoints'])->name('employees.points');
    Route::delete('/employees/{employee}', [EmployeeController::class, 'destroy'])->name('employees.destroy');
    Route::post('/employees/{employee}/pay', [EmployeeController::class, 'pay'])->name('employees.pay');
    Route::post('/employees/{employee}/generate-slip', [EmployeeController::class, 'generateSlip'])->name('employees.generate-slip');
    Route::get('/employees/payroll/{payroll}/slip', [EmployeeController::class, 'showSlip'])->name('employees.payroll.slip');
    Route::post('/employees/payroll/{payroll}/post-transaction', [EmployeeController::class, 'postTransaction'])->name('employees.payroll.post-transaction');
    Route::get('/employees/payroll/{payroll}/pdf', [EmployeeController::class, 'downloadPdf'])->name('employees.payroll.pdf');
    Route::get('/employees/{employee}/slip-preview', [EmployeeController::class, 'previewSlip'])->name('employees.slip.preview');
    Route::get('/employees/attendance-list', [EmployeeController::class, 'attendanceList'])->name('employees.attendance-list');
    Route::get('/employees/{employee}/fetch-attendance', [EmployeeController::class, 'fetchAttendance'])->name('employees.fetch-attendance');
    Route::post('/employees/sync-api', [EmployeeController::class, 'syncFromApi'])->name('employees.sync-api');

    // Manajemen Tunjangan Pegawai
    Route::post('/allowances', [AllowanceController::class, 'store'])->name('allowances.store');
    Route::put('/allowances/{allowance}', [AllowanceController::class, 'update'])->name('allowances.update');
    Route::patch('/allowances/{allowance}/toggle', [AllowanceController::class, 'toggle'])->name('allowances.toggle');
    Route::delete('/allowances/{allowance}', [AllowanceController::class, 'destroy'])->name('allowances.destroy');

    // Data Penjahit & Upah Penjahit (Borongan)
    Route::post('/tailors', [TailorController::class, 'store'])->name('tailors.store');
    Route::put('/tailors/{tailor}', [TailorController::class, 'update'])->name('tailors.update');
    Route::delete('/tailors/{tailor}', [TailorController::class, 'destroy'])->name('tailors.destroy');
    Route::resource('tailor-payrolls', TailorPayrollController::class);

    // Manajemen & Pelaporan Pajak PPh Final UMKM (Halaman Khusus)
    Route::get('/taxes', [TaxController::class, 'index'])->name('taxes.index');
    Route::post('/taxes/pay', [TaxController::class, 'recordPayment'])->name('taxes.pay');
});

require __DIR__.'/auth.php';
