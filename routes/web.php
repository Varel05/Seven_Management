<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/dashboard', function () {
    $transactions = \App\Models\JournalEntry::with('lines.account')
        ->latest('date')
        ->get();

    // Saldo Kas & Bank (Semua Akun bertipe Asset, normal balance: Debit - Credit)
    $assetDebit = \App\Models\JournalEntryLine::whereHas('account', fn($q) => $q->where('type', 'asset'))->sum('debit');
    $assetCredit = \App\Models\JournalEntryLine::whereHas('account', fn($q) => $q->where('type', 'asset'))->sum('credit');
    $totalKasDanBank = (float)($assetDebit - $assetCredit);

    // Khusus Kas Operasional (1001)
    $cashDebit = \App\Models\JournalEntryLine::whereHas('account', fn($q) => $q->where('code', '1001'))->sum('debit');
    $cashCredit = \App\Models\JournalEntryLine::whereHas('account', fn($q) => $q->where('code', '1001'))->sum('credit');
    $totalKas = (float)($cashDebit - $cashCredit);

    // Pemasukan bulan ini (Akun Pendapatan/Revenue)
    $pemasukanBulanIni = (float)\App\Models\JournalEntryLine::whereHas('account', fn($q) => $q->where('type', 'revenue'))
        ->whereMonth('created_at', now()->month)
        ->whereYear('created_at', now()->year)
        ->sum('credit');

    // Pengeluaran bulan ini (Akun Beban/Expense)
    $pengeluaranBulanIni = (float)\App\Models\JournalEntryLine::whereHas('account', fn($q) => $q->where('type', 'expense'))
        ->whereMonth('created_at', now()->month)
        ->whereYear('created_at', now()->year)
        ->sum('debit');

    // Laba / Rugi Bersih bulan berjalan
    $labaBersihBulanIni = $pemasukanBulanIni - $pengeluaranBulanIni;

    // Transaksi Pending verifikasi (misal dari bot Telegram)
    $pendingCount = \App\Models\JournalEntry::where('status', 'pending')->count();
    $verifiedCount = \App\Models\JournalEntry::where('status', 'verified')->count();

    // Ringkasan Akun Utama (Chart of Accounts)
    $accounts = \App\Models\Account::with('lines')->get()->map(function ($acc) {
        $debit = $acc->lines->sum('debit');
        $credit = $acc->lines->sum('credit');
        $balance = in_array($acc->type, ['asset', 'expense']) ? ($debit - $credit) : ($credit - $debit);
        return [
            'code'      => $acc->code,
            'name'      => $acc->name,
            'type'      => $acc->type,
            'balance'   => (float)$balance,
            'trx_count' => $acc->lines->count(),
        ];
    });

    return view('dashboard', compact(
        'transactions', 
        'totalKas', 
        'totalKasDanBank', 
        'pemasukanBulanIni', 
        'pengeluaranBulanIni', 
        'labaBersihBulanIni',
        'pendingCount',
        'verifiedCount',
        'accounts'
    ));
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Tindakan Verifikasi / Tolak Jurnal Akuntansi dari Dashboard
    Route::patch('/journal-entries/{journalEntry}/verify', function (\App\Models\JournalEntry $journalEntry) {
        $journalEntry->update(['status' => 'verified']);
        return back()->with('success', 'Transaksi ' . $journalEntry->reference . ' berhasil diverifikasi ke buku besar.');
    })->name('journal-entries.verify');

    Route::patch('/journal-entries/{journalEntry}/reject', function (\App\Models\JournalEntry $journalEntry) {
        $journalEntry->update(['status' => 'rejected']);
        return back()->with('warning', 'Transaksi ' . $journalEntry->reference . ' ditandai ditolak (rejected).');
    })->name('journal-entries.reject');
});

require __DIR__.'/auth.php';
