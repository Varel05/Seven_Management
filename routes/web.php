<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/dashboard', function () {
    $transactions = \App\Models\JournalEntry::with('lines.account')
        ->latest('date')
        ->limit(10)
        ->get();

    // Saldo Kas Operasional (Debit - Credit)
    $cashDebit = \App\Models\JournalEntryLine::whereHas('account', fn($q) => $q->where('code', '1001'))->sum('debit');
    $cashCredit = \App\Models\JournalEntryLine::whereHas('account', fn($q) => $q->where('code', '1001'))->sum('credit');
    $totalKas = $cashDebit - $cashCredit;

    // Pemasukan bulan ini (Akun Pendapatan/Revenue)
    $pemasukanBulanIni = \App\Models\JournalEntryLine::whereHas('account', fn($q) => $q->where('type', 'revenue'))
        ->whereMonth('created_at', now()->month)
        ->whereYear('created_at', now()->year)
        ->sum('credit');

    // Pengeluaran bulan ini (Akun Beban/Expense)
    $pengeluaranBulanIni = \App\Models\JournalEntryLine::whereHas('account', fn($q) => $q->where('type', 'expense'))
        ->whereMonth('created_at', now()->month)
        ->whereYear('created_at', now()->year)
        ->sum('debit');

    // Transaksi Pending verifikasi
    $pendingCount = \App\Models\JournalEntry::where('status', 'pending')->count();

    return view('dashboard', compact('transactions', 'totalKas', 'pemasukanBulanIni', 'pengeluaranBulanIni', 'pendingCount'));
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
