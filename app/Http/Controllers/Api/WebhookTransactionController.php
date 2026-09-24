<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\CustomSuitOrder;
use App\Models\Employee;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\Product;
use App\Models\RecurringTransaction;
use App\Models\RetailSale;
use App\Models\RetailSaleItem;
use App\Services\SuitMaterialEstimatorService;
use Illuminate\Http\Request;
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
            'description' => 'required|string|max:255',
            'amount' => 'nullable|min:0',
            'type' => 'nullable|string',        // normalize type
            'date' => 'nullable|string',
            'source' => 'nullable|string|max:50',
            'reference' => 'nullable|string|max:100',
            'account' => 'nullable|string|max:100',
            'account_code' => 'nullable|string|max:20',
            'category' => 'nullable|string|max:100',
            'status' => 'nullable|string|in:pending,verified,rejected',
            'lines' => 'nullable|array',
            'lines.*.account_code' => 'required_with:lines|string',
            'lines.*.debit' => 'nullable|numeric|min:0',
            'lines.*.credit' => 'nullable|numeric|min:0',
            'lines.*.description' => 'nullable|string',
        ]);

        // Cast amount ke float
        $validated['amount'] = (float) ($validated['amount'] ?? 0);

        // Normalize type — support bahasa Indonesia & Inggris
        $typeMap = [
            'pengeluaran' => 'expense',
            'pemasukan' => 'income',
            'masuk' => 'income',
            'keluar' => 'expense',
            'transfer' => 'transfer',
            'expense' => 'expense',
            'income' => 'income',
        ];

        $rawType = strtolower(trim($validated['type'] ?? 'expense'));
        $validated['type'] = $typeMap[$rawType] ?? 'expense';

        return DB::transaction(function () use ($validated) {
            $transactionDate = now();
            if (! empty($validated['date'])) {
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

            $reference = $validated['reference'] ?? ('TG-'.strtoupper(Str::random(8)));

            $rawSource = $validated['source'] ?? 'telegram';
            $source = str_starts_with(strtolower($rawSource), 'telegram') ? 'telegram' : $rawSource;

            $journalEntry = JournalEntry::create([
                'reference' => $reference,
                'description' => $validated['description'],
                'date' => $transactionDate,
                'source' => $source,
                'status' => $validated['status'] ?? 'verified',
            ]);

            // Double-entry mode jika lines eksplisit disediakan
            if (! empty($validated['lines']) && count($validated['lines']) > 0) {
                foreach ($validated['lines'] as $line) {
                    $account = Account::firstOrCreate(
                        ['code' => $line['account_code']],
                        [
                            'name' => $line['account_name'] ?? ('Akun '.$line['account_code']),
                            'type' => 'expense',
                        ]
                    );

                    JournalEntryLine::create([
                        'journal_entry_id' => $journalEntry->id,
                        'account_id' => $account->id,
                        'description' => $line['description'] ?? $journalEntry->description,
                        'debit' => $line['debit'] ?? 0,
                        'credit' => $line['credit'] ?? 0,
                    ]);
                }
            } else {
                // Simple mode: auto generate double-entry sesuai database Chart of Accounts
                $amount = $validated['amount'];
                $type = $validated['type'];

                $assetAccount = $this->resolveAssetAccount($validated);

                if ($type === 'expense') {
                    $expenseAccount = $this->resolveCategoryAccount($validated, 'expense');

                    // Debit Akun Beban, Credit Akun Kas/Bank
                    JournalEntryLine::create([
                        'journal_entry_id' => $journalEntry->id,
                        'account_id' => $expenseAccount->id,
                        'description' => $journalEntry->description,
                        'debit' => $amount,
                        'credit' => 0,
                    ]);

                    JournalEntryLine::create([
                        'journal_entry_id' => $journalEntry->id,
                        'account_id' => $assetAccount->id,
                        'description' => 'Pembayaran via '.$assetAccount->name,
                        'debit' => 0,
                        'credit' => $amount,
                    ]);

                } elseif ($type === 'income') {
                    $incomeAccount = $this->resolveCategoryAccount($validated, 'income');

                    // Debit Akun Kas/Bank (Asset bertambah), Credit Akun Pendapatan
                    JournalEntryLine::create([
                        'journal_entry_id' => $journalEntry->id,
                        'account_id' => $assetAccount->id,
                        'description' => 'Penerimaan ke '.$assetAccount->name,
                        'debit' => $amount,
                        'credit' => 0,
                    ]);

                    JournalEntryLine::create([
                        'journal_entry_id' => $journalEntry->id,
                        'account_id' => $incomeAccount->id,
                        'description' => $journalEntry->description,
                        'debit' => 0,
                        'credit' => $amount,
                    ]);
                }
            }

            return response()->json([
                'status' => true,
                'message' => 'Transaksi berhasil dicatat ke sistem akuntansi',
                'data' => $journalEntry->load('lines.account'),
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
        if (! empty($validated['account_code'])) {
            $found = $assetAccounts->firstWhere('code', $validated['account_code']);
            if ($found) {
                return $found;
            }
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
            if (preg_match('/\b'.preg_quote($accCodeLower, '/').'\b/', $fullSearchText)) {
                $score += 100;
            }

            // B. Kecocokan nama lengkap akun (misal "bank bca", "kas operasional")
            if (str_contains($fullSearchText, $accNameLower)) {
                $score += 80;
            }

            // C. Kecocokan kata kunci unik (misal "bca", "mandiri", "bri", "gopay", "ovo")
            $cleanName = trim(str_replace(['bank', 'kas', 'dompet', 'rekening'], '', $accNameLower));
            if (! empty($cleanName)) {
                // Split jika ada beberapa kata
                $keywords = array_filter(explode(' ', $cleanName));
                foreach ($keywords as $kw) {
                    if (strlen($kw) >= 2 && preg_match('/\b'.preg_quote($kw, '/').'\b/i', $fullSearchText)) {
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
        if (! empty($validated['account_code'])) {
            $found = $accounts->firstWhere('code', $validated['account_code']);
            if ($found) {
                return $found;
            }
        }

        $bestAccount = null;
        $highestScore = 0;

        foreach ($accounts as $acc) {
            $score = 0;
            $accNameLower = strtolower($acc->name);
            $accCodeLower = strtolower($acc->code);

            // A. Kecocokan kode akun eksak
            if (preg_match('/\b'.preg_quote($accCodeLower, '/').'\b/', $fullSearchText)) {
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
                if (strlen($kw) >= 3 && preg_match('/\b'.preg_quote($kw, '/').'\b/i', $fullSearchText)) {
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
    /**
     * Get list of recurring expenses and employee payroll that are due today and upcoming this month.
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

            if (! $isThisMonth) {
                continue;
            }

            $alreadyPostedThisMonth = $item->last_posted_at
                && $item->last_posted_at->isCurrentMonth()
                && $item->last_posted_at->isCurrentYear();

            $daysLeft = (int) $today->diffInDays($targetDate, false);

            $itemData = [
                'id' => $item->id,
                'name' => $item->name,
                'amount' => (float) $item->amount,
                'formatted_amount' => 'Rp '.number_format($item->amount, 0, ',', '.'),
                'frequency' => $item->frequency,
                'day_of_month' => $item->day_of_month,
                'due_date' => $targetDate->translatedFormat('d F Y'),
                'days_left' => $daysLeft,
                'expense_account_name' => $item->expenseAccount->name ?? 'Beban Operasional',
                'asset_account_name' => $item->assetAccount->name ?? 'Kas Operasional',
                'notes' => $item->notes,
                'last_posted_at' => $item->last_posted_at ? $item->last_posted_at->translatedFormat('d M Y') : null,
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

        // Hitung status Payroll Karyawan bulan ini
        $activeEmployees = Employee::with('assetAccount')
            ->active()
            ->orderBy('pay_day')
            ->get();

        $payrollDueToday = [];
        $payrollOverdue = [];
        $payrollUpcoming = [];
        $payrollAlreadyPaid = [];

        foreach ($activeEmployees as $emp) {
            $targetDay = min((int) $emp->pay_day, $daysInMonth);
            $targetDate = Carbon::create($today->year, $today->month, $targetDay)->startOfDay();

            $alreadyPaidThisMonth = $emp->last_paid_at
                && $emp->last_paid_at->isCurrentMonth()
                && $emp->last_paid_at->isCurrentYear();

            $daysLeft = (int) $today->diffInDays($targetDate, false);

            $empData = [
                'id' => $emp->id,
                'name' => $emp->name,
                'position' => $emp->position,
                'base_salary' => (float) $emp->base_salary,
                'current_points' => (int) $emp->current_points,
                'rate_per_point' => (float) $emp->rate_per_point,
                'bonus_salary' => (float) $emp->bonus_salary,
                'total_salary' => (float) $emp->total_salary,
                'amount' => (float) $emp->total_salary,
                'formatted_amount' => $emp->formatted_total_salary,
                'pay_day' => $emp->pay_day,
                'due_date' => $targetDate->translatedFormat('d F Y'),
                'days_left' => $daysLeft,
                'asset_account_name' => $emp->assetAccount->name ?? 'Kas Operasional',
                'last_paid_at' => $emp->last_paid_at ? $emp->last_paid_at->translatedFormat('d M Y') : null,
            ];

            if ($alreadyPaidThisMonth) {
                $payrollAlreadyPaid[] = $empData;
            } else {
                if ($today->isSameDay($targetDate)) {
                    $payrollDueToday[] = $empData;
                } elseif ($targetDate->isPast()) {
                    $payrollOverdue[] = $empData;
                } else {
                    $payrollUpcoming[] = $empData;
                }
            }
        }

        // Actionable items yang membutuhkan tombol konfirmasi bayar
        $actionable = array_merge($dueToday, $overdue);
        $payrollActionable = array_merge($payrollDueToday, $payrollOverdue);

        if ($request->boolean('mark_notified', false)) {
            $actionableIds = array_column($actionable, 'id');
            RecurringTransaction::whereIn('id', $actionableIds)->update(['last_notified_at' => now()]);
        }

        // Susun teks respon Telegram yang informatif
        $messageLines = ["📅 *Jadwal Pengeluaran & Gaji ({$monthName})*\n"];

        if (! empty($dueToday) || ! empty($payrollDueToday)) {
            $messageLines[] = '🔴 *Jatuh Tempo Hari Ini (Perlu Dibayar):*';
            foreach ($dueToday as $d) {
                $messageLines[] = "• #{$d['id']} *{$d['name']}*: {$d['formatted_amount']}";
            }
            foreach ($payrollDueToday as $pd) {
                $messageLines[] = "• 👤 *Gaji {$pd['name']}* (#{$pd['id']}): {$pd['formatted_amount']}";
            }
            $messageLines[] = '';
        }

        if (! empty($overdue) || ! empty($payrollOverdue)) {
            $messageLines[] = '⚠️ *Terlewat (Belum Dibayar):*';
            foreach ($overdue as $o) {
                $messageLines[] = "• #{$o['id']} *{$o['name']}*: {$o['formatted_amount']} (Tgl {$o['day_of_month']} {$now->translatedFormat('M')})";
            }
            foreach ($payrollOverdue as $po) {
                $messageLines[] = "• 👤 *Gaji {$po['name']}* (#{$po['id']}): {$po['formatted_amount']} (Tgl {$po['pay_day']} {$now->translatedFormat('M')})";
            }
            $messageLines[] = '';
        }

        if (! empty($actionable) || ! empty($payrollActionable)) {
            $messageLines[] = '💡 *Cara Bayar Cepat:*';
            if (! empty($actionable)) {
                $firstId = $actionable[0]['id'];
                $firstName = strtolower(explode(' ', $actionable[0]['name'])[0]);
                $messageLines[] = "• Tagihan Rutin: `/bayar {$firstId}` atau `/bayar {$firstName}`";
            }
            if (! empty($payrollActionable)) {
                $firstEmpName = strtolower(explode(' ', $payrollActionable[0]['name'])[0]);
                $messageLines[] = "• Gaji Karyawan: `/bayar gaji {$firstEmpName}` atau `/bayar gaji {$payrollActionable[0]['id']}`";
            }
            $messageLines[] = '• Untuk lewati: `/lewati <id>`';
            $messageLines[] = '';
        }

        if (! empty($upcoming) || ! empty($payrollUpcoming)) {
            $messageLines[] = '⏳ *Mendatang Bulan Ini:*';
            foreach ($upcoming as $u) {
                $daysLeft = (int) $u['days_left'];
                $daysText = $daysLeft === 1 ? 'Besok' : "{$daysLeft} hari lagi";
                $messageLines[] = "• #{$u['id']} *{$u['name']}*: {$u['formatted_amount']} (Tgl {$u['day_of_month']} {$now->translatedFormat('M')} • {$daysText})";
            }
            foreach ($payrollUpcoming as $pu) {
                $daysLeft = (int) $pu['days_left'];
                $daysText = $daysLeft === 1 ? 'Besok' : "{$daysLeft} hari lagi";
                $messageLines[] = "• 👤 *Gaji {$pu['name']}*: {$pu['formatted_amount']} (Tgl {$pu['pay_day']} {$now->translatedFormat('M')} • {$daysText})";
            }
            $messageLines[] = '';
        }

        if (! empty($alreadyPaid) || ! empty($payrollAlreadyPaid)) {
            $messageLines[] = '🟢 *Sudah Dibayar Bulan Ini:*';
            foreach ($alreadyPaid as $p) {
                $messageLines[] = "• #{$p['id']} *{$p['name']}*: {$p['formatted_amount']} (Dibayar {$p['last_posted_at']})";
            }
            foreach ($payrollAlreadyPaid as $pp) {
                $messageLines[] = "• 👤 *Gaji {$pp['name']}*: {$pp['formatted_amount']} (Dibayar {$pp['last_paid_at']})";
            }
            $messageLines[] = '';
        }

        if (empty($dueToday) && empty($overdue) && empty($upcoming) && empty($alreadyPaid)
            && empty($payrollDueToday) && empty($payrollOverdue) && empty($payrollUpcoming) && empty($payrollAlreadyPaid)) {
            $messageLines[] = "ℹ️ Belum ada jadwal pengeluaran rutin atau gaji untuk bulan ini.\n";
        }

        $totalRecurring = array_sum(array_column($dueToday, 'amount'))
                        + array_sum(array_column($overdue, 'amount'))
                        + array_sum(array_column($upcoming, 'amount'))
                        + array_sum(array_column($alreadyPaid, 'amount'));

        $totalPayroll = array_sum(array_column($payrollDueToday, 'amount'))
                      + array_sum(array_column($payrollOverdue, 'amount'))
                      + array_sum(array_column($payrollUpcoming, 'amount'))
                      + array_sum(array_column($payrollAlreadyPaid, 'amount'));

        $totalAll = $totalRecurring + $totalPayroll;

        $messageLines[] = '───────────────────';
        $messageLines[] = '💰 *Total Estimasi Bulan Ini*: Rp '.number_format($totalAll, 0, ',', '.');
        if ($totalPayroll > 0) {
            $messageLines[] = '  • Beban Rutin: Rp '.number_format($totalRecurring, 0, ',', '.');
            $messageLines[] = '  • Beban Gaji (5002): Rp '.number_format($totalPayroll, 0, ',', '.');
        }

        return response()->json([
            'status' => true,
            'period' => $monthName,
            'count' => count($actionable) + count($payrollActionable),
            'data' => $actionable,
            'due_today' => $dueToday,
            'overdue' => $overdue,
            'upcoming' => $upcoming,
            'already_paid' => $alreadyPaid,
            'payroll' => [
                'count' => count($payrollActionable),
                'due_today' => $payrollDueToday,
                'overdue' => $payrollOverdue,
                'upcoming' => $payrollUpcoming,
                'already_paid' => $payrollAlreadyPaid,
                'total_amount' => $totalPayroll,
                'formatted_total_amount' => 'Rp '.number_format($totalPayroll, 0, ',', '.'),
            ],
            'total_recurring' => $totalRecurring,
            'total_payroll' => $totalPayroll,
            'total_amount' => $totalAll,
            'formatted_total_amount' => 'Rp '.number_format($totalAll, 0, ',', '.'),
            'message' => implode("\n", $messageLines),
        ]);
    }

    /**
     * Tampilkan daftar gaji karyawan dan status pembukuannya untuk bulan ini via webhook / Telegram.
     */
    public function duePayroll(Request $request)
    {
        $today = now();
        $daysInMonth = $today->daysInMonth;
        $monthName = $today->translatedFormat('F Y');

        $activeEmployees = Employee::with('assetAccount')
            ->active()
            ->orderBy('pay_day')
            ->get();

        if ($activeEmployees->isEmpty()) {
            return response()->json([
                'status' => true,
                'period' => $monthName,
                'count' => 0,
                'message' => "ℹ️ Belum ada data karyawan aktif di sistem Seven Management.\n\n💡 Silakan tambahkan data staf melalui menu Karyawan & Payroll di dashboard web.",
                'data' => [],
            ]);
        }

        $dueToday = [];
        $overdue = [];
        $upcoming = [];
        $alreadyPaid = [];

        foreach ($activeEmployees as $emp) {
            $targetDay = min((int) $emp->pay_day, $daysInMonth);
            $targetDate = Carbon::create($today->year, $today->month, $targetDay)->startOfDay();

            $alreadyPaidThisMonth = $emp->last_paid_at
                && $emp->last_paid_at->isCurrentMonth()
                && $emp->last_paid_at->isCurrentYear();

            $daysLeft = (int) $today->diffInDays($targetDate, false);

            $empData = [
                'id' => $emp->id,
                'name' => $emp->name,
                'position' => $emp->position,
                'base_salary' => (float) $emp->base_salary,
                'formatted_base' => $emp->formatted_base_salary,
                'current_points' => (int) $emp->current_points,
                'rate_per_point' => (float) $emp->rate_per_point,
                'bonus_salary' => (float) $emp->bonus_salary,
                'formatted_bonus' => $emp->formatted_bonus_salary,
                'total_salary' => (float) $emp->total_salary,
                'amount' => (float) $emp->total_salary,
                'formatted_amount' => $emp->formatted_total_salary,
                'pay_day' => $emp->pay_day,
                'due_date' => $targetDate->translatedFormat('d F Y'),
                'days_left' => $daysLeft,
                'asset_account_name' => $emp->assetAccount->name ?? 'Kas Operasional',
                'last_paid_at' => $emp->last_paid_at ? $emp->last_paid_at->translatedFormat('d M Y') : null,
            ];

            if ($alreadyPaidThisMonth) {
                $alreadyPaid[] = $empData;
            } else {
                if ($today->isSameDay($targetDate)) {
                    $dueToday[] = $empData;
                } elseif ($targetDate->isPast()) {
                    $overdue[] = $empData;
                } else {
                    $upcoming[] = $empData;
                }
            }
        }

        $messageLines = ["👥 *Daftar Gaji Karyawan & Status ({$monthName})*\n"];

        if (! empty($dueToday)) {
            $messageLines[] = '🔴 *Jatuh Tempo Hari Ini (Perlu Dibayar):*';
            foreach ($dueToday as $d) {
                $bonusStr = $d['current_points'] > 0 ? " • Bonus: {$d['formatted_bonus']} ({$d['current_points']} pt)" : '';
                $messageLines[] = "• 👤 *#{$d['id']} {$d['name']}* ({$d['position']})";
                $messageLines[] = "  💵 Gaji: {$d['formatted_base']}{$bonusStr} ➔ *{$d['formatted_amount']}*";
                $messageLines[] = "  💳 Bayar via: {$d['asset_account_name']}";
                $messageLines[] = "  👉 Bayar cepat: `/bayar gaji {$d['id']}`";
            }
            $messageLines[] = '';
        }

        if (! empty($overdue)) {
            $messageLines[] = '⚠️ *Terlewat (Belum Dibayar):*';
            foreach ($overdue as $o) {
                $bonusStr = $o['current_points'] > 0 ? " • Bonus: {$o['formatted_bonus']} ({$o['current_points']} pt)" : '';
                $messageLines[] = "• 👤 *#{$o['id']} {$o['name']}* ({$o['position']})";
                $messageLines[] = "  💵 Gaji: {$o['formatted_base']}{$bonusStr} ➔ *{$o['formatted_amount']}*";
                $messageLines[] = "  📅 Jatuh Tempo: Tgl {$o['pay_day']} {$today->translatedFormat('M')}";
                $messageLines[] = "  💳 Bayar via: {$o['asset_account_name']}";
                $messageLines[] = "  👉 Bayar cepat: `/bayar gaji {$o['id']}`";
            }
            $messageLines[] = '';
        }

        if (! empty($upcoming)) {
            $messageLines[] = '⏳ *Mendatang Bulan Ini:*';
            foreach ($upcoming as $u) {
                $daysLeft = (int) $u['days_left'];
                $daysText = $daysLeft === 1 ? 'Besok' : "{$daysLeft} hari lagi";
                $bonusStr = $u['current_points'] > 0 ? " • Bonus: {$u['formatted_bonus']}" : '';
                $messageLines[] = "• 👤 *#{$u['id']} {$u['name']}* ({$u['position']}): *{$u['formatted_amount']}*";
                $messageLines[] = "  📅 Tgl {$u['pay_day']} {$today->translatedFormat('M')} ({$daysText}) • via {$u['asset_account_name']}";
            }
            $messageLines[] = '';
        }

        if (! empty($alreadyPaid)) {
            $messageLines[] = '🟢 *Sudah Dibayar Bulan Ini (Lunas):*';
            foreach ($alreadyPaid as $p) {
                $messageLines[] = "• 👤 *#{$p['id']} {$p['name']}* ({$p['position']}): *{$p['formatted_amount']}* (Lunas tgl {$p['last_paid_at']})";
            }
            $messageLines[] = '';
        }

        $totalPaid = array_sum(array_column($alreadyPaid, 'amount'));
        $totalUnpaid = array_sum(array_column($dueToday, 'amount'))
                     + array_sum(array_column($overdue, 'amount'))
                     + array_sum(array_column($upcoming, 'amount'));
        $totalAll = $totalPaid + $totalUnpaid;

        $messageLines[] = '───────────────────';
        $messageLines[] = '💰 *Total Beban Gaji (5002)*: Rp '.number_format($totalAll, 0, ',', '.');
        $messageLines[] = '  • 🟢 Lunas Dibayar: Rp '.number_format($totalPaid, 0, ',', '.').' ('.count($alreadyPaid).' staf)';
        $messageLines[] = '  • ⏳ Belum Dibayar: Rp '.number_format($totalUnpaid, 0, ',', '.').' ('.(count($dueToday) + count($overdue) + count($upcoming)).' staf)';

        if ($totalUnpaid > 0) {
            $messageLines[] = '';
            $messageLines[] = '💡 *Ketik `/bayar gaji <nama/id>` untuk eksekusi pembayaran gaji.*';
        }

        return response()->json([
            'status' => true,
            'period' => $monthName,
            'count' => $activeEmployees->count(),
            'due_today' => $dueToday,
            'overdue' => $overdue,
            'upcoming' => $upcoming,
            'already_paid' => $alreadyPaid,
            'actionable' => array_merge($dueToday, $overdue),
            'total_amount' => $totalAll,
            'total_paid' => $totalPaid,
            'total_unpaid' => $totalUnpaid,
            'formatted_total_amount' => 'Rp '.number_format($totalAll, 0, ',', '.'),
            'message' => implode("\n", $messageLines),
        ]);
    }

    /**
     * Approve and execute posting for a recurring expense via webhook (e.g. Telegram button click).
     */
    public function approveRecurring(Request $request, RecurringTransaction $recurringTransaction)
    {
        $customAmount = $request->filled('amount') ? (float) $request->input('amount') : null;
        $rawSource = $request->input('source', 'telegram');
        $source = str_starts_with(strtolower($rawSource), 'telegram') ? 'telegram' : $rawSource;

        $journalEntry = $recurringTransaction->executePosting($customAmount, $source);

        return response()->json([
            'status' => true,
            'message' => "Pengeluaran rutin '{$recurringTransaction->name}' berhasil dibukukan.",
            'data' => [
                'reference' => $journalEntry->reference,
                'name' => $recurringTransaction->name,
                'amount' => $customAmount ?: (float) $recurringTransaction->amount,
                'expense_account' => $recurringTransaction->expenseAccount->name ?? '',
                'asset_account' => $recurringTransaction->assetAccount->name ?? '',
                'journal_entry' => $journalEntry,
            ],
        ], 201);
    }

    /**
     * Approve and execute posting for employee payroll via webhook.
     */
    public function approvePayroll(Request $request, Employee $employee)
    {
        $customAmount = $request->filled('amount') ? (float) $request->input('amount') : null;
        $rawSource = $request->input('source', 'telegram');
        $source = str_starts_with(strtolower($rawSource), 'telegram') ? 'telegram' : $rawSource;

        $journalEntry = $employee->executePayrollPosting($customAmount, $source);

        return response()->json([
            'status' => true,
            'message' => "Penggajian karyawan '{$employee->name}' berhasil dibukukan.",
            'data' => [
                'reference' => $journalEntry->reference,
                'name' => $employee->name,
                'amount' => $customAmount ?: (float) $employee->total_salary,
                'expense_code' => '5002',
                'expense_name' => 'Beban Gaji',
                'asset_account' => $employee->assetAccount->name ?? 'Kas Operasional',
                'journal_entry' => $journalEntry,
            ],
        ], 201);
    }

    /**
     * Skip this period for employee payroll via webhook.
     */
    public function skipPayroll(Request $request, Employee $employee)
    {
        $employee->update(['last_paid_at' => now()]);

        return response()->json([
            'status' => true,
            'message' => "Penggajian karyawan '{$employee->name}' telah dilewati untuk periode ini.",
        ]);
    }

    /**
     * Skip this period for a recurring expense via webhook.
     */
    public function skipRecurring(Request $request, RecurringTransaction $recurringTransaction)
    {
        $recurringTransaction->update(['last_posted_at' => now()]);

        return response()->json([
            'status' => true,
            'message' => "Pengeluaran rutin '{$recurringTransaction->name}' telah dilewati untuk periode ini.",
        ]);
    }

    /**
     * Handle manual text command to approve or skip a recurring expense or employee payroll.
     * Examples: /bayar 1, /bayar wifi, /bayar gaji budi, /bayar gaji 1, /lewati gaji budi
     */
    public function manualRecurringAction(Request $request)
    {
        $validated = $request->validate([
            'query' => 'required|string',
            'action' => 'nullable|string|in:approve,skip,list',
            'amount' => 'nullable|numeric|min:0',
        ]);

        $rawQuery = trim($validated['query']);
        $action = strtolower($validated['action'] ?? 'approve');
        $customAmount = ! empty($validated['amount']) ? (float) $validated['amount'] : null;

        // Cek jika perintah secara spesifik menargetkan gaji karyawan (misal: "gaji budi", "/gaji", "salary 1", "karyawan asep", atau "gaji")
        $isExplicitPayroll = false;
        $cleanQuery = $rawQuery;
        if (preg_match('/^\/?(?:gaji|salary|karyawan|daftar\s*gaji)\s*(.*)$/i', $rawQuery, $matches)) {
            $isExplicitPayroll = true;
            $cleanQuery = trim($matches[1]);
        }

        // Jika action adalah 'list' atau query murni "gaji" / "daftar gaji" tanpa nama: tampilkan daftar gaji & statusnya!
        if ($action === 'list' || ($isExplicitPayroll && empty($cleanQuery))) {
            return $this->duePayroll($request);
        }

        // 1. Jika query eksplisit gaji, cari langsung di model Employee
        if ($isExplicitPayroll) {
            $employee = null;
            if (is_numeric($cleanQuery)) {
                $employee = Employee::with('assetAccount')->find((int) $cleanQuery);
            }
            if (! $employee && ! empty($cleanQuery)) {
                $employee = Employee::with('assetAccount')
                    ->where('name', 'LIKE', "%{$cleanQuery}%")
                    ->first();
            }

            if (! $employee) {
                return response()->json([
                    'status' => false,
                    'message' => "❌ Karyawan dengan kata kunci '{$cleanQuery}' tidak ditemukan.\n\n💡 Ketik /rutin untuk melihat daftar karyawan & jadwal gaji.",
                ], 404);
            }

            return $this->handleEmployeePayment($employee, $action, $customAmount);
        }

        // 2. Jika bukan eksplisit gaji, cari dulu di RecurringTransaction
        $recurring = null;
        if (is_numeric($rawQuery)) {
            $recurring = RecurringTransaction::with(['expenseAccount', 'assetAccount'])->find((int) $rawQuery);
        }

        if (! $recurring && ! empty($rawQuery)) {
            $recurring = RecurringTransaction::with(['expenseAccount', 'assetAccount'])
                ->active()
                ->where('name', 'LIKE', "%{$rawQuery}%")
                ->first();
        }

        // 3. Jika di RecurringTransaction tidak ada, periksa apakah cocok dengan nama Employee
        if (! $recurring && ! empty($rawQuery)) {
            $employee = Employee::with('assetAccount')
                ->where('name', 'LIKE', "%{$rawQuery}%")
                ->first();

            if ($employee) {
                return $this->handleEmployeePayment($employee, $action, $customAmount);
            }
        }

        if (! $recurring) {
            return response()->json([
                'status' => false,
                'message' => "❌ Tagihan atau gaji dengan kata kunci '{$rawQuery}' tidak ditemukan.\n\n💡 Ketik /rutin untuk melihat daftar tagihan & jadwal gaji aktif.",
            ], 404);
        }

        if ($action === 'skip') {
            $recurring->update(['last_posted_at' => now()]);

            return response()->json([
                'status' => true,
                'message' => "⏭️ Pengeluaran rutin '#{$recurring->id} {$recurring->name}' telah dilewati untuk periode ini.",
            ]);
        }

        // Eksekusi posting akuntansi (double-entry)
        $journalEntry = $recurring->executePosting($customAmount, 'telegram');

        $finalAmount = $customAmount ?: (float) $recurring->amount;
        $formattedAmount = 'Rp '.number_format($finalAmount, 0, ',', '.');
        $assetName = $recurring->assetAccount->name ?? 'Kas Operasional';
        $expenseName = $recurring->expenseAccount->name ?? 'Beban Operasional';

        return response()->json([
            'status' => true,
            'message' => "✅ *Pengeluaran Rutin Berhasil Dibukukan!*\n\n".
                         "🏢 *#{$recurring->id} {$recurring->name}*\n".
                         "💰 *{$formattedAmount}*\n".
                         "📂 Beban: {$expenseName}\n".
                         "💳 Bayar dari: {$assetName}\n".
                         "🔖 Ref: `{$journalEntry->reference}`",
            'data' => [
                'reference' => $journalEntry->reference,
                'name' => $recurring->name,
                'amount' => $finalAmount,
            ],
        ], 201);
    }

    /**
     * Eksekusi atau lewati pembayaran gaji karyawan dari webhook / Telegram command.
     */
    private function handleEmployeePayment(Employee $employee, string $action, ?float $customAmount)
    {
        if ($action === 'skip') {
            $employee->update(['last_paid_at' => now()]);

            return response()->json([
                'status' => true,
                'message' => "⏭️ Penggajian karyawan '#{$employee->id} {$employee->name}' telah dilewati untuk periode ini.",
            ]);
        }

        $journalEntry = $employee->executePayrollPosting($customAmount, 'telegram');

        $finalAmount = $customAmount !== null && $customAmount > 0
            ? $customAmount
            : (float) $employee->total_salary;
        $formattedAmount = 'Rp '.number_format($finalAmount, 0, ',', '.');
        $assetName = $employee->assetAccount->name ?? 'Kas Operasional';

        $bonusInfo = '';
        if ($employee->current_points > 0) {
            $bonusInfo = "⭐ Bonus Poin: {$employee->formatted_bonus_salary} ({$employee->current_points} poin @ Rp ".number_format($employee->rate_per_point, 0, ',', '.').")\n";
        }

        return response()->json([
            'status' => true,
            'message' => "✅ *Gaji Karyawan Berhasil Dibukukan!*\n\n".
                         "👤 *#{$employee->id} {$employee->name}* ({$employee->position})\n".
                         "💵 Gaji Pokok: {$employee->formatted_base_salary}\n".
                         $bonusInfo.
                         "💰 *Total Dibayar: {$formattedAmount}*\n".
                         "📂 Beban: Beban Gaji (5002)\n".
                         "💳 Bayar dari: {$assetName}\n".
                         "🔖 Ref: `{$journalEntry->reference}`",
            'data' => [
                'reference' => $journalEntry->reference,
                'name' => $employee->name,
                'amount' => $finalAmount,
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
                    'id' => $acc->id,
                    'code' => $acc->code,
                    'name' => $acc->name,
                    'balance' => $balance,
                    'formatted_balance' => 'Rp '.number_format($balance, 0, ',', '.'),
                ];
            });

        $totalDebit = (float) JournalEntryLine::whereHas('account', fn ($q) => $q->where('type', 'asset'))->sum('debit');
        $totalCredit = (float) JournalEntryLine::whereHas('account', fn ($q) => $q->where('type', 'asset'))->sum('credit');
        $totalLiquidity = $totalDebit - $totalCredit;

        $messageLines = ["💳 *Saldo Kas & Bank Terkini:*\n"];
        foreach ($accounts as $acc) {
            $messageLines[] = "• *{$acc['name']}*: {$acc['formatted_balance']}";
        }
        $messageLines[] = "\n───────────────────";
        $messageLines[] = '💰 *Total Aset Likuid*: Rp '.number_format($totalLiquidity, 0, ',', '.');

        return response()->json([
            'status' => true,
            'total' => $totalLiquidity,
            'formatted_total' => 'Rp '.number_format($totalLiquidity, 0, ',', '.'),
            'accounts' => $accounts,
            'message' => implode("\n", $messageLines),
        ]);
    }

    /**
     * Get month-to-date income, expenses, and net profit summary.
     */
    public function summary(Request $request)
    {
        $now = Carbon::now();
        $monthName = $now->translatedFormat('F Y');

        $revenue = (float) JournalEntryLine::whereHas('account', fn ($q) => $q->where('type', 'revenue'))
            ->whereMonth('created_at', $now->month)
            ->whereYear('created_at', $now->year)
            ->sum('credit');

        $expense = (float) JournalEntryLine::whereHas('account', fn ($q) => $q->where('type', 'expense'))
            ->whereMonth('created_at', $now->month)
            ->whereYear('created_at', $now->year)
            ->sum('debit');

        $netProfit = $revenue - $expense;
        $pendingCount = JournalEntry::where('status', 'pending')->count();
        $verifiedCount = JournalEntry::where('status', 'verified')->count();

        $messageLines = [
            "📊 *Ringkasan Keuangan ({$monthName})*\n",
            '🟢 *Pemasukan*: Rp '.number_format($revenue, 0, ',', '.'),
            '🔴 *Pengeluaran*: Rp '.number_format($expense, 0, ',', '.'),
            '───────────────────',
            ($netProfit >= 0 ? '📈' : '📉').' *Laba Bersih*: Rp '.number_format($netProfit, 0, ',', '.'),
            "\n⏳ *Status Transaksi Bulan Ini*:",
            "• Menunggu Verifikasi: {$pendingCount}",
            "• Terverifikasi: {$verifiedCount}",
        ];

        return response()->json([
            'status' => true,
            'period' => $monthName,
            'revenue' => $revenue,
            'expense' => $expense,
            'net_profit' => $netProfit,
            'pending_count' => $pendingCount,
            'verified_count' => $verifiedCount,
            'message' => implode("\n", $messageLines),
        ]);
    }

    /**
     * Webhook n8n: Estimasi kebutuhan bahan, HPP, dan rekomendasi harga jas via AI Agent / Telegram.
     */
    public function estimateSuit(Request $request, SuitMaterialEstimatorService $estimator)
    {
        $suitType = $request->input('suit_type', 'jas_blazer_pria');
        $aiVision = $request->input('ai_vision') ?? $request->input('gemini_analysis') ?? [];
        $parsedMeasurements = $aiVision['parsed_measurements'] ?? [];

        $measurements = [
            'height' => (float) ($request->input('height') ?? $parsedMeasurements['height'] ?? $request->input('tinggi_badan') ?? 170),
            'chest' => (float) ($request->input('chest') ?? $parsedMeasurements['chest'] ?? $request->input('lingkar_dada') ?? 96),
            'waist' => (float) ($request->input('waist') ?? $parsedMeasurements['waist'] ?? $request->input('lingkar_pinggang') ?? 82),
            'jacket_length' => (float) ($request->input('jacket_length') ?? $parsedMeasurements['jacket_length'] ?? $request->input('panjang_jas') ?? 74),
            'trouser_length' => (float) ($request->input('trouser_length') ?? $parsedMeasurements['trouser_length'] ?? $request->input('panjang_celana') ?? 98),
        ];

        $customOptions = [
            'fabric_price_per_meter' => $request->input('fabric_price_per_meter'),
            'labor_cost' => $request->input('labor_cost'),
            'target_margin_percent' => $request->input('target_margin_percent', 45),
        ];

        $estimate = $estimator->estimate($suitType, $measurements, $customOptions, $aiVision);

        $typeName = Product::CATEGORIES[$suitType] ?? ucfirst(str_replace('_', ' ', $suitType));
        $m = $estimate['materials'];
        $fin = $estimate['financial'];

        $telegramMsg = [
            '🧵 *Hasil Analisis Gambar & Estimasi Bahan Jas (AI Master Tailor)*',
            "👔 *Kategori Model*: {$typeName}",
        ];

        if (! empty($aiVision['model_name'])) {
            $telegramMsg[] = "🧥 *Desain Jas*: {$aiVision['model_name']}";
        }
        if (! empty($aiVision['lapel_style'])) {
            $telegramMsg[] = "👔 *Gaya Kerah*: {$aiVision['lapel_style']}";
        }
        if (! empty($aiVision['button_layout'])) {
            $telegramMsg[] = "🔘 *Kancing*: {$aiVision['button_layout']}";
        }
        if (! empty($aiVision['pocket_type'])) {
            $telegramMsg[] = "👝 *Tipe Saku*: {$aiVision['pocket_type']}";
        }
        if (! empty($aiVision['color'])) {
            $telegramMsg[] = "🎨 *Warna*: {$aiVision['color']}";
        }
        if (! empty($aiVision['recommended_cut'])) {
            $telegramMsg[] = "✂️ *Potongan Rekomendasi*: {$aiVision['recommended_cut']}";
        }

        $telegramMsg[] = '───────────────────';
        $telegramMsg[] = '📐 *Data Ukuran Kustom (Caption):*';
        $telegramMsg[] = "• TB: {$measurements['height']} cm | LD: {$measurements['chest']} cm | LP: {$measurements['waist']} cm";
        if (! empty($parsedMeasurements['shoulder']) || ! empty($parsedMeasurements['sleeve_length'])) {
            $shoulder = $parsedMeasurements['shoulder'] ?? '-';
            $sleeve = $parsedMeasurements['sleeve_length'] ?? '-';
            $telegramMsg[] = "• Bahu: {$shoulder} cm | Panjang Lengan: {$sleeve} cm";
        }

        if (! empty($aiVision['ai_fit_advisory'])) {
            $telegramMsg[] = '───────────────────';
            $telegramMsg[] = '💡 *Analisis & Rekomendasi Fit AI:*';
            $telegramMsg[] = "{$aiVision['ai_fit_advisory']}";
        }

        $telegramMsg[] = '───────────────────';
        $telegramMsg[] = '🧵 *Kalkulasi Bahan Baku:*';
        $telegramMsg[] = "📏 *Kain Utama*: {$m['main_fabric_meters']} meter ({$m['main_fabric_description']})";

        if (($m['lining_meters'] ?? 0) > 0) {
            $telegramMsg[] = "🧶 *Kain Furing / Lining*: {$m['lining_meters']} meter";
        }
        if (($m['interlining_kufner_meters'] ?? 0) > 0) {
            $telegramMsg[] = "✂️ *Interlining / Kufner*: {$m['interlining_kufner_meters']} meter";
        }

        $telegramMsg[] = '───────────────────';
        $telegramMsg[] = '💵 *Estimasi HPP (Modal)*: Rp '.number_format($fin['total_cost'], 0, ',', '.');
        $telegramMsg[] = '   • Bahan Baku: Rp '.number_format($m['total_material_cost'], 0, ',', '.');
        $telegramMsg[] = '   • Ongkos Jahit: Rp '.number_format($estimate['labor']['labor_cost'], 0, ',', '.');
        $telegramMsg[] = '🏷️ *Rekomendasi Harga Jual*: Rp '.number_format($fin['suggested_price'], 0, ',', '.');
        $telegramMsg[] = '📈 *Proyeksi Keuntungan*: Rp '.number_format($fin['projected_profit'], 0, ',', '.')." ({$fin['profit_margin_percent']}%)";

        return response()->json([
            'status' => true,
            'data' => $estimate,
            'message' => implode("\n", $telegramMsg),
        ]);
    }

    /**
     * Webhook n8n: Membuat pesanan jas custom langsung dari bot Telegram.
     */
    public function orderCustomSuit(Request $request, SuitMaterialEstimatorService $estimator)
    {
        $validated = $request->validate([
            'customer_name' => 'required|string|max:255',
            'customer_phone' => 'nullable|string|max:50',
            'suit_type' => 'nullable|string',
            'fabric_type' => 'nullable|string|max:150',
            'color' => 'nullable|string|max:100',
            'image_url' => 'nullable|string',
            'total_price' => 'nullable|numeric|min:0',
            'down_payment' => 'nullable|numeric|min:0',
            'account_code' => 'nullable|string|max:20',
            'notes' => 'nullable|string|max:1000',
        ]);

        $suitType = $validated['suit_type'] ?? 'jas_blazer_pria';
        if (! array_key_exists($suitType, Product::CATEGORIES)) {
            $suitType = 'jas_blazer_pria';
        }

        $measurements = [
            'height' => (float) ($request->input('height') ?? $request->input('tinggi_badan') ?? 170),
            'chest' => (float) ($request->input('chest') ?? $request->input('lingkar_dada') ?? 96),
            'waist' => (float) ($request->input('waist') ?? $request->input('lingkar_pinggang') ?? 82),
            'jacket_length' => (float) ($request->input('jacket_length') ?? $request->input('panjang_jas') ?? 74),
            'trouser_length' => (float) ($request->input('trouser_length') ?? $request->input('panjang_celana') ?? 98),
        ];

        $estimate = $estimator->estimate($suitType, $measurements, [], $request->input('gemini_analysis'));

        $totalPrice = (float) ($validated['total_price'] ?? $estimate['financial']['suggested_price']);
        $totalCost = (float) $estimate['financial']['total_cost'];
        $materialCost = (float) $estimate['materials']['total_material_cost'];
        $laborCost = (float) $estimate['labor']['labor_cost'];
        $downPayment = (float) ($validated['down_payment'] ?? 0);

        $orderNumber = 'CST-'.date('Ymd').'-'.strtoupper(Str::random(4));

        $paymentStatus = 'unpaid';
        if ($downPayment >= $totalPrice && $totalPrice > 0) {
            $paymentStatus = 'paid';
        } elseif ($downPayment > 0) {
            $paymentStatus = 'partial_dp';
        }

        $order = DB::transaction(function () use (
            $validated,
            $orderNumber,
            $suitType,
            $measurements,
            $estimate,
            $materialCost,
            $laborCost,
            $totalCost,
            $totalPrice,
            $downPayment,
            $paymentStatus

        ) {
            $accountCode = $validated['account_code'] ?? '1001';
            $account = Account::where('code', $accountCode)->first()
                ?? Account::where('type', 'asset')->first();

            $customOrder = CustomSuitOrder::create([
                'order_number' => $orderNumber,
                'customer_name' => $validated['customer_name'],
                'customer_phone' => $validated['customer_phone'] ?? null,
                'order_date' => now()->toDateString(),
                'due_date' => now()->addDays(14)->toDateString(),
                'suit_type' => $suitType,
                'fabric_type' => $validated['fabric_type'] ?? 'Wool Blend Standar',
                'color' => $validated['color'] ?? 'Custom Color',
                'body_measurements' => $measurements,
                'reference_image' => $validated['image_url'] ?? null,
                'ai_estimation' => $estimate,
                'material_cost' => $materialCost,
                'labor_cost' => $laborCost,
                'total_cost' => $totalCost,
                'total_price' => $totalPrice,
                'down_payment' => $downPayment,
                'payment_status' => $paymentStatus,
                'production_status' => 'consultation',
                'account_id' => $account?->id,
                'source' => 'telegram',
                'notes' => $validated['notes'] ?? 'Dibuat otomatis via Telegram n8n AI Agent',
            ]);

            // Jika ada DP, bukukan langsung ke kasir akuntansi
            if ($downPayment > 0 && $account) {
                $customRevenueAccount = Account::firstOrCreate(
                    ['code' => '4003'],
                    ['name' => 'Pendapatan Jasa Pembuatan Jas Custom', 'type' => 'revenue']
                );

                $journal = JournalEntry::create([
                    'reference' => $orderNumber,
                    'description' => "DP Pesanan Jas Telegram {$customOrder->customer_name} ({$orderNumber})",
                    'date' => now(),
                    'source' => 'custom_suit',
                    'status' => 'verified',
                ]);

                JournalEntryLine::create([
                    'journal_entry_id' => $journal->id,
                    'account_id' => $account->id,
                    'description' => "Penerimaan DP pesanan jas {$orderNumber}",
                    'debit' => $downPayment,
                    'credit' => 0,
                ]);

                JournalEntryLine::create([
                    'journal_entry_id' => $journal->id,
                    'account_id' => $customRevenueAccount->id,
                    'description' => "Pendapatan jasa tailor {$orderNumber}",
                    'debit' => 0,
                    'credit' => $downPayment,
                ]);

                $customOrder->update(['journal_entry_id' => $journal->id]);
            }

            return $customOrder;
        });

        $msg = [
            '✅ *Pesanan Pembuatan Jas Berhasil Dibuat!*',
            "📋 *No. Pesanan*: `{$order->order_number}`",
            "👤 *Pelanggan*: {$order->customer_name}",
            "👔 *Jenis*: {$order->suit_type_label}",
            '💰 *Total Biaya*: Rp '.number_format($order->total_price, 0, ',', '.'),
            '💵 *Uang Muka (DP)*: Rp '.number_format($order->down_payment, 0, ',', '.'),
            '💳 *Sisa Tagihan*: Rp '.number_format($order->remaining_payment, 0, ',', '.'),
            '📍 *Status*: Konsultasi / Ukur',
            '📅 *Target Jadi*: '.($order->due_date ? $order->due_date->format('d M Y') : '14 hari'),
        ];

        return response()->json([
            'status' => true,
            'order' => $order,
            'message' => implode("\n", $msg),
        ]);
    }

    /**
     * Webhook n8n: Cek stok pakaian retail via Telegram / AI Agent.
     */
    public function checkRetailStock(Request $request)
    {
        $query = Product::query();

        if ($request->boolean('low_stock')) {
            $query->whereColumn('stock', '<=', 'min_stock');
        } elseif ($search = $request->input('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%");
            });
        } elseif ($cat = $request->input('category')) {
            $query->where('category', $cat);
        }

        $products = $query->orderBy('stock', 'asc')->limit(15)->get();

        if ($products->isEmpty()) {
            return response()->json([
                'status' => true,
                'count' => 0,
                'products' => [],
                'message' => 'ℹ️ Tidak ditemukan produk pakaian yang sesuai kriteria pencarian.',
            ]);
        }

        $lines = ["📦 *Informasi Stok Pakaian Retail Seven Management:*\n"];
        foreach ($products as $p) {
            $statusIcon = $p->stock <= 0 ? '❌' : ($p->stock <= $p->min_stock ? '⚠️' : '✅');
            $lines[] = "{$statusIcon} *{$p->name}* ({$p->code})";
            $lines[] = "   Stok: {$p->stock} pcs | Harga: Rp ".number_format($p->selling_price, 0, ',', '.');
        }

        return response()->json([
            'status' => true,
            'count' => $products->count(),
            'products' => $products,
            'message' => implode("\n", $lines),
        ]);
    }

    /**
     * Webhook n8n: Catat transaksi penjualan retail dari Telegram.
     */
    public function recordRetailSale(Request $request)
    {
        $validated = $request->validate([
            'product_code' => 'required_without:product_id|string',
            'product_id' => 'nullable|exists:products,id',
            'quantity' => 'nullable|integer|min:1',
            'customer_name' => 'nullable|string|max:255',
            'account_code' => 'nullable|string',
        ]);

        $qty = (int) ($validated['quantity'] ?? 1);
        $product = ! empty($validated['product_id'])
            ? Product::find($validated['product_id'])
            : Product::where('code', $validated['product_code'])->first();

        if (! $product) {
            return response()->json([
                'status' => false,
                'message' => "❌ Produk dengan kode '{$validated['product_code']}' tidak ditemukan.",
            ], 404);
        }

        if ($product->stock < $qty) {
            return response()->json([
                'status' => false,
                'message' => "❌ Stok produk '{$product->name}' tidak mencukupi (sisa: {$product->stock}, diminta: {$qty}).",
            ], 422);
        }

        $account = Account::where('code', $validated['account_code'] ?? '1001')->first()
            ?? Account::where('type', 'asset')->first();

        $sale = DB::transaction(function () use ($product, $qty, $validated, $account) {
            $product->decrement('stock', $qty);

            $unitCost = (float) $product->cost_price;
            $unitPrice = (float) $product->selling_price;
            $subtotal = $unitPrice * $qty;
            $totalCost = $unitCost * $qty;
            $invoiceNumber = 'RTL-'.date('Ymd').'-'.strtoupper(Str::random(4));

            $retailSale = RetailSale::create([
                'invoice_number' => $invoiceNumber,
                'sale_date' => now(),
                'customer_name' => $validated['customer_name'] ?? 'Pelanggan Telegram',
                'payment_method' => 'cash',
                'account_id' => $account?->id,
                'total_amount' => $subtotal,
                'total_cost' => $totalCost,
                'notes' => 'Transaksi penjualan via Bot Telegram n8n',
            ]);

            RetailSaleItem::create([
                'retail_sale_id' => $retailSale->id,
                'product_id' => $product->id,
                'quantity' => $qty,
                'unit_cost_price' => $unitCost,
                'unit_selling_price' => $unitPrice,
                'subtotal' => $subtotal,
            ]);

            // Posting otomatis ke Akuntansi
            $pendapatanRetailAccount = Account::firstOrCreate(
                ['code' => '4002'],
                ['name' => 'Pendapatan Penjualan Retail', 'type' => 'revenue']
            );
            $persediaanAccount = Account::firstOrCreate(
                ['code' => '1003'],
                ['name' => 'Persediaan Barang Dagang (Retail)', 'type' => 'asset']
            );
            $hppAccount = Account::firstOrCreate(
                ['code' => '5004'],
                ['name' => 'Beban Pokok Penjualan (HPP) Retail', 'type' => 'expense']
            );

            $journal = JournalEntry::create([
                'reference' => $invoiceNumber,
                'description' => "Penjualan Retail {$product->name} x{$qty} ({$invoiceNumber})",
                'date' => now(),
                'source' => 'retail',
                'status' => 'verified',
            ]);

            if ($account) {
                JournalEntryLine::create([
                    'journal_entry_id' => $journal->id,
                    'account_id' => $account->id,
                    'description' => "Penerimaan penjualan {$invoiceNumber}",
                    'debit' => $subtotal,
                    'credit' => 0,
                ]);
            }

            JournalEntryLine::create([
                'journal_entry_id' => $journal->id,
                'account_id' => $pendapatanRetailAccount->id,
                'description' => "Pendapatan retail {$invoiceNumber}",
                'debit' => 0,
                'credit' => $subtotal,
            ]);

            if ($totalCost > 0) {
                JournalEntryLine::create([
                    'journal_entry_id' => $journal->id,
                    'account_id' => $hppAccount->id,
                    'description' => "HPP penjualan {$invoiceNumber}",
                    'debit' => $totalCost,
                    'credit' => 0,
                ]);
                JournalEntryLine::create([
                    'journal_entry_id' => $journal->id,
                    'account_id' => $persediaanAccount->id,
                    'description' => "Pengurangan stok {$invoiceNumber}",
                    'debit' => 0,
                    'credit' => $totalCost,
                ]);
            }

            $retailSale->update(['journal_entry_id' => $journal->id]);

            return $retailSale;
        });

        $msg = [
            '🛍️ *Penjualan Retail Berhasil Dicatat!*',
            "🧾 *Invoice*: `{$sale->invoice_number}`",
            "📦 *Item*: {$product->name} (x{$qty})",
            '💰 *Total Bayar*: Rp '.number_format($sale->total_amount, 0, ',', '.'),
            '📉 *Sisa Stok*: '.($product->fresh()->stock).' pcs',
        ];

        return response()->json([
            'status' => true,
            'sale' => $sale,
            'message' => implode("\n", $msg),
        ]);
    }
}
