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
            'type'                 => 'nullable|string',        // hapus in: biar normalize yang handle
            'date'                 => 'nullable|string',
            'source'               => 'nullable|string|max:50',
            'reference'            => 'nullable|string|max:100',
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

            // Double-entry mode jika lines disediakan
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
                // Simple mode: auto generate double-entry
                $amount = $validated['amount'];
                $type   = $validated['type'];

                // Kas Operasional (1001)
                $cashAccount = Account::firstOrCreate(
                    ['code' => '1001'],
                    ['name' => 'Kas Operasional', 'type' => 'asset']
                );

                if ($type === 'expense') {
                    $expenseAccount = Account::firstOrCreate(
                        ['code' => $validated['account_code'] ?? '5001'],
                        ['name' => $validated['category'] ?? 'Beban Operasional', 'type' => 'expense']
                    );

                    // Debit Beban, Credit Kas
                    JournalEntryLine::create([
                        'journal_entry_id' => $journalEntry->id,
                        'account_id'       => $expenseAccount->id,
                        'description'      => $journalEntry->description,
                        'debit'            => $amount,
                        'credit'           => 0,
                    ]);

                    JournalEntryLine::create([
                        'journal_entry_id' => $journalEntry->id,
                        'account_id'       => $cashAccount->id,
                        'description'      => 'Pembayaran Kas',
                        'debit'            => 0,
                        'credit'           => $amount,
                    ]);

                } elseif ($type === 'income') {
                    $incomeAccount = Account::firstOrCreate(
                        ['code' => $validated['account_code'] ?? '4001'],
                        ['name' => $validated['category'] ?? 'Pendapatan Usaha', 'type' => 'revenue']
                    );

                    // Debit Kas, Credit Pendapatan
                    JournalEntryLine::create([
                        'journal_entry_id' => $journalEntry->id,
                        'account_id'       => $cashAccount->id,
                        'description'      => 'Penerimaan Kas',
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
}