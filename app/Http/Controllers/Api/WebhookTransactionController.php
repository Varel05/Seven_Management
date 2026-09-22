<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
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
            $transactionDate = !empty($validated['date'])
                ? Carbon::parse($validated['date'])
                : now();

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
}