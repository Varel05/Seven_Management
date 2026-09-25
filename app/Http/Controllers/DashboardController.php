<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\RecurringTransaction;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Display the main dashboard view.
     */
    public function index(): View
    {
        $metrics = $this->calculateMetrics();

        return view('dashboard', $metrics);
    }

    /**
     * Return live data JSON for polling and real-time updates.
     */
    public function liveData(Request $request): JsonResponse
    {
        $latestEntry = JournalEntry::latest('id')->first();
        $latestEntryId = $latestEntry?->id ?? 0;
        $entriesCount = JournalEntry::count();
        $maxJournalUpdate = JournalEntry::max('updated_at') ?? '';
        $maxAccountUpdate = Account::max('updated_at') ?? '';
        $pendingCount = JournalEntry::where('status', 'pending')->count();
        $verifiedCount = JournalEntry::where('status', 'verified')->count();

        // Sidik jari untuk mendeteksi perubahan data sekecil apapun
        $currentHash = md5("{$latestEntryId}-{$entriesCount}-{$pendingCount}-{$verifiedCount}-{$maxJournalUpdate}-{$maxAccountUpdate}");

        $clientHash = $request->query('hash');
        $force = $request->boolean('force');

        if ($clientHash === $currentHash && ! $force) {
            return response()->json([
                'has_changes' => false,
                'hash' => $currentHash,
                'timestamp' => now()->translatedFormat('H:i:s'),
            ]);
        }

        $metrics = $this->calculateMetrics();
        $transactions = $metrics['transactions'];

        // Deteksi apakah ada transaksi baru dibanding ID terakhir di browser
        $hasClientLastId = $request->has('last_entry_id');
        $clientLastId = $request->integer('last_entry_id');
        $newTransactionInfo = null;

        if ($latestEntry && $hasClientLastId && $latestEntry->id > $clientLastId) {
            $firstLine = $latestEntry->lines->first();
            $expenseLine = $latestEntry->lines->first(fn ($l) => $l->account && $l->account->type === 'expense');
            $revenueLine = $latestEntry->lines->first(fn ($l) => $l->account && $l->account->type === 'revenue');
            $amount = $expenseLine
                ? ($expenseLine->debit ?? 0)
                : ($revenueLine->credit ?? $latestEntry->lines->sum('debit'));

            $newTransactionInfo = [
                'id' => $latestEntry->id,
                'reference' => $latestEntry->reference,
                'description' => $latestEntry->description,
                'amount' => (float) $amount,
                'amount_formatted' => 'Rp '.number_format((float) $amount, 0, ',', '.'),
                'source' => $latestEntry->source,
                'status' => $latestEntry->status,
                'is_telegram' => str_starts_with(strtolower($latestEntry->source ?? ''), 'telegram'),
            ];
        }

        $items = $transactions->map(function ($t) {
            $firstLine = $t->lines->first();
            $expenseLine = $t->lines->first(fn ($l) => $l->account && $l->account->type === 'expense');
            $revenueLine = $t->lines->first(fn ($l) => $l->account && $l->account->type === 'revenue');
            $accountName = $expenseLine ? ($expenseLine->account->name ?? '') : ($revenueLine ? ($revenueLine->account->name ?? '') : ($firstLine->account->name ?? ''));

            return [
                'id' => $t->id,
                'status' => $t->status,
                'search' => strtolower($t->description.' '.$t->reference.' '.$accountName),
            ];
        })->values();

        $tableHtml = view('dashboard.partials.transactions-table-body', [
            'transactions' => $transactions,
        ])->render();

        return response()->json([
            'has_changes' => true,
            'hash' => $currentHash,
            'timestamp' => now()->translatedFormat('H:i:s'),
            'latest_entry_id' => $latestEntryId,
            'new_transaction' => $newTransactionInfo,
            'kpi' => [
                'totalKasDanBank' => $metrics['totalKasDanBank'],
                'totalKasDanBank_formatted' => 'Rp '.number_format($metrics['totalKasDanBank'], 0, ',', '.'),
                'totalKas' => $metrics['totalKas'],
                'totalKas_formatted' => 'Rp '.number_format($metrics['totalKas'], 0, ',', '.'),
                'pemasukanBulanIni' => $metrics['pemasukanBulanIni'],
                'pemasukanBulanIni_formatted' => 'Rp '.number_format($metrics['pemasukanBulanIni'], 0, ',', '.'),
                'pengeluaranBulanIni' => $metrics['pengeluaranBulanIni'],
                'pengeluaranBulanIni_formatted' => 'Rp '.number_format($metrics['pengeluaranBulanIni'], 0, ',', '.'),
                'labaBersihBulanIni' => $metrics['labaBersihBulanIni'],
                'labaBersihBulanIni_formatted' => ($metrics['labaBersihBulanIni'] >= 0 ? '+' : '-').' Rp '.number_format(abs($metrics['labaBersihBulanIni']), 0, ',', '.'),
                'is_profit' => $metrics['labaBersihBulanIni'] >= 0,
                'isProfit' => $metrics['labaBersihBulanIni'] >= 0,
                'pendingCount' => $metrics['pendingCount'],
                'verifiedCount' => $metrics['verifiedCount'],
            ],
            'accounts' => $metrics['accounts'],
            'chartData' => $metrics['chartData'],
            'items' => $items,
            'table_html' => $tableHtml,
        ]);
    }

    /**
     * Compute all dashboard financial metrics and datasets.
     *
     * @return array<string, mixed>
     */
    protected function calculateMetrics(): array
    {
        $transactions = JournalEntry::with('lines.account')
            ->latest('date')
            ->get();

        // Saldo Kas & Bank (Semua Akun bertipe Asset, normal balance: Debit - Credit)
        $assetDebit = JournalEntryLine::whereHas('account', fn ($q) => $q->where('type', 'asset'))->sum('debit');
        $assetCredit = JournalEntryLine::whereHas('account', fn ($q) => $q->where('type', 'asset'))->sum('credit');
        $totalKasDanBank = (float) ($assetDebit - $assetCredit);

        // Khusus Kas Operasional (1001)
        $cashDebit = JournalEntryLine::whereHas('account', fn ($q) => $q->where('code', '1001'))->sum('debit');
        $cashCredit = JournalEntryLine::whereHas('account', fn ($q) => $q->where('code', '1001'))->sum('credit');
        $totalKas = (float) ($cashDebit - $cashCredit);

        // Pemasukan bulan ini (Akun Pendapatan/Revenue)
        $pemasukanBulanIni = (float) JournalEntryLine::whereHas('account', fn ($q) => $q->where('type', 'revenue'))
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('credit');

        // Pengeluaran bulan ini (Akun Beban/Expense)
        $pengeluaranBulanIni = (float) JournalEntryLine::whereHas('account', fn ($q) => $q->where('type', 'expense'))
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('debit');

        // Laba / Rugi Bersih bulan berjalan
        $labaBersihBulanIni = $pemasukanBulanIni - $pengeluaranBulanIni;

        // Transaksi Pending verifikasi (misal dari bot Telegram)
        $pendingCount = JournalEntry::where('status', 'pending')->count();
        $verifiedCount = JournalEntry::where('status', 'verified')->count();

        // Ringkasan Akun Utama (Chart of Accounts)
        $accounts = Account::with('lines')->get()->map(function ($acc) {
            $debit = $acc->lines->sum('debit');
            $credit = $acc->lines->sum('credit');
            $balance = in_array($acc->type, ['asset', 'expense']) ? ($debit - $credit) : ($credit - $debit);

            return [
                'id' => $acc->id,
                'code' => $acc->code,
                'name' => $acc->name,
                'type' => $acc->type,
                'balance' => (float) $balance,
                'trx_count' => $acc->lines->count(),
            ];
        });

        // Master Pengeluaran Berulang (Recurring Expenses)
        $recurringTransactions = RecurringTransaction::with(['expenseAccount', 'assetAccount'])
            ->latest()
            ->get();

        $expenseAccounts = Account::where('type', 'expense')->orderBy('code')->get();
        $assetAccounts = Account::where('type', 'asset')->orderBy('code')->get();

        // Data Diagram Garis Arus Keuangan (Pendapatan vs Pengeluaran: Harian Bulan Ini & Bulanan Tahun Ini)
        $currentYear = (int) now()->format('Y');
        $currentMonth = (int) now()->format('n');
        $daysInMonth = (int) now()->daysInMonth;

        $chartLines = JournalEntryLine::whereHas('account', function ($q) {
            $q->whereIn('type', ['revenue', 'expense']);
        })
            ->with(['account:id,type', 'journalEntry:id,date'])
            ->get()
            ->filter(function ($line) use ($currentYear) {
                $date = $line->journalEntry?->date ?? $line->created_at;

                return $date && (int) Carbon::parse($date)->format('Y') === $currentYear;
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
            $date = Carbon::parse($line->journalEntry?->date ?? $line->created_at);
            $month = (int) $date->format('n');
            $day = (int) $date->format('j');

            $isRev = $line->account?->type === 'revenue';
            $isExp = $line->account?->type === 'expense';
            $amount = $isRev ? (float) $line->credit : (float) $line->debit;

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
                'totalRevenue' => (float) array_sum($dailyRevenue),
                'totalExpense' => (float) array_sum($dailyExpense),
                'netProfit' => (float) (array_sum($dailyRevenue) - array_sum($dailyExpense)),
            ],
            'monthly' => [
                'period' => 'Tahun '.$currentYear,
                'labels' => $monthlyLabels,
                'revenue' => array_values($monthlyRevenue),
                'expense' => array_values($monthlyExpense),
                'totalRevenue' => (float) array_sum($monthlyRevenue),
                'totalExpense' => (float) array_sum($monthlyExpense),
                'netProfit' => (float) (array_sum($monthlyRevenue) - array_sum($monthlyExpense)),
            ],
        ];

        return [
            'transactions' => $transactions,
            'totalKas' => $totalKas,
            'totalKasDanBank' => $totalKasDanBank,
            'pemasukanBulanIni' => $pemasukanBulanIni,
            'pengeluaranBulanIni' => $pengeluaranBulanIni,
            'labaBersihBulanIni' => $labaBersihBulanIni,
            'pendingCount' => $pendingCount,
            'verifiedCount' => $verifiedCount,
            'accounts' => $accounts,
            'recurringTransactions' => $recurringTransactions,
            'expenseAccounts' => $expenseAccounts,
            'assetAccounts' => $assetAccounts,
            'chartData' => $chartData,
        ];
    }
}
