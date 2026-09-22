<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\RecurringTransaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WebhookTransactionController extends Controller
{
    /**
     * Display a listing of recent journal transactions.
     */
    public function index()
    {
        $transactions = JournalEntry::with('lines.account')
            ->latest('date')
            ->limit(20)
            ->get();

        return response()->json([
            'status' => true,
            'data' => $transactions,
        ]);
    }

    /**
     * Store a newly created transaction from n8n / webhook.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'description'          => 'required|string|max:255',
            'amount'               => 'nullable|min:0',
            'type'                 => 'nullable|string',        // normalize type
            'date'                 => 'nullable|string',
            'source'               => 'nullable|string|max:50',
            'reference'            => 'nullable|string|max:100',
            'account'              => 'nullable|string|max:100',
            'account_code'         => 'nullable|string|max:20',
            'category'             => 'nullable|string|max:100',
            'status'               => 'nullable|string|in:pending,verified,rejected',
            'lines'                => 'nullable|array',
            'lines.*.account_code' => 'required_with:lines|string',
            'lines.*.debit'        => 'nullable|numeric|min:0',
            'lines.*.credit'       => 'nullable|numeric|min:0',
            'lines.*.description'  => 'nullable|string',
        ]);

        // Cast amount ke float
        $validated['amount'] = (float) ($validated['amount'] ?? 0);

        // Normalize type — support bahasa Indonesia & Inggris
        $typeMap = [
            'pengeluaran' => 'expense',
            'pemasukan'   => 'income',
            'masuk'       => 'income',
            'keluar'      => 'expense',
            'transfer'    => 'transfer',
            'expense'     => 'expense',
            'income'      => 'income',
        ];

        $rawType = strtolower(trim($validated['type'] ?? 'expense'));
        $validated['type'] = $typeMap[$rawType] ?? 'expense';

        return DB::transaction(function () use ($validated) {
            $transactionDate = now();
            if (!empty($validated['date'])) {
                try {
                    $parsed = Carbon::parse($validated['date']);
                    // Hanya gunakan jika tahunnya valid (minimal tahun ini) agar terhindar dari halusinasi model AI
                    if ($parsed->year >= now()->year) {
                        $transactionDate = $parsed;
                    }
                } catch (\Exception $e) {
                    $transactionDate = now();
                }
            }

            $reference = $validated['reference'] ?? ('TG-' . strtoupper(Str::random(8)));

            $journalEntry = JournalEntry::create([
                'reference'   => $reference,
                'description' => $validated['description'],
                'date'        => $transactionDate,
                'source'      => $validated['source'] ?? 'telegram',
                'status'      => $validated['status'] ?? 'verified',
            ]);

            // Double-entry mode jika lines eksplisit disediakan
            if (!empty($validated['lines']) && count($validated['lines']) > 0) {
                foreach ($validated['lines'] as $line) {
                    $account = Account::firstOrCreate(
                        ['code' => $line['account_code']],
                        [
                            'name' => $line['account_name'] ?? ('Akun ' . $line['account_code']),
                            'type' => 'expense',
                        ]
                    );

                    JournalEntryLine::create([
                        'journal_entry_id' => $journalEntry->id,
                        'account_id'       => $account->id,
                        'description'      => $line['description'] ?? $journalEntry->description,
                        'debit'            => $line['debit'] ?? 0,
                        'credit'           => $line['credit'] ?? 0,
                    ]);
                }
            } else {
                // Simple mode: auto generate double-entry sesuai database Chart of Accounts
                $amount = $validated['amount'];
                $type   = $validated['type'];

                $assetAccount = $this->resolveAssetAccount($validated);

                if ($type === 'expense') {
                    $expenseAccount = $this->resolveCategoryAccount($validated, 'expense');

                    // Debit Akun Beban, Credit Akun Kas/Bank
                    JournalEntryLine::create([
                        'journal_entry_id' => $journalEntry->id,
                        'account_id'       => $expenseAccount->id,
                        'description'      => $journalEntry->description,
                        'debit'            => $amount,
                        'credit'           => 0,
                    ]);

                    JournalEntryLine::create([
                        'journal_entry_id' => $journalEntry->id,
                        'account_id'       => $assetAccount->id,
                        'description'      => 'Pembayaran via ' . $assetAccount->name,
                        'debit'            => 0,
                        'credit'           => $amount,
                    ]);

                } elseif ($type === 'income') {
                    $incomeAccount = $this->resolveCategoryAccount($validated, 'income');

                    // Debit Akun Kas/Bank (Asset bertambah), Credit Akun Pendapatan
                    JournalEntryLine::create([
                        'journal_entry_id' => $journalEntry->id,
                        'account_id'       => $assetAccount->id,
                        'description'      => 'Penerimaan ke ' . $assetAccount->name,
                        'debit'            => $amount,
                        'credit'           => 0,
                    ]);

                    JournalEntryLine::create([
                        'journal_entry_id' => $journalEntry->id,
                        'account_id'       => $incomeAccount->id,
                        'description'      => $journalEntry->description,
                        'debit'            => 0,
                        'credit'           => $amount,
                    ]);
                }
            }

            return response()->json([
                'status'  => true,
                'message' => 'Transaksi berhasil dicatat ke sistem akuntansi',
                'data'    => $journalEntry->load('lines.account'),
            ], 201);
        });
    }

    /**
     * Cari akun Asset (Kas / Bank) yang paling cocok dari database.
     */
    private function resolveAssetAccount(array $validated): Account
    {
        $assetAccounts = Account::where('type', 'asset')->get();

        // 1. Cek jika kode akun langsung diberikan dan cocok dengan akun asset
        if (!empty($validated['account_code'])) {
            $found = $assetAccounts->firstWhere('code', $validated['account_code']);
            if ($found) return $found;
        }

        // 2. Gabungkan teks petunjuk (account, category, description)
        $searchSources = [
            trim($validated['account'] ?? ''),
            trim($validated['category'] ?? ''),
            trim($validated['description'] ?? ''),
        ];
        $searchSources = array_filter($searchSources);
        $fullSearchText = strtolower(implode(' ', $searchSources));

        $bestAccount = null;
        $highestScore = 0;

        foreach ($assetAccounts as $acc) {
            $score = 0;
            $accNameLower = strtolower($acc->name);
            $accCodeLower = strtolower($acc->code);

            // A. Kecocokan kode akun eksak
            if (preg_match('/\b' . preg_quote($accCodeLower, '/') . '\b/', $fullSearchText)) {
                $score += 100;
            }

            // B. Kecocokan nama lengkap akun (misal "bank bca", "kas operasional")
            if (str_contains($fullSearchText, $accNameLower)) {
                $score += 80;
            }

            // C. Kecocokan kata kunci unik (misal "bca", "mandiri", "bri", "gopay", "ovo")
            $cleanName = trim(str_replace(['bank', 'kas', 'dompet', 'rekening'], '', $accNameLower));
            if (!empty($cleanName)) {
                // Split jika ada beberapa kata
                $keywords = array_filter(explode(' ', $cleanName));
                foreach ($keywords as $kw) {
                    if (strlen($kw) >= 2 && preg_match('/\b' . preg_quote($kw, '/') . '\b/i', $fullSearchText)) {
                        $score += 60;
                    }
                }
            }

            // D. Kecocokan kata "kas" atau "tunai"
            if (str_contains($accNameLower, 'kas') && (preg_match('/\b(kas|tunai|cash)\b/i', $fullSearchText))) {
                $score += 30;
            }

            if ($score > $highestScore) {
                $highestScore = $score;
                $bestAccount = $acc;
            }
        }

        if ($bestAccount && $highestScore > 0) {
            return $bestAccount;
        }

        // Fallback default: Kas Operasional (1001)
        return Account::firstOrCreate(
            ['code' => '1001'],
            ['name' => 'Kas Operasional', 'type' => 'asset']
        );
    }

    /**
     * Cari akun Kategori (Beban atau Pendapatan) yang paling cocok dari database.
     */
    private function resolveCategoryAccount(array $validated, string $type): Account
    {
        $targetType = $type === 'income' ? 'revenue' : 'expense';
        $accounts = Account::where('type', $targetType)->get();

        $searchSources = [
            trim($validated['category'] ?? ''),
            trim($validated['description'] ?? ''),
        ];
        $searchSources = array_filter($searchSources);
        $fullSearchText = strtolower(implode(' ', $searchSources));

        // 1. Cek jika kode akun langsung diberikan dan cocok
        if (!empty($validated['account_code'])) {
            $found = $accounts->firstWhere('code', $validated['account_code']);
            if ($found) return $found;
        }

        $bestAccount = null;
        $highestScore = 0;

        foreach ($accounts as $acc) {
            $score = 0;
            $accNameLower = strtolower($acc->name);
            $accCodeLower = strtolower($acc->code);

            // A. Kecocokan kode akun eksak
            if (preg_match('/\b' . preg_quote($accCodeLower, '/') . '\b/', $fullSearchText)) {
                $score += 100;
            }

            // B. Kecocokan nama lengkap
            if (str_contains($fullSearchText, $accNameLower)) {
                $score += 80;
            }

            // C. Kata kunci inti akun (misal "gaji", "listrik", "perlengkapan", "kantor", "sewa")
            $cleanName = trim(str_replace(['beban', 'biaya', 'pendapatan', 'akun'], '', $accNameLower));
            $keywords = array_filter(explode(' ', $cleanName));
            foreach ($keywords as $kw) {
                if (strlen($kw) >= 3 && preg_match('/\b' . preg_quote($kw, '/') . '\b/i', $fullSearchText)) {
                    $score += 50;
                }
            }

            if ($score > $highestScore) {
                $highestScore = $score;
                $bestAccount = $acc;
            }
        }

        if ($bestAccount && $highestScore > 0) {
            return $bestAccount;
        }

        // Fallback default
        if ($type === 'income') {
            return Account::firstOrCreate(
                ['code' => '4001'],
                ['name' => 'Pendapatan Usaha', 'type' => 'revenue']
            );
        }

        return Account::firstOrCreate(
            ['code' => '5001'],
            ['name' => 'Beban Operasional', 'type' => 'expense']
        );
    }

    /**
     * Get list of recurring expenses that are due today and upcoming this month.
     */
    public function dueRecurring(Request $request)
    {
        $today = Carbon::today();
        $now = Carbon::now();
        $monthName = $now->translatedFormat('F Y');
        $daysInMonth = (int) $today->daysInMonth;

        $allActive = RecurringTransaction::with(['expenseAccount', 'assetAccount'])
            ->active()
            ->orderBy('day_of_month')
            ->get();

        $dueToday = [];
        $overdue = [];
        $upcoming = [];
        $alreadyPaid = [];

        foreach ($allActive as $item) {
            $targetDay = min((int) $item->day_of_month, $daysInMonth);
            $targetDate = Carbon::create($today->year, $today->month, $targetDay)->startOfDay();

            // Cek relevansi frekuensi untuk bulan ini
            $isThisMonth = false;
            if ($item->frequency === 'monthly') {
                $isThisMonth = true;
            } elseif ($item->frequency === 'yearly') {
                $targetMonth = (int) ($item->month_of_year ?? 1);
                $isThisMonth = ($today->month === $targetMonth);
            } elseif ($item->frequency === 'weekly') {
                $isThisMonth = true;
            }

            if (!$isThisMonth) {
                continue;
            }

            $alreadyPostedThisMonth = $item->last_posted_at 
                && $item->last_posted_at->isCurrentMonth() 
                && $item->last_posted_at->isCurrentYear();

            $daysLeft = (int) $today->diffInDays($targetDate, false);

            $itemData = [
                'id'                   => $item->id,
                'name'                 => $item->name,
                'amount'               => (float) $item->amount,
                'formatted_amount'     => 'Rp ' . number_format($item->amount, 0, ',', '.'),
                'frequency'            => $item->frequency,
                'day_of_month'         => $item->day_of_month,
                'due_date'             => $targetDate->translatedFormat('d F Y'),
                'days_left'            => $daysLeft,
                'expense_account_name' => $item->expenseAccount->name ?? 'Beban Operasional',
                'asset_account_name'   => $item->assetAccount->name ?? 'Kas Operasional',
                'notes'                => $item->notes,
                'last_posted_at'       => $item->last_posted_at ? $item->last_posted_at->translatedFormat('d M Y') : null,
            ];

            if ($alreadyPostedThisMonth) {
                $alreadyPaid[] = $itemData;
            } else {
                if ($today->isSameDay($targetDate)) {
                    $dueToday[] = $itemData;
                } elseif ($targetDate->isPast()) {
                    $overdue[] = $itemData;
                } else {
                    $upcoming[] = $itemData;
                }
            }
        }

        // Actionable items yang membutuhkan tombol konfirmasi bayar
        $actionable = array_merge($dueToday, $overdue);

        if ($request->boolean('mark_notified', false)) {
            $actionableIds = array_column($actionable, 'id');
            RecurringTransaction::whereIn('id', $actionableIds)->update(['last_notified_at' => now()]);
        }

        // Susun teks respon Telegram yang informatif
        $messageLines = ["📅 *Jadwal Pengeluaran Rutin ({$monthName})*\n"];

        if (!empty($dueToday)) {
            $messageLines[] = "🔴 *Jatuh Tempo Hari Ini (Perlu Dibayar):*";
            foreach ($dueToday as $d) {
                $messageLines[] = "• #{$d['id']} *{$d['name']}*: {$d['formatted_amount']}";
            }
            $messageLines[] = "";
        }

        if (!empty($overdue)) {
            $messageLines[] = "⚠️ *Terlewat (Belum Dibayar):*";
            foreach ($overdue as $o) {
                $messageLines[] = "• #{$o['id']} *{$o['name']}*: {$o['formatted_amount']} (Tgl {$o['day_of_month']} {$now->translatedFormat('M')})";
            }
            $messageLines[] = "";
        }

        if (!empty($actionable)) {
            $firstId = $actionable[0]['id'];
            $firstName = strtolower(explode(' ', $actionable[0]['name'])[0]);
            $messageLines[] = "💡 *Cara Bayar Cepat:*";
            $messageLines[] = "• Klik tombol `[✅ Bayar Sekarang]`";
            $messageLines[] = "• ATAU ketik manual: `/bayar {$firstId}` atau `/bayar {$firstName}`";
            $messageLines[] = "• Untuk lewati: `/lewati {$firstId}`";
            $messageLines[] = "";
        }

        if (!empty($upcoming)) {
            $messageLines[] = "⏳ *Mendatang Bulan Ini:*";
            foreach ($upcoming as $u) {
                $daysLeft = (int) $u['days_left'];
                $daysText = $daysLeft === 1 ? 'Besok' : "{$daysLeft} hari lagi";
                $messageLines[] = "• #{$u['id']} *{$u['name']}*: {$u['formatted_amount']} (Tgl {$u['day_of_month']} {$now->translatedFormat('M')} • {$daysText})";
            }
            $messageLines[] = "";
        }

        if (!empty($alreadyPaid)) {
            $messageLines[] = "🟢 *Sudah Dibayar Bulan Ini:*";
            foreach ($alreadyPaid as $p) {
                $messageLines[] = "• #{$p['id']} *{$p['name']}*: {$p['formatted_amount']} (Dibayar {$p['last_posted_at']})";
            }
            $messageLines[] = "";
        }

        if (empty($dueToday) && empty($overdue) && empty($upcoming) && empty($alreadyPaid)) {
            $messageLines[] = "ℹ️ Belum ada jadwal pengeluaran rutin untuk bulan ini.\n";
        }

        $totalAll = array_sum(array_column($dueToday, 'amount'))
                  + array_sum(array_column($overdue, 'amount'))
                  + array_sum(array_column($upcoming, 'amount'))
                  + array_sum(array_column($alreadyPaid, 'amount'));

        $messageLines[] = "───────────────────";
        $messageLines[] = "💰 *Total Rutin Bulan Ini*: Rp " . number_format($totalAll, 0, ',', '.');

        return response()->json([
            'status'                 => true,
            'period'                 => $monthName,
            'count'                  => count($actionable),
            'data'                   => $actionable,
            'due_today'              => $dueToday,
            'overdue'                => $overdue,
            'upcoming'               => $upcoming,
            'already_paid'           => $alreadyPaid,
            'total_amount'           => $totalAll,
            'formatted_total_amount' => 'Rp ' . number_format($totalAll, 0, ',', '.'),
            'message'                => implode("\n", $messageLines),
        ]);
    }

    /**
     * Approve and execute posting for a recurring expense via webhook (e.g. Telegram button click).
     */
    public function approveRecurring(Request $request, RecurringTransaction $recurringTransaction)
    {
        $customAmount = $request->filled('amount') ? (float) $request->input('amount') : null;
        $source = $request->input('source', 'telegram_approval');

        $journalEntry = $recurringTransaction->executePosting($customAmount, $source);

        return response()->json([
            'status'  => true,
            'message' => "Pengeluaran rutin '{$recurringTransaction->name}' berhasil dibukukan.",
            'data'    => [
                'reference'       => $journalEntry->reference,
                'name'            => $recurringTransaction->name,
                'amount'          => $customAmount ?: (float) $recurringTransaction->amount,
                'expense_account' => $recurringTransaction->expenseAccount->name ?? '',
                'asset_account'   => $recurringTransaction->assetAccount->name ?? '',
                'journal_entry'   => $journalEntry,
            ],
        ], 201);
    }

    /**
     * Skip this period for a recurring expense via webhook.
     */
    public function skipRecurring(Request $request, RecurringTransaction $recurringTransaction)
    {
        $recurringTransaction->update(['last_posted_at' => now()]);

        return response()->json([
            'status'  => true,
            'message' => "Pengeluaran rutin '{$recurringTransaction->name}' telah dilewati untuk periode ini.",
        ]);
    }

    /**
     * Handle manual text command to approve or skip a recurring expense (e.g. /bayar 1 or /bayar wifi).
     */
    public function manualRecurringAction(Request $request)
    {
        $validated = $request->validate([
            'query'  => 'required|string',
            'action' => 'nullable|string|in:approve,skip',
            'amount' => 'nullable|numeric|min:0',
        ]);

        $query = trim($validated['query']);
        $action = strtolower($validated['action'] ?? 'approve');

        // Cari berdasarkan ID jika berupa angka
        $recurring = null;
        if (is_numeric($query)) {
            $recurring = RecurringTransaction::with(['expenseAccount', 'assetAccount'])->find((int) $query);
        }

        // Cari berdasarkan nama jika belum ditemukan
        if (!$recurring && !empty($query)) {
            $recurring = RecurringTransaction::with(['expenseAccount', 'assetAccount'])
                ->active()
                ->where('name', 'LIKE', "%{$query}%")
                ->first();
        }

        if (!$recurring) {
            return response()->json([
                'status'  => false,
                'message' => "❌ Tagihan dengan kata kunci '{$query}' tidak ditemukan.\n\n💡 Ketik /rutin untuk melihat daftar nama & ID tagihan yang aktif.",
            ], 404);
        }

        if ($action === 'skip') {
            $recurring->update(['last_posted_at' => now()]);
            return response()->json([
                'status'  => true,
                'message' => "⏭️ Pengeluaran rutin '#{$recurring->id} {$recurring->name}' telah dilewati untuk periode ini.",
            ]);
        }

        // Eksekusi posting akuntansi (double-entry)
        $customAmount = !empty($validated['amount']) ? (float) $validated['amount'] : null;
        $journalEntry = $recurring->executePosting($customAmount, 'telegram_manual_command');

        $finalAmount = $customAmount ?: (float) $recurring->amount;
        $formattedAmount = 'Rp ' . number_format($finalAmount, 0, ',', '.');
        $assetName = $recurring->assetAccount->name ?? 'Kas Operasional';
        $expenseName = $recurring->expenseAccount->name ?? 'Beban Operasional';

        return response()->json([
            'status'  => true,
            'message' => "✅ *Pengeluaran Rutin Berhasil Dibukukan!*\n\n" .
                         "🏢 *#{$recurring->id} {$recurring->name}*\n" .
                         "💰 *{$formattedAmount}*\n" .
                         "📂 Beban: {$expenseName}\n" .
                         "💳 Bayar dari: {$assetName}\n" .
                         "🔖 Ref: `{$journalEntry->reference}`",
            'data'    => [
                'reference' => $journalEntry->reference,
                'name'      => $recurring->name,
                'amount'    => $finalAmount,
            ],
        ], 201);
    }

    /**
     * Get real-time balance of all asset (cash & bank) accounts.
     */
    public function balance(Request $request)
    {
        $accounts = Account::where('type', 'asset')
            ->with('lines')
            ->orderBy('code')
            ->get()
            ->map(function ($acc) {
                $debit = (float) $acc->lines->sum('debit');
                $credit = (float) $acc->lines->sum('credit');
                $balance = $debit - $credit;
                return [
                    'id'                => $acc->id,
                    'code'              => $acc->code,
                    'name'              => $acc->name,
                    'balance'           => $balance,
                    'formatted_balance' => 'Rp ' . number_format($balance, 0, ',', '.'),
                ];
            });

        $totalDebit = (float) JournalEntryLine::whereHas('account', fn($q) => $q->where('type', 'asset'))->sum('debit');
        $totalCredit = (float) JournalEntryLine::whereHas('account', fn($q) => $q->where('type', 'asset'))->sum('credit');
        $totalLiquidity = $totalDebit - $totalCredit;

        $messageLines = ["💳 *Saldo Kas & Bank Terkini:*\n"];
        foreach ($accounts as $acc) {
            $messageLines[] = "• *{$acc['name']}*: {$acc['formatted_balance']}";
        }
        $messageLines[] = "\n───────────────────";
        $messageLines[] = "💰 *Total Aset Likuid*: Rp " . number_format($totalLiquidity, 0, ',', '.');

        return response()->json([
            'status'          => true,
            'total'           => $totalLiquidity,
            'formatted_total' => 'Rp ' . number_format($totalLiquidity, 0, ',', '.'),
            'accounts'        => $accounts,
            'message'         => implode("\n", $messageLines),
        ]);
    }

    /**
     * Get month-to-date income, expenses, and net profit summary.
     */
    public function summary(Request $request)
    {
        $now = Carbon::now();
        $monthName = $now->translatedFormat('F Y');

        $revenue = (float) JournalEntryLine::whereHas('account', fn($q) => $q->where('type', 'revenue'))
            ->whereMonth('created_at', $now->month)
            ->whereYear('created_at', $now->year)
            ->sum('credit');

        $expense = (float) JournalEntryLine::whereHas('account', fn($q) => $q->where('type', 'expense'))
            ->whereMonth('created_at', $now->month)
            ->whereYear('created_at', $now->year)
            ->sum('debit');

        $netProfit = $revenue - $expense;
        $pendingCount = JournalEntry::where('status', 'pending')->count();
        $verifiedCount = JournalEntry::where('status', 'verified')->count();

        $messageLines = [
            "📊 *Ringkasan Keuangan ({$monthName})*\n",
            "🟢 *Pemasukan*: Rp " . number_format($revenue, 0, ',', '.'),
            "🔴 *Pengeluaran*: Rp " . number_format($expense, 0, ',', '.'),
            "───────────────────",
            ($netProfit >= 0 ? "📈" : "📉") . " *Laba Bersih*: Rp " . number_format($netProfit, 0, ',', '.'),
            "\n⏳ *Status Transaksi Bulan Ini*:",
            "• Menunggu Verifikasi: {$pendingCount}",
            "• Terverifikasi: {$verifiedCount}",
        ];

        return response()->json([
            'status'         => true,
            'period'         => $monthName,
            'revenue'        => $revenue,
            'expense'        => $expense,
            'net_profit'     => $netProfit,
            'pending_count'  => $pendingCount,
            'verified_count' => $verifiedCount,
            'message'        => implode("\n", $messageLines),
        ]);
    }
}