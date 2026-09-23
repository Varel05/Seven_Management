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
            'id'        => $acc->id,
            'code'      => $acc->code,
            'name'      => $acc->name,
            'type'      => $acc->type,
            'balance'   => (float)$balance,
            'trx_count' => $acc->lines->count(),
        ];
    });

    // Master Pengeluaran Berulang (Recurring Expenses)
    $recurringTransactions = \App\Models\RecurringTransaction::with(['expenseAccount', 'assetAccount'])
        ->latest()
        ->get();

    $expenseAccounts = \App\Models\Account::where('type', 'expense')->orderBy('code')->get();
    $assetAccounts = \App\Models\Account::where('type', 'asset')->orderBy('code')->get();

    // Data Diagram Garis Arus Keuangan (Pendapatan vs Pengeluaran: Harian Bulan Ini & Bulanan Tahun Ini)
    $currentYear = (int)now()->format('Y');
    $currentMonth = (int)now()->format('n');
    $daysInMonth = (int)now()->daysInMonth;

    $chartLines = \App\Models\JournalEntryLine::whereHas('account', function($q) {
        $q->whereIn('type', ['revenue', 'expense']);
    })
    ->with(['account:id,type', 'journalEntry:id,date'])
    ->get()
    ->filter(function ($line) use ($currentYear) {
        $date = $line->journalEntry?->date ?? $line->created_at;
        return $date && (int)\Carbon\Carbon::parse($date)->format('Y') === $currentYear;
    });

    $dailyLabels = [];
    $dailyRevenue = array_fill(1, $daysInMonth, 0.0);
    $dailyExpense = array_fill(1, $daysInMonth, 0.0);

    for ($d = 1; $d <= $daysInMonth; $d++) {
        $dailyLabels[] = sprintf('%02d %s', $d, now()->translatedFormat('M'));
    }

    $monthlyLabels = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    $monthlyRevenue = array_fill(1, 12, 0.0);
    $monthlyExpense = array_fill(1, 12, 0.0);

    foreach ($chartLines as $line) {
        $date = \Carbon\Carbon::parse($line->journalEntry?->date ?? $line->created_at);
        $month = (int)$date->format('n');
        $day = (int)$date->format('j');

        $isRev = $line->account?->type === 'revenue';
        $isExp = $line->account?->type === 'expense';
        $amount = $isRev ? (float)$line->credit : (float)$line->debit;

        // Akumulasi bulanan
        if ($isRev) {
            $monthlyRevenue[$month] = ($monthlyRevenue[$month] ?? 0) + $amount;
        } elseif ($isExp) {
            $monthlyExpense[$month] = ($monthlyExpense[$month] ?? 0) + $amount;
        }

        // Akumulasi harian (khusus bulan berjalan)
        if ($month === $currentMonth) {
            if ($isRev) {
                $dailyRevenue[$day] = ($dailyRevenue[$day] ?? 0) + $amount;
            } elseif ($isExp) {
                $dailyExpense[$day] = ($dailyExpense[$day] ?? 0) + $amount;
            }
        }
    }

    $chartData = [
        'daily' => [
            'period' => now()->translatedFormat('F Y'),
            'labels' => $dailyLabels,
            'revenue' => array_values($dailyRevenue),
            'expense' => array_values($dailyExpense),
            'totalRevenue' => (float)array_sum($dailyRevenue),
            'totalExpense' => (float)array_sum($dailyExpense),
            'netProfit' => (float)(array_sum($dailyRevenue) - array_sum($dailyExpense)),
        ],
        'monthly' => [
            'period' => 'Tahun ' . $currentYear,
            'labels' => $monthlyLabels,
            'revenue' => array_values($monthlyRevenue),
            'expense' => array_values($monthlyExpense),
            'totalRevenue' => (float)array_sum($monthlyRevenue),
            'totalExpense' => (float)array_sum($monthlyExpense),
            'netProfit' => (float)(array_sum($monthlyRevenue) - array_sum($monthlyExpense)),
        ],
    ];

    return view('dashboard', compact(
        'transactions', 
        'totalKas', 
        'totalKasDanBank', 
        'pemasukanBulanIni', 
        'pengeluaranBulanIni', 
        'labaBersihBulanIni',
        'pendingCount',
        'verifiedCount',
        'accounts',
        'recurringTransactions',
        'expenseAccounts',
        'assetAccounts',
        'chartData'
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

    // Manajemen Bagan Akun Keuangan (Chart of Accounts)
    Route::post('/accounts', [\App\Http\Controllers\AccountController::class, 'store'])->name('accounts.store');
    Route::put('/accounts/{account}', [\App\Http\Controllers\AccountController::class, 'update'])->name('accounts.update');
    Route::delete('/accounts/{account}', [\App\Http\Controllers\AccountController::class, 'destroy'])->name('accounts.destroy');

    // Manajemen Pengeluaran Rutin & Langganan (Recurring Transactions)
    Route::post('/recurring-transactions', [\App\Http\Controllers\RecurringTransactionController::class, 'store'])->name('recurring-transactions.store');
    Route::put('/recurring-transactions/{recurringTransaction}', [\App\Http\Controllers\RecurringTransactionController::class, 'update'])->name('recurring-transactions.update');
    Route::delete('/recurring-transactions/{recurringTransaction}', [\App\Http\Controllers\RecurringTransactionController::class, 'destroy'])->name('recurring-transactions.destroy');
    Route::post('/recurring-transactions/{recurringTransaction}/approve', [\App\Http\Controllers\RecurringTransactionController::class, 'approve'])->name('recurring-transactions.approve');

    // Manajemen Data Karyawan & Payroll
    Route::get('/employees', [\App\Http\Controllers\EmployeeController::class, 'index'])->name('employees.index');
    Route::post('/employees', [\App\Http\Controllers\EmployeeController::class, 'store'])->name('employees.store');
    Route::put('/employees/{employee}', [\App\Http\Controllers\EmployeeController::class, 'update'])->name('employees.update');
    Route::patch('/employees/{employee}/points', [\App\Http\Controllers\EmployeeController::class, 'updatePoints'])->name('employees.points');
    Route::delete('/employees/{employee}', [\App\Http\Controllers\EmployeeController::class, 'destroy'])->name('employees.destroy');
    Route::post('/employees/{employee}/pay', [\App\Http\Controllers\EmployeeController::class, 'pay'])->name('employees.pay');
});

require __DIR__.'/auth.php';
