<?php

namespace App\Http\Controllers\Api;

use App\Enums\EmployeeRole;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\CustomSuitOrder;
use App\Models\Employee;
use App\Models\EmployeePointLog;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\Material;
use App\Models\MaterialStockMovement;
use App\Models\PointSetting;
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
     * Helper terpusat untuk mencari data karyawan yang mengirim pesan via Telegram:
     * 1. Prioritas 1: Berdasarkan Nomor HP / Mobile Number (sender_phone / phone / contact / header X-Telegram-Phone)
     * 2. Prioritas 2: Berdasarkan Telegram User ID (sender_telegram_id / header X-Telegram-User-Id)
     * 3. Prioritas 3: Berdasarkan Telegram Username (sender_username)
     * 4. Prioritas 4: Berdasarkan employee_id jika dikirim langsung
     */
    protected function resolveTelegramEmployee(Request $request): ?Employee
    {
        $senderPhone = trim((string) (
            $request->input('sender_phone')
            ?? $request->input('sender_mobile_number')
            ?? $request->input('phone_number')
            ?? $request->input('mobile_number')
            ?? $request->input('phone')
            ?? $request->header('X-Telegram-Phone')
            ?? $request->header('X-Sender-Phone')
            ?? ''
        ));

        if (! empty($senderPhone)) {
            $emp = Employee::findByPhone($senderPhone);
            if ($emp) {
                return $emp;
            }
        }

        $senderId = trim((string) (
            $request->input('sender_telegram_id')
            ?? $request->input('telegram_user_id')
            ?? $request->header('X-Telegram-User-Id')
            ?? ''
        ));

        if (! empty($senderId)) {
            $emp = Employee::where('telegram_user_id', $senderId)->first();
            if ($emp) {
                return $emp;
            }
        }

        $senderUsername = trim((string) ($request->input('sender_username') ?? ''));
        if (! empty($senderUsername)) {
            $emp = Employee::where('telegram_username', ltrim($senderUsername, '@'))->first();
            if ($emp) {
                return $emp;
            }
        }

        return null;
    }

    /**
     * Resolves the Telegram sender (via mobile phone number or Telegram ID) and enforces allowed roles.
     */
    protected function authorizeTelegramRole(Request $request, array $allowedRoles = []): array
    {
        $senderPhone = trim((string) (
            $request->input('sender_phone')
            ?? $request->input('sender_mobile_number')
            ?? $request->input('phone_number')
            ?? $request->input('mobile_number')
            ?? $request->input('phone')
            ?? $request->header('X-Telegram-Phone')
            ?? $request->header('X-Sender-Phone')
            ?? ''
        ));

        $senderId = trim((string) (
            $request->input('sender_telegram_id')
            ?? $request->input('telegram_user_id')
            ?? $request->header('X-Telegram-User-Id')
            ?? ''
        ));

        $senderUsername = trim((string) ($request->input('sender_username') ?? ''));

        $ownerPhones = config('services.telegram.owner_phones', []);
        $akuntanPhones = config('services.telegram.akuntan_phones', []);
        $ownerIds = config('services.telegram.owner_ids', []);
        $akuntanIds = config('services.telegram.akuntan_ids', []);

        $role = null;

        // 1. Cari Karyawan dari Request (HP -> ID -> Username)
        $employee = $this->resolveTelegramEmployee($request);

        // 2. Normalisasi nomor HP untuk pencocokan konfigurasi .env
        $normalizedSenderPhone = ! empty($senderPhone) ? Employee::normalizePhone($senderPhone) : '';
        $normalizedOwnerPhones = array_map([Employee::class, 'normalizePhone'], $ownerPhones);
        $normalizedAkuntanPhones = array_map([Employee::class, 'normalizePhone'], $akuntanPhones);

        // 3. Tentukan Peran (Role)
        if (! empty($normalizedSenderPhone) && in_array($normalizedSenderPhone, $normalizedOwnerPhones, true)) {
            $role = Employee::ROLE_OWNER;
        } elseif (! empty($normalizedSenderPhone) && in_array($normalizedSenderPhone, $normalizedAkuntanPhones, true)) {
            $role = Employee::ROLE_AKUNTAN;
        } elseif (! empty($senderId) && in_array($senderId, $ownerIds, true)) {
            $role = Employee::ROLE_OWNER;
        } elseif (! empty($senderId) && in_array($senderId, $akuntanIds, true)) {
            $role = Employee::ROLE_AKUNTAN;
        } elseif ($employee) {
            $role = $employee->role_value ?: Employee::ROLE_CS;
            // Jika ID telegram baru diketahui dan belum tersimpan pada profil karyawan, otomatis simpan
            if (! empty($senderId) && empty($employee->telegram_user_id)) {
                $employee->update(['telegram_user_id' => $senderId]);
            }
        }

        // 4. Validasi Peran yang Diizinkan (jika ada pembatasan peran)
        $hasIdentifier = ! empty($senderPhone) || ! empty($senderId) || ! empty($senderUsername);

        if ($hasIdentifier && ! empty($allowedRoles)) {
            $allowedRoleValues = array_map(fn ($r) => $r instanceof EmployeeRole ? $r->value : (string) $r, $allowedRoles);
            if (! $role || ! in_array($role, $allowedRoleValues, true)) {
                $roleNames = array_map(fn ($r) => Employee::ROLES[$r] ?? ucfirst($r), $allowedRoleValues);
                $allowedList = implode(' / ', $roleNames);
                $currentRoleName = $role ? (Employee::ROLES[$role] ?? ucfirst($role)) : 'Belum Terdaftar';
                $identityText = $senderPhone ?: ($senderId ? "ID: {$senderId}" : 'Tidak diketahui');

                abort(response()->json([
                    'status' => false,
                    'role' => $role,
                    'message' => "⛔ *Akses Ditolak!*\nFitur ini dibatasi khusus untuk: [{$allowedList}].\nAkun Telegram Anda ({$identityText}) teridentifikasi sebagai: *{$currentRoleName}*.\n\n💡 Pastikan nomor HP Telegram Anda sudah didaftarkan pada menu Manajemen Karyawan di sistem Seven Management.",
                ], 403));
            }
        }

        return [
            'sender_id' => $senderId,
            'sender_phone' => $senderPhone,
            'role' => $role,
            'employee' => $employee,
        ];
    }

    /**
     * Identifikasi dan hubungkan akun Telegram pengguna via nomor HP / Telegram ID.
     */
    public function identifyTelegramUser(Request $request)
    {
        $auth = $this->authorizeTelegramRole($request);
        $senderPhone = $auth['sender_phone'] ?? '';
        $senderId = $auth['sender_id'] ?? '';
        $role = $auth['role'];
        $employee = $auth['employee'];

        // Jika data karyawan ditemukan dan ada senderId, tautkan otomatis jika belum tertaut
        if ($employee && ! empty($senderId) && $employee->telegram_user_id !== $senderId) {
            $employee->update(['telegram_user_id' => $senderId]);
        }

        $senderUsername = trim((string) ($request->input('sender_username') ?? ''));
        if ($employee && ! empty($senderUsername) && empty($employee->telegram_username)) {
            $employee->update(['telegram_username' => ltrim($senderUsername, '@')]);
        }

        if (! $role && ! $employee) {
            $identity = $senderPhone ?: ($senderId ? "ID: {$senderId}" : 'Nomor HP tidak terdeteksi');

            return response()->json([
                'status' => false,
                'authenticated' => false,
                'role' => null,
                'level' => 'guest',
                'message' => "❌ *Akun Telegram Belum Terhubung*\n".
                             "Nomor HP / Akun Telegram Anda ({$identity}) belum terdaftar pada sistem Seven Management.\n\n".
                             "💡 Silakan hubungi HRD / Manajemen untuk mendaftarkan nomor HP Anda pada menu Karyawan, atau gunakan tombol '📱 Kirim Nomor HP' untuk menautkan akun.",
            ], 404);
        }

        $roleLabel = $role ? (Employee::ROLES[$role] ?? ucfirst($role)) : ($employee ? $employee->role_label : 'Pegawai');
        $isAboveStaff = in_array($role, [Employee::ROLE_OWNER, Employee::ROLE_AKUNTAN, Employee::ROLE_MANAGER, EmployeeRole::Hrd->value, EmployeeRole::Supervisor->value], true);
        $level = $isAboveStaff ? 'above_staff' : 'staff';

        $greetingName = $employee ? $employee->name : $roleLabel;
        $levelTitle = $isAboveStaff ? '👑 Manajemen (Di Atas Staff)' : '💼 Operasional (Tingkat Staff)';

        return response()->json([
            'status' => true,
            'authenticated' => true,
            'role' => $role,
            'level' => $level,
            'is_above_staff' => $isAboveStaff,
            'is_staff' => ! $isAboveStaff,
            'role_label' => $roleLabel,
            'employee' => $employee ? [
                'id' => $employee->id,
                'name' => $employee->name,
                'phone' => $employee->phone,
                'role' => $employee->role_value,
                'position' => $employee->position,
                'current_points' => $employee->current_points,
                'tier_label' => $employee->tier_label,
                'formatted_bonus' => $employee->formatted_bonus_salary,
            ] : null,
            'message' => "👋 Halo *{$greetingName}*!\n".
                         "📱 Status: *Terautentikasi*\n".
                         "💼 Jabatan: *{$roleLabel}* ({$levelTitle})\n\n".
                         ($isAboveStaff
                            ? 'Anda memiliki akses manajemen untuk memantau saldo, tagihan rutin, rekapitulasi gaji, dan evaluasi poin staf.'
                            : 'Gunakan menu di bawah untuk mencatat kasir retail (/jual), cek stok pakaian (/stok), tracking jas (/status), dan cek perolehan poin Anda (/poinsaya).'),
        ]);
    }

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
        $this->authorizeTelegramRole($request, EmployeeRole::aboveStaff());

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
        $auth = $this->authorizeTelegramRole($request, EmployeeRole::aboveStaff());
        if ($auth['role'] === Employee::ROLE_CS || $auth['role'] === Employee::ROLE_STAFF) {
            return response()->json([
                'status' => false,
                'role' => $auth['role'],
                'message' => "⛔ *Akses Ditolak!*\nInformasi rekapitulasi gaji karyawan hanya dapat diakses oleh Manajemen (Owner / Akuntan / HRD / Manager).\n\n💡 Untuk mengecek poin dan estimasi bonus kinerja Anda sendiri, gunakan perintah `/poinsaya`.",
            ], 403);
        }

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
        $this->authorizeTelegramRole($request, [Employee::ROLE_OWNER, Employee::ROLE_AKUNTAN]);

        // 1. Cek database sebelum eksekusi bayar apakah tagihan sudah dibayar pada periode berjalan
        if ($recurringTransaction->isPaidForCurrentPeriod()) {
            $existingEntry = $recurringTransaction->findExistingCurrentPeriodJournal();
            $paidAt = $recurringTransaction->last_posted_at
                ? $recurringTransaction->last_posted_at->translatedFormat('d F Y, H:i').' WIB'
                : ($existingEntry ? Carbon::parse($existingEntry->date)->translatedFormat('d F Y, H:i').' WIB' : 'Periode ini');

            $reference = $existingEntry?->reference ?? 'Sudah Dibukukan';
            $formattedAmount = 'Rp '.number_format($recurringTransaction->amount, 0, ',', '.');
            $periodName = now()->translatedFormat('F Y');

            return response()->json([
                'status' => false,
                'already_paid' => true,
                'message' => "⚠️ *Pengeluaran Bulanan Sudah Dibayar!*\n\n".
                             "🏢 *{$recurringTransaction->name}*\n".
                             "💰 *{$formattedAmount}* ({$recurringTransaction->frequency})\n".
                             "📅 Periode: {$periodName}\n".
                             "⏰ Waktu Bayar: {$paidAt}\n".
                             "🔖 Ref: `{$reference}`\n\n".
                             '💡 *Info:* Pengeluaran ini sudah tercatat sebelumnya di buku besar database. Pembayaran tidak diproses ulang untuk mencegah duplikasi transaksi.',
                'data' => [
                    'already_paid' => true,
                    'reference' => $reference,
                    'name' => $recurringTransaction->name,
                    'amount' => (float) $recurringTransaction->amount,
                    'paid_at' => $paidAt,
                    'journal_entry' => $existingEntry,
                ],
            ], 200);
        }

        $customAmount = $request->filled('amount') ? (float) $request->input('amount') : null;
        $rawSource = $request->input('source', 'telegram');
        $source = str_starts_with(strtolower($rawSource), 'telegram') ? 'telegram' : $rawSource;

        $journalEntry = $recurringTransaction->executePosting($customAmount, $source);
        $finalAmount = $customAmount ?: (float) $recurringTransaction->amount;
        $formattedAmount = 'Rp '.number_format($finalAmount, 0, ',', '.');
        $periodName = now()->translatedFormat('F Y');

        return response()->json([
            'status' => true,
            'message' => "✅ *Pengeluaran Rutin Berhasil Dibukukan!*\n\n".
                         "🏢 *{$recurringTransaction->name}*\n".
                         "💰 *{$formattedAmount}*\n".
                         "📅 Periode: {$periodName}\n".
                         '📂 Beban: '.($recurringTransaction->expenseAccount->name ?? 'Beban Operasional')."\n".
                         '💳 Bayar dari: '.($recurringTransaction->assetAccount->name ?? 'Kas Operasional')."\n".
                         "🔖 Ref: `{$journalEntry->reference}`",
            'data' => [
                'reference' => $journalEntry->reference,
                'name' => $recurringTransaction->name,
                'amount' => $finalAmount,
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
        $this->authorizeTelegramRole($request, [Employee::ROLE_OWNER, Employee::ROLE_AKUNTAN]);

        // Cek database sebelum eksekusi bayar apakah gaji bulan ini sudah dibayar
        if ($employee->isPaidThisMonth()) {
            $existingEntry = $employee->findExistingCurrentMonthPayrollJournal();
            $paidAt = $employee->last_paid_at
                ? $employee->last_paid_at->translatedFormat('d F Y, H:i').' WIB'
                : ($existingEntry ? Carbon::parse($existingEntry->date)->translatedFormat('d F Y, H:i').' WIB' : 'Bulan ini');

            $reference = $existingEntry?->reference ?? 'Sudah Dibukukan';
            $formattedAmount = 'Rp '.number_format($employee->total_salary, 0, ',', '.');
            $periodName = now()->translatedFormat('F Y');

            return response()->json([
                'status' => false,
                'already_paid' => true,
                'message' => "⚠️ *Gaji Karyawan Sudah Dibayar!*\n\n".
                             "👤 *{$employee->name}* ({$employee->position})\n".
                             "💰 *{$formattedAmount}*\n".
                             "📅 Periode: {$periodName}\n".
                             "⏰ Waktu Bayar: {$paidAt}\n".
                             "🔖 Ref: `{$reference}`\n\n".
                             '💡 *Info:* Gaji karyawan ini sudah tercatat sebelumnya di buku besar. Pembayaran tidak diproses ulang untuk mencegah duplikasi.',
                'data' => [
                    'already_paid' => true,
                    'reference' => $reference,
                    'name' => $employee->name,
                    'amount' => (float) $employee->total_salary,
                ],
            ], 200);
        }

        $customAmount = $request->filled('amount') ? (float) $request->input('amount') : null;
        $rawSource = $request->input('source', 'telegram');
        $source = str_starts_with(strtolower($rawSource), 'telegram') ? 'telegram' : $rawSource;

        $journalEntry = $employee->executePayrollPosting($customAmount, $source);

        return response()->json([
            'status' => true,
            'message' => "✅ *Penggajian Karyawan Berhasil Dibukukan!*\n\n".
                         "👤 *{$employee->name}*\n".
                         '💰 *Rp '.number_format($customAmount ?: (float) $employee->total_salary, 0, ',', '.')."*\n".
                         "🔖 Ref: `{$journalEntry->reference}`",
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
        $this->authorizeTelegramRole($request, [Employee::ROLE_OWNER, Employee::ROLE_AKUNTAN]);

        if ($employee->isPaidThisMonth()) {
            return response()->json([
                'status' => false,
                'already_paid' => true,
                'message' => "ℹ️ Penggajian karyawan '{$employee->name}' sudah berstatus dibayar untuk periode ini.",
            ], 200);
        }

        $employee->update(['last_paid_at' => now()]);

        return response()->json([
            'status' => true,
            'message' => "⏭️ Penggajian karyawan '{$employee->name}' telah dilewati untuk periode ini.",
        ]);
    }

    /**
     * Skip this period for a recurring expense via webhook.
     */
    public function skipRecurring(Request $request, RecurringTransaction $recurringTransaction)
    {
        $this->authorizeTelegramRole($request, [Employee::ROLE_OWNER, Employee::ROLE_AKUNTAN]);

        if ($recurringTransaction->isPaidForCurrentPeriod()) {
            return response()->json([
                'status' => false,
                'already_paid' => true,
                'message' => "ℹ️ Pengeluaran rutin '{$recurringTransaction->name}' sudah berstatus dibayar untuk periode ini.",
            ], 200);
        }

        $recurringTransaction->update(['last_posted_at' => now()]);

        return response()->json([
            'status' => true,
            'message' => "⏭️ Pengeluaran rutin '{$recurringTransaction->name}' telah dilewati untuk periode ini.",
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
            if ($recurring->isPaidForCurrentPeriod()) {
                return response()->json([
                    'status' => false,
                    'already_paid' => true,
                    'message' => "ℹ️ Pengeluaran rutin '#{$recurring->id} {$recurring->name}' sudah berstatus dibayar untuk periode ini.",
                ], 200);
            }

            $recurring->update(['last_posted_at' => now()]);

            return response()->json([
                'status' => true,
                'message' => "⏭️ Pengeluaran rutin '#{$recurring->id} {$recurring->name}' telah dilewati untuk periode ini.",
            ]);
        }

        // Cek database sebelum eksekusi bayar apakah tagihan sudah dibayar pada periode berjalan
        if ($recurring->isPaidForCurrentPeriod()) {
            $existingEntry = $recurring->findExistingCurrentPeriodJournal();
            $paidAt = $recurring->last_posted_at
                ? $recurring->last_posted_at->translatedFormat('d F Y, H:i').' WIB'
                : ($existingEntry ? Carbon::parse($existingEntry->date)->translatedFormat('d F Y, H:i').' WIB' : 'Periode ini');

            $reference = $existingEntry?->reference ?? 'Sudah Dibukukan';
            $formattedAmount = 'Rp '.number_format($recurring->amount, 0, ',', '.');
            $periodName = now()->translatedFormat('F Y');

            return response()->json([
                'status' => false,
                'already_paid' => true,
                'message' => "⚠️ *Pengeluaran Bulanan Sudah Dibayar!*\n\n".
                             "🏢 *#{$recurring->id} {$recurring->name}*\n".
                             "💰 *{$formattedAmount}* ({$recurring->frequency})\n".
                             "📅 Periode: {$periodName}\n".
                             "⏰ Waktu Bayar: {$paidAt}\n".
                             "🔖 Ref: `{$reference}`\n\n".
                             '💡 *Info:* Pengeluaran ini sudah tercatat sebelumnya di buku besar database. Pembayaran tidak diproses ulang untuk mencegah duplikasi.',
                'data' => [
                    'already_paid' => true,
                    'reference' => $reference,
                    'name' => $recurring->name,
                    'amount' => (float) $recurring->amount,
                ],
            ], 200);
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
            if ($employee->isPaidThisMonth()) {
                return response()->json([
                    'status' => false,
                    'already_paid' => true,
                    'message' => "ℹ️ Penggajian karyawan '#{$employee->id} {$employee->name}' sudah berstatus dibayar untuk periode ini.",
                ], 200);
            }

            $employee->update(['last_paid_at' => now()]);

            return response()->json([
                'status' => true,
                'message' => "⏭️ Penggajian karyawan '#{$employee->id} {$employee->name}' telah dilewati untuk periode ini.",
            ]);
        }

        // Cek database sebelum eksekusi bayar apakah gaji bulan ini sudah dibayar
        if ($employee->isPaidThisMonth()) {
            $existingEntry = $employee->findExistingCurrentMonthPayrollJournal();
            $paidAt = $employee->last_paid_at
                ? $employee->last_paid_at->translatedFormat('d F Y, H:i').' WIB'
                : ($existingEntry ? Carbon::parse($existingEntry->date)->translatedFormat('d F Y, H:i').' WIB' : 'Bulan ini');

            $reference = $existingEntry?->reference ?? 'Sudah Dibukukan';
            $formattedAmount = 'Rp '.number_format($employee->total_salary, 0, ',', '.');
            $periodName = now()->translatedFormat('F Y');

            return response()->json([
                'status' => false,
                'already_paid' => true,
                'message' => "⚠️ *Gaji Karyawan Sudah Dibayar!*\n\n".
                             "👤 *#{$employee->id} {$employee->name}* ({$employee->position})\n".
                             "💰 *{$formattedAmount}*\n".
                             "📅 Periode: {$periodName}\n".
                             "⏰ Waktu Bayar: {$paidAt}\n".
                             "🔖 Ref: `{$reference}`\n\n".
                             '💡 *Info:* Gaji karyawan ini sudah tercatat sebelumnya di buku besar database.',
                'data' => [
                    'already_paid' => true,
                    'reference' => $reference,
                    'name' => $employee->name,
                    'amount' => (float) $employee->total_salary,
                ],
            ], 200);
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
        $this->authorizeTelegramRole($request, EmployeeRole::aboveStaff());

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
        $this->authorizeTelegramRole($request, EmployeeRole::aboveStaff());

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

        $senderId = trim((string) ($request->input('sender_telegram_id') ?? ''));
        $employeeId = $request->input('employee_id');
        $csEmployee = $employeeId ? Employee::find($employeeId) : $this->resolveTelegramEmployee($request);

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
            $paymentStatus,
            $csEmployee
        ) {
            $accountCode = $validated['account_code'] ?? '1001';
            $account = Account::where('code', $accountCode)->first()
                ?? Account::where('type', 'asset')->first();

            $customOrder = CustomSuitOrder::create([
                'order_number' => $orderNumber,
                'employee_id' => $csEmployee?->id,
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
                'notes' => $validated['notes'] ?? 'Dibuat otomatis via Telegram n8n AI Agent'.($csEmployee ? " (CS: {$csEmployee->name})" : ''),
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

        // Berikan poin pelayanan jas custom ke CS jika teridentifikasi
        $pointInfo = null;
        if ($csEmployee) {
            $suitPoints = PointSetting::get('service:custom_suit', EmployeePointLog::PRODUCT_POINTS['custom_made_jas'] ?? 25);
            $csEmployee->addPoints(
                $suitPoints,
                EmployeePointLog::CATEGORY_ITEM_SALE,
                "Pesanan Jas Custom {$order->order_number} ({$order->customer_name})",
                'custom_order',
                $order->id,
                $senderId ? "Telegram:{$senderId}" : 'CS'
            );

            $pointInfo = [
                'points' => $suitPoints,
                'employee_name' => $csEmployee->name,
                'new_points' => $csEmployee->fresh()->current_points,
            ];
        }

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

        if ($pointInfo) {
            $msg[] = '───────────────────';
            $msg[] = "⭐ *Poin Pelayanan CS*: +{$pointInfo['points']} pt ({$pointInfo['employee_name']})";
            $msg[] = "   • Total Poin CS Sekarang: *{$pointInfo['new_points']} pt*";
        }

        return response()->json([
            'status' => true,
            'order' => $order,
            'points_awarded' => $pointInfo,
            'message' => implode("\n", $msg),
        ]);
    }

    /**
     * Webhook n8n: Tracking status pengerjaan pesanan jas custom via Telegram.
     */
    public function trackCustomSuit(Request $request)
    {
        $query = CustomSuitOrder::query();

        if ($search = $request->input('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhere('customer_phone', 'like', "%{$search}%");
            });
        }

        if ($status = $request->input('status')) {
            $query->where('production_status', $status);
        } else {
            // Default: tampilkan yang masih aktif jika tidak mencari spesifik
            if (! $search) {
                $query->whereNotIn('production_status', ['completed', 'cancelled']);
            }
        }

        $orders = $query->latest('id')->limit(8)->get();

        if ($orders->isEmpty()) {
            return response()->json([
                'status' => true,
                'count' => 0,
                'orders' => [],
                'message' => 'ℹ️ Tidak ditemukan data pesanan jas custom yang cocok.'.($search ? " Pencarian: '{$search}'" : ''),
            ]);
        }

        $statusIcons = [
            'consultation' => '📐',
            'cutting_sewing' => '✂️',
            'fitting' => '👔',
            'finishing' => '✨',
            'ready' => '📦',
            'completed' => '✅',
            'cancelled' => '❌',
        ];

        $lines = ["🧵 *Tracking Pesanan Jas Custom Seven Management:*\n"];
        foreach ($orders as $o) {
            $icon = $statusIcons[$o->production_status] ?? '📍';
            $lines[] = "{$icon} *#{$o->order_number}* — {$o->customer_name}";
            $lines[] = "   • Model: {$o->suit_type_label} | {$o->color}";
            $lines[] = "   • Tahap: *{$o->production_status_label}*";
            $lines[] = "   • Bayar: {$o->payment_status_label} (Sisa: Rp ".number_format($o->remaining_payment, 0, ',', '.').')';
            if ($o->due_date) {
                $lines[] = '   • Target: '.$o->due_date->format('d M Y');
            }
            $lines[] = '';
        }

        return response()->json([
            'status' => true,
            'count' => $orders->count(),
            'orders' => $orders,
            'message' => trim(implode("\n", $lines)),
        ]);
    }

    /**
     * Webhook n8n: Update status produksi pesanan jas custom dari Telegram.
     */
    public function updateCustomSuitStatus(Request $request)
    {
        $validated = $request->validate([
            'order_query' => 'required|string',
            'status' => 'required|string',
        ]);

        $search = trim($validated['order_query']);
        $order = CustomSuitOrder::where('order_number', $search)
            ->orWhere('order_number', 'like', "%{$search}%")
            ->orWhere('customer_name', 'like', "%{$search}%")
            ->latest('id')
            ->first();

        if (! $order) {
            return response()->json([
                'status' => false,
                'message' => "❌ Pesanan jas dengan kata kunci '{$search}' tidak ditemukan.",
            ], 404);
        }

        $aliasMap = [
            'konsultasi' => 'consultation',
            'ukur' => 'consultation',
            'potong' => 'cutting_sewing',
            'jahit' => 'cutting_sewing',
            'potong_jahit' => 'cutting_sewing',
            'fitting' => 'fitting',
            'finishing' => 'finishing',
            'press' => 'finishing',
            'siap' => 'ready',
            'ready' => 'ready',
            'ambil' => 'ready',
            'selesai' => 'completed',
            'batal' => 'cancelled',
        ];

        $targetStatus = strtolower(trim($validated['status']));
        $finalStatus = $aliasMap[$targetStatus] ?? $targetStatus;

        if (! array_key_exists($finalStatus, CustomSuitOrder::PRODUCTION_STATUSES)) {
            $validList = implode(', ', array_keys($aliasMap));

            return response()->json([
                'status' => false,
                'message' => "❌ Status '{$validated['status']}' tidak valid. Pilihan status: {$validList}.",
            ], 422);
        }

        $oldStatusLabel = $order->production_status_label;
        $order->update(['production_status' => $finalStatus]);
        $newStatusLabel = $order->fresh()->production_status_label;

        $statusIcons = [
            'consultation' => '📐',
            'cutting_sewing' => '✂️',
            'fitting' => '👔',
            'finishing' => '✨',
            'ready' => '📦',
            'completed' => '✅',
            'cancelled' => '❌',
        ];
        $icon = $statusIcons[$finalStatus] ?? '📍';

        $msg = [
            "{$icon} *Status Produksi Jas Berhasil Diperbarui!*",
            "📋 *No. Pesanan*: `{$order->order_number}`",
            "👤 *Pelanggan*: {$order->customer_name}",
            "👔 *Model*: {$order->suit_type_label}",
            "🔄 *Perubahan*: {$oldStatusLabel} ➔ *{$newStatusLabel}*",
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
            'payment_method' => 'nullable|string',
            'sender_telegram_id' => 'nullable|string',
            'employee_id' => 'nullable|exists:employees,id',
            'is_rental' => 'nullable|boolean',
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

        $senderId = trim((string) ($request->input('sender_telegram_id') ?? ''));
        $employeeId = $request->input('employee_id');
        $csEmployee = $employeeId ? Employee::find($employeeId) : $this->resolveTelegramEmployee($request);

        $paymentMethod = strtolower($request->input('payment_method', 'cash'));
        $isRental = $request->boolean('is_rental');

        $sale = DB::transaction(function () use ($product, $qty, $validated, $account, $csEmployee, $paymentMethod, $isRental) {
            $product->decrement('stock', $qty);

            $unitCost = (float) $product->cost_price;
            $unitPrice = (float) ($isRental ? $product->effective_rental_price : $product->selling_price);
            $subtotal = $unitPrice * $qty;
            $totalCost = $unitCost * $qty;
            $invoiceNumber = ($isRental ? 'RNT-' : 'RTL-').date('Ymd').'-'.strtoupper(Str::random(4));

            $retailSale = RetailSale::create([
                'invoice_number' => $invoiceNumber,
                'employee_id' => $csEmployee?->id,
                'transaction_type' => $isRental ? 'rental' : 'sale',
                'sale_date' => now(),
                'customer_name' => $validated['customer_name'] ?? 'Pelanggan Telegram',
                'payment_method' => $paymentMethod,
                'account_id' => $account?->id,
                'total_amount' => $subtotal,
                'total_cost' => $totalCost,
                'notes' => 'Transaksi via Bot Telegram n8n'.($csEmployee ? " (CS: {$csEmployee->name})" : ''),
            ]);

            RetailSaleItem::create([
                'retail_sale_id' => $retailSale->id,
                'product_id' => $product->id,
                'transaction_type' => $isRental ? 'rental' : 'sale',
                'quantity' => $qty,
                'unit_cost_price' => $unitCost,
                'unit_selling_price' => $isRental ? 0 : $unitPrice,
                'unit_rental_price' => $isRental ? $unitPrice : 0,
                'subtotal' => $subtotal,
            ]);

            // Posting otomatis ke Akuntansi
            $pendapatanAccount = Account::firstOrCreate(
                ['code' => $isRental ? '4003' : '4002'],
                ['name' => $isRental ? 'Pendapatan Sewa Pakaian' : 'Pendapatan Penjualan Retail', 'type' => 'revenue']
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
                'description' => ($isRental ? 'Sewa ' : 'Penjualan ')."Retail {$product->name} x{$qty} ({$invoiceNumber})",
                'date' => now(),
                'source' => 'retail',
                'status' => 'verified',
            ]);

            if ($account) {
                JournalEntryLine::create([
                    'journal_entry_id' => $journal->id,
                    'account_id' => $account->id,
                    'description' => "Penerimaan kas/bank {$invoiceNumber}",
                    'debit' => $subtotal,
                    'credit' => 0,
                ]);
            }

            JournalEntryLine::create([
                'journal_entry_id' => $journal->id,
                'account_id' => $pendapatanAccount->id,
                'description' => "Pendapatan retail {$invoiceNumber}",
                'debit' => 0,
                'credit' => $subtotal,
            ]);

            if (! $isRental && $totalCost > 0) {
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

        // Hitung & Berikan Poin Pelayanan ke CS jika terdeteksi
        $pointInfo = null;
        if ($csEmployee) {
            $itemBasePoints = $isRental
                ? PointSetting::get('service:rental', EmployeePointLog::DEFAULT_RENT_POINTS)
                : $product->effective_point_reward;

            $qtyBonus = ($qty > 1) ? ($qty - 1) * PointSetting::get('service:quantity_extra', EmployeePointLog::DEFAULT_QTY_EXTRA_POINTS) : 0;
            $codBonus = ($paymentMethod === 'cod') ? PointSetting::get('service:cod', EmployeePointLog::DEFAULT_COD_POINTS) : 0;
            $totalPointsEarned = $itemBasePoints + $qtyBonus + $codBonus;

            $pointCategory = $isRental ? EmployeePointLog::CATEGORY_ITEM_RENT : EmployeePointLog::CATEGORY_ITEM_SALE;
            $actor = $senderId ? "Telegram:{$senderId}" : 'CS';

            // 1. Poin dasar item
            $csEmployee->addPoints(
                $itemBasePoints,
                $pointCategory,
                ($isRental ? 'Sewa ' : 'Jual ')."{$product->name} (x{$qty})",
                'retail_sale',
                $sale->id,
                $actor
            );

            // 2. Bonus kuantitas (jika > 1 pcs)
            if ($qtyBonus > 0) {
                $csEmployee->addPoints(
                    $qtyBonus,
                    EmployeePointLog::CATEGORY_QUANTITY,
                    "Bonus kuantitas x{$qty} {$product->name}",
                    'retail_sale',
                    $sale->id,
                    $actor
                );
            }

            // 3. Bonus Cash on Delivery (COD)
            if ($codBonus > 0) {
                $csEmployee->addPoints(
                    $codBonus,
                    EmployeePointLog::CATEGORY_COD,
                    "Layanan Cash on Delivery (COD) {$product->name}",
                    'retail_sale',
                    $sale->id,
                    $actor
                );
            }

            $pointInfo = [
                'total' => $totalPointsEarned,
                'item_points' => $itemBasePoints,
                'qty_bonus' => $qtyBonus,
                'cod_bonus' => $codBonus,
                'employee_name' => $csEmployee->name,
                'new_points' => $csEmployee->fresh()->current_points,
            ];
        }

        $typeLabel = $isRental ? 'Penyewaan Baju' : 'Penjualan Retail';
        $msg = [
            "🛍️ *{$typeLabel} Berhasil Dicatat!*",
            "🧾 *Invoice*: `{$sale->invoice_number}`",
            "📦 *Item*: {$product->name} (x{$qty})",
            '💳 *Metode*: '.strtoupper($paymentMethod),
            '💰 *Total Bayar*: Rp '.number_format($sale->total_amount, 0, ',', '.'),
            '📉 *Sisa Stok*: '.($product->fresh()->stock).' pcs',
        ];

        if ($pointInfo) {
            $msg[] = '───────────────────';
            $msg[] = "⭐ *Poin Pelayanan CS*: +{$pointInfo['total']} pt ({$pointInfo['employee_name']})";
            $msg[] = "   • Item: +{$pointInfo['item_points']} pt | Qty: +{$pointInfo['qty_bonus']} pt".($pointInfo['cod_bonus'] > 0 ? " | COD: +{$pointInfo['cod_bonus']} pt" : '');
            $msg[] = "   • Total Poin CS Sekarang: *{$pointInfo['new_points']} pt*";
        }

        return response()->json([
            'status' => true,
            'sale' => $sale,
            'points_awarded' => $pointInfo,
            'message' => implode("\n", $msg),
        ]);
    }

    /**
     * Webhook n8n: Cek stok gudang bahan baku & peringatan stok menipis (Low Stock Alert).
     */
    public function checkMaterialStock(Request $request)
    {
        $query = Material::physical();

        $isLowStockOnly = $request->boolean('low_stock');
        if ($isLowStockOnly) {
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

        $totalLowStock = Material::physical()->whereColumn('stock', '<=', 'min_stock')->count();
        $materials = $query->orderBy('stock', 'asc')->limit(20)->get();

        if ($materials->isEmpty()) {
            return response()->json([
                'status' => true,
                'count' => 0,
                'low_stock_count' => $totalLowStock,
                'materials' => [],
                'message' => $isLowStockOnly
                    ? '✅ *Semua Stok Bahan Baku Aman!*\nTidak ada bahan baku atau aksesoris yang berada di bawah batas minimum.'
                    : 'ℹ️ Tidak ditemukan bahan baku yang sesuai kriteria pencarian.',
            ]);
        }

        $lines = [];
        if ($isLowStockOnly) {
            $lines[] = '⚠️ *Peringatan Stok Bahan Baku Menipis / Habis!*';
            $lines[] = "Ditemukan *{$materials->count()}* bahan yang memerlukan pengadaan ulang:\n";
        } else {
            $lines[] = "🧶 *Gudang Bahan Baku & Aksesoris Seven Management:*\n";
        }

        foreach ($materials as $m) {
            $statusIcon = $m->stock <= 0 ? '🔴' : ($m->stock <= $m->min_stock ? '⚠️' : '✅');
            $stockText = $m->formatted_stock;
            $minText = number_format($m->min_stock, 0, ',', '.').' '.$m->unit;

            $lines[] = "{$statusIcon} *{$m->name}* (`{$m->code}`)";
            $lines[] = "   • Stok: *{$stockText}* (Min: {$minText})";
            $lines[] = "   • Kategori: {$m->category_label}";
            $lines[] = "   • Biaya Std: {$m->formatted_standard_cost}/{$m->unit}";
            $lines[] = '';
        }

        if ($totalLowStock > 0 && ! $isLowStockOnly) {
            $lines[] = "⚠️ *Perhatian*: Ada *{$totalLowStock}* bahan di bawah batas minimum stok!";
        }

        $lines[] = '💡 *Restock Cepat via Bot*:';
        $lines[] = '`/restock <kode/nama> <qty> [total_biaya] [kas/bank]`';
        $lines[] = 'Contoh: `/restock KAIN-WOL 10 1500000 bank`';

        return response()->json([
            'status' => true,
            'count' => $materials->count(),
            'low_stock_count' => $totalLowStock,
            'materials' => $materials,
            'message' => trim(implode("\n", $lines)),
        ]);
    }

    /**
     * Webhook n8n: Catat pembelian / restock bahan baku langsung dari Telegram.
     */
    public function recordMaterialRestock(Request $request)
    {
        $validated = $request->validate([
            'material_code' => 'required_without:material_id|string',
            'material_id' => 'nullable|exists:materials,id',
            'quantity' => 'required|numeric|min:0.001',
            'unit_cost' => 'nullable|numeric|min:0',
            'total_cost' => 'nullable|numeric|min:0',
            'account_code' => 'nullable|string',
            'reference_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:255',
        ]);

        $search = trim($validated['material_code'] ?? '');
        $material = ! empty($validated['material_id'])
            ? Material::find($validated['material_id'])
            : Material::where('code', $search)
                ->orWhere('code', 'like', "%{$search}%")
                ->orWhere('name', 'like', "%{$search}%")
                ->first();

        if (! $material) {
            return response()->json([
                'status' => false,
                'message' => "❌ Bahan baku dengan kata kunci '{$search}' tidak ditemukan.",
            ], 404);
        }

        if (! $material->isPhysical()) {
            return response()->json([
                'status' => false,
                'message' => "❌ Komponen '{$material->name}' merupakan biaya jasa/overhead, bukan material fisik gudang.",
            ], 422);
        }

        $qty = (float) $validated['quantity'];

        // Tentukan unit cost dan total cost
        if (! empty($validated['total_cost']) && (float) $validated['total_cost'] > 0) {
            $totalCost = (float) $validated['total_cost'];
            $unitCost = $totalCost / $qty;
        } elseif (! empty($validated['unit_cost']) && (float) $validated['unit_cost'] > 0) {
            $unitCost = (float) $validated['unit_cost'];
            $totalCost = $unitCost * $qty;
        } else {
            $unitCost = (float) $material->standard_cost;
            $totalCost = $unitCost * $qty;
        }

        // Akun Kas / Bank pembayaran
        $accountSearch = strtolower(trim($validated['account_code'] ?? '1001'));
        if (str_contains($accountSearch, 'bank') || $accountSearch === '1002') {
            $targetCode = '1002';
        } else {
            $targetCode = '1001';
        }

        $fundAccount = Account::where('code', $targetCode)->first()
            ?? Account::where('code', '1001')->first()
            ?? Account::where('type', 'asset')->first();

        // Akun Persediaan Bahan Baku (1004)
        $inventoryAccount = ($material->account_id ? Account::find($material->account_id) : null)
            ?? Account::where('code', '1004')->first()
            ?? Account::firstOrCreate(
                ['code' => '1004'],
                ['name' => 'Persediaan Bahan Baku & Pembantu', 'type' => 'asset']
            );

        $refNumber = ! empty($validated['reference_number']) ? $validated['reference_number'] : ('RST-'.date('Ymd').'-'.strtoupper(Str::random(4)));
        $notes = ! empty($validated['notes']) ? $validated['notes'] : "Restock {$material->name} x{$qty} {$material->unit} via Telegram";

        $movement = DB::transaction(function () use (
            $material,
            $qty,
            $unitCost,
            $totalCost,
            $refNumber,
            $notes,
            $fundAccount,
            $inventoryAccount
        ) {
            // 1. Tambah stok bahan
            $material->increment('stock', $qty);

            // 2. Update harga standar jika harga beli baru diisi spesifik
            if ($unitCost > 0) {
                $material->update(['standard_cost' => $unitCost]);
            }

            // 3. Catat kartu mutasi stok
            $stockMovement = MaterialStockMovement::create([
                'material_id' => $material->id,
                'type' => 'in',
                'quantity' => $qty,
                'unit_cost' => $unitCost,
                'reference_type' => 'purchase',
                'reference_number' => $refNumber,
                'notes' => $notes,
            ]);

            // 4. Catat Jurnal Pembukuan Otomatis (Double Entry)
            if ($totalCost > 0 && $fundAccount && $inventoryAccount) {
                $journal = JournalEntry::create([
                    'reference' => $refNumber,
                    'description' => "Pembelian/Restock {$material->name} ({$refNumber})",
                    'date' => now(),
                    'source' => 'material',
                    'status' => 'verified',
                ]);

                // Debit Persediaan Bahan Baku
                JournalEntryLine::create([
                    'journal_entry_id' => $journal->id,
                    'account_id' => $inventoryAccount->id,
                    'description' => "Persediaan masuk {$material->name} x{$qty} {$material->unit}",
                    'debit' => $totalCost,
                    'credit' => 0,
                ]);

                // Credit Kas/Bank
                JournalEntryLine::create([
                    'journal_entry_id' => $journal->id,
                    'account_id' => $fundAccount->id,
                    'description' => "Pengeluaran pembelian bahan baku ({$refNumber})",
                    'debit' => 0,
                    'credit' => $totalCost,
                ]);
            }

            return $stockMovement;
        });

        $updatedMaterial = $material->fresh();
        $msg = [
            '🧶 *Restock Bahan Baku Berhasil!*',
            "📦 *Bahan*: {$updatedMaterial->name} (`{$updatedMaterial->code}`)",
            "➕ *Jumlah Masuk*: +{$qty} {$updatedMaterial->unit}",
            "📊 *Total Stok Sekarang*: *{$updatedMaterial->formatted_stock}*",
            '💰 *Total Pembelian*: Rp '.number_format($totalCost, 0, ',', '.').($fundAccount ? " (via {$fundAccount->name})" : ''),
            "🧾 *No. Referensi*: `{$refNumber}`",
        ];

        return response()->json([
            'status' => true,
            'material' => $updatedMaterial,
            'movement' => $movement,
            'message' => implode("\n", $msg),
        ]);
    }

    /**
     * Webhook n8n: Rekap klasemen poin insentif seluruh karyawan & penjahit.
     */
    public function listEmployeePoints(Request $request)
    {
        $auth = $this->authorizeTelegramRole($request);
        $isCs = ($auth['role'] === Employee::ROLE_CS);

        $employees = Employee::active()
            ->orderBy('current_points', 'desc')
            ->orderBy('name', 'asc')
            ->get();

        if ($employees->isEmpty()) {
            return response()->json([
                'status' => true,
                'count' => 0,
                'total_points' => 0,
                'employees' => [],
                'message' => 'ℹ️ Belum ada karyawan aktif yang terdaftar di sistem.',
            ]);
        }

        $totalPoints = $employees->sum('current_points');
        $lines = ['🏆 *Klasemen Poin Insentif Karyawan & Penjahit:*', "Total Terkumpul: *{$totalPoints} Poin*\n"];

        $medals = ['🥇', '🥈', '🥉'];
        foreach ($employees as $index => $emp) {
            $rankIcon = $medals[$index] ?? '🎖️';
            $points = (int) $emp->current_points;
            $tier = $emp->tier_label;
            $bonus = $emp->formatted_bonus_salary;

            $lines[] = "{$rankIcon} *#".($index + 1).". {$emp->name}* ({$emp->position})";
            $lines[] = "   • ⭐ Poin: *{$points} pt* | {$tier}";
            // Privasi: CS tidak diperkenankan melihat nominal rupiah gaji/bonus staf lain di publik
            if (! $isCs && $points > 0) {
                $lines[] = "   • 💰 Bonus: {$bonus} (Total Gaji: {$emp->formatted_total_salary})";
            }
            $lines[] = '';
        }

        if ($isCs) {
            $lines[] = '💡 *Info*: Untuk melihat rincian bonus & estimasi gaji pribadi Anda, gunakan `/poinsaya`.';
        } else {
            $lines[] = '💡 *Format Cepat Tambah Poin (Khusus Owner/Akuntan)*:';
            $lines[] = '`/poin <nama/id> <+poin> [kategori] [keterangan]`';
            $lines[] = 'Contoh: `/poin Budi 15 review Pelanggan puas bintang 5`';
        }

        return response()->json([
            'status' => true,
            'is_cs' => $isCs,
            'count' => $employees->count(),
            'total_points' => $totalPoints,
            'employees' => $isCs ? $employees->makeHidden(['base_salary', 'bonus_salary', 'total_salary', 'formatted_bonus_salary', 'formatted_total_salary']) : $employees,
            'message' => trim(implode("\n", $lines)),
        ]);
    }

    /**
     * Webhook n8n: Cek rekapitulasi poin & estimasi bonus pribadi karyawan / CS.
     */
    public function myPoints(Request $request)
    {
        $employeeId = $request->input('employee_id');
        $employee = $employeeId ? Employee::find($employeeId) : $this->resolveTelegramEmployee($request);

        if (! $employee) {
            $senderPhone = trim((string) ($request->input('sender_phone') ?? $request->input('phone') ?? $request->header('X-Telegram-Phone') ?? ''));
            $senderId = trim((string) ($request->input('sender_telegram_id') ?? $request->header('X-Telegram-User-Id') ?? ''));
            $identifier = $senderPhone ?: ($senderId ? "ID: {$senderId}" : 'nomor HP / Telegram Anda');

            return response()->json([
                'status' => false,
                'message' => "❌ Akun Telegram / Nomor HP Anda belum terhubung dengan data karyawan di sistem Seven Management.\n\n💡 Hubungi Admin/Akuntan untuk mendaftarkan Nomor HP Anda (`{$identifier}`) pada data karyawan.",
            ], 404);
        }

        $now = now();
        $logs = $employee->pointLogs()
            ->whereMonth('created_at', $now->month)
            ->whereYear('created_at', $now->year)
            ->get();

        $categorySummary = [];
        foreach (EmployeePointLog::CATEGORIES as $key => $label) {
            $sum = $logs->where('category', $key)->sum('points');
            if ($sum != 0) {
                $categorySummary[$label] = $sum;
            }
        }

        // Hitung target tier berikutnya
        $currentPoints = (int) $employee->current_points;
        $nextTierInfo = '';
        if ($currentPoints < 200) {
            $needed = 200 - $currentPoints;
            $nextTierInfo = "🎯 *Target*: Butuh *{$needed} pt* lagi untuk mencapai *Tier 1* (Rp 1.000 / pt)";
        } elseif ($currentPoints < 295) {
            $needed = 295 - $currentPoints;
            $nextTierInfo = "🎯 *Target*: Butuh *{$needed} pt* lagi untuk mencapai *Tier 2* (Rp 1.400 / pt)";
        } elseif ($currentPoints < 370) {
            $needed = 370 - $currentPoints;
            $nextTierInfo = "🎯 *Target*: Butuh *{$needed} pt* lagi untuk mencapai *Tier 3* (Rp 1.800 / pt)";
        } elseif ($currentPoints < 445) {
            $needed = 445 - $currentPoints;
            $nextTierInfo = "🎯 *Target*: Butuh *{$needed} pt* lagi untuk mencapai *Tier 4* (Rp 2.200 / pt)";
        } elseif ($currentPoints < 500) {
            $needed = 500 - $currentPoints;
            $nextTierInfo = "🎯 *Target*: Butuh *{$needed} pt* lagi untuk mencapai *Tier 5 (Maksimal)* (Rp 2.600 / pt)";
        } else {
            $nextTierInfo = '🏆 *Luar Biasa!* Anda sudah berada di *Tier Tertinggi (Tier 5)*!';
        }

        $msg = [
            "⭐ *Rekapitulasi Poin Pelayanan CS Bulan {$now->translatedFormat('F Y')}*",
            "👤 *Nama*: *{$employee->name}* ({$employee->position} - {$employee->role_label})",
            "📊 *Total Poin*: *{$currentPoints} pt*",
            "🏆 *Status Tingkatan*: *{$employee->tier_label}*",
        ];

        if ($employee->rate_per_point > 0) {
            $msg[] = '💵 *Tarif per Poin*: Rp '.number_format($employee->rate_per_point, 0, ',', '.').' / pt';
            $msg[] = "💰 *Estimasi Bonus Poin*: *{$employee->formatted_bonus_salary}*";
        } else {
            $msg[] = '💰 *Estimasi Bonus Poin*: Rp 0 (Belum mencapai batas Tier 1: 200 pt)';
        }

        if (! empty($categorySummary)) {
            $msg[] = "\n📋 *Rincian Poin Bulan Ini*:";
            foreach ($categorySummary as $catLabel => $pts) {
                $sign = $pts > 0 ? '+' : '';
                $msg[] = "   • {$catLabel}: *{$sign}{$pts} pt*";
            }
        }

        $msg[] = "\n{$nextTierInfo}";

        return response()->json([
            'status' => true,
            'employee' => $employee,
            'current_points' => $currentPoints,
            'tier_label' => $employee->tier_label,
            'rate_per_point' => (float) $employee->rate_per_point,
            'bonus_salary' => (float) $employee->bonus_salary,
            'category_summary' => $categorySummary,
            'message' => implode("\n", $msg),
        ]);
    }

    /**
     * Webhook n8n: Input penambahan / penyesuaian poin insentif karyawan via Telegram.
     */
    public function updateEmployeePoints(Request $request)
    {
        // Hanya Manajemen (Di Atas Staff) yang berhak memberi / menyesuaikan poin secara manual
        $auth = $this->authorizeTelegramRole($request, EmployeeRole::aboveStaff());

        $validated = $request->validate([
            'employee_query' => 'required_without:employee_id',
            'employee_id' => 'nullable|exists:employees,id',
            'points' => 'required|integer',
            'mode' => 'nullable|in:add,set,subtract',
            'operation' => 'nullable|in:add,set,subtract',
            'category' => 'nullable|string|in:review,cross_company,manual,cod,quantity,item_sale,item_rent',
            'notes' => 'nullable|string|max:255',
        ]);

        $mode = $validated['mode'] ?? $validated['operation'] ?? 'add';
        $points = (int) $validated['points'];
        $category = $validated['category'] ?? EmployeePointLog::CATEGORY_MANUAL;

        $employee = null;
        if (! empty($validated['employee_id'])) {
            $employee = Employee::find($validated['employee_id']);
            $search = $employee?->name ?? (string) $validated['employee_id'];
        } else {
            $search = trim((string) $validated['employee_query']);
            if (is_numeric($search)) {
                $employee = Employee::find((int) $search);
            }

            if (! $employee) {
                $employee = Employee::where('name', $search)
                    ->orWhere('name', 'like', "%{$search}%")
                    ->first();
            }
        }

        if (! $employee) {
            $activeNames = Employee::active()->pluck('name')->implode(', ');

            return response()->json([
                'status' => false,
                'message' => "❌ Karyawan dengan kata kunci '{$search}' tidak ditemukan.\n\n💡 Karyawan aktif terdaftar: {$activeNames}",
            ], 404);
        }

        $oldPoints = (int) $employee->current_points;
        $oldTier = $employee->tier_label;

        if ($mode === 'set') {
            $newPoints = max(0, $points);
            $sign = '=';
            $diff = $newPoints - $oldPoints;
        } elseif ($mode === 'subtract') {
            $newPoints = max(0, $oldPoints - abs($points));
            $sign = '-';
            $diff = -abs($points);
        } else {
            // mode add
            $newPoints = $oldPoints + $points;
            $sign = $points >= 0 ? '+' : '';
            $diff = $points;
        }

        $tierRate = Employee::getRateForPoints($newPoints);
        $updateData = ['current_points' => $newPoints];

        if ($tierRate > 0) {
            $updateData['rate_per_point'] = $tierRate;
        } elseif ($employee->rate_per_point <= 2600) {
            $updateData['rate_per_point'] = 0;
        }

        $employee->update($updateData);

        // Catat mutasi poin ke log riwayat
        $actor = $auth['employee']
            ? "{$auth['employee']->name} ({$auth['role']})"
            : ($auth['sender_id'] ? "Telegram:{$auth['sender_id']}" : 'Admin Webhook');
        $categoryLabel = EmployeePointLog::CATEGORIES[$category] ?? 'Penyesuaian Manual';
        $logNotes = ! empty($validated['notes']) ? $validated['notes'] : $categoryLabel;

        if ($diff !== 0) {
            $employee->pointLogs()->create([
                'points' => $diff,
                'category' => $category,
                'actor' => $actor,
                'notes' => $logNotes,
            ]);
        }

        $updated = $employee->fresh();

        $tierChanged = ($oldTier !== $updated->tier_label);
        $isTierUp = $tierChanged && ($newPoints > $oldPoints);

        $msg = [
            '⭐ *Poin Insentif Karyawan Berhasil Dicatat!*',
            "👤 *Karyawan*: *{$updated->name}* ({$updated->position} - {$updated->role_label})",
            "🏷️ *Kategori*: {$categoryLabel}",
            "📈 *Perubahan Poin*: {$sign}{$points} pt (Sebelumnya: {$oldPoints} pt ➔ *{$newPoints} pt*)",
            "🏆 *Status Tingkatan*: *{$updated->tier_label}*",
        ];

        if ($tierRate > 0) {
            $msg[] = '💵 *Tarif Insentif*: Rp '.number_format($updated->rate_per_point, 0, ',', '.').' / poin';
        }

        $msg[] = "💰 *Estimasi Bonus Poin*: {$updated->formatted_bonus_salary}";
        $msg[] = "💼 *Estimasi Total Gaji*: {$updated->formatted_total_salary} (Pokok + Bonus)";

        if ($isTierUp) {
            $msg[] = "🎉 *Selamat! Karyawan telah naik ke {$updated->tier_label}!*";
        }

        if (! empty($validated['notes'])) {
            $msg[] = "📝 *Catatan*: {$validated['notes']}";
        }

        return response()->json([
            'status' => true,
            'employee' => $updated,
            'category' => $category,
            'old_points' => $oldPoints,
            'new_points' => $newPoints,
            'diff' => $diff,
            'message' => implode("\n", $msg),
        ]);
    }
}
