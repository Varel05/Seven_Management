<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Employee;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\Material;
use App\Models\TailorPayroll;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TailorPayrollController extends Controller
{
    /**
     * Tampilkan riwayat slip upah penjahit dan ringkasan biaya tenaga kerja borongan.
     */
    public function index(Request $request)
    {
        $query = TailorPayroll::with(['employee', 'items', 'advances', 'journalEntry'])
            ->latest('payroll_date');

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('tailor_name', 'like', "%{$search}%")
                    ->orWhere('period_label', 'like', "%{$search}%");
            });
        }

        if ($request->filled('month')) {
            $month = Carbon::parse($request->input('month'));
            $query->whereYear('payroll_date', $month->year)
                ->whereMonth('payroll_date', $month->month);
        }

        $payrolls = $query->paginate(15)->withQueryString();

        // Statistik
        $thisMonth = now();
        $monthlyPayrolls = TailorPayroll::whereYear('payroll_date', $thisMonth->year)
            ->whereMonth('payroll_date', $thisMonth->month)
            ->get();

        $totalWagesThisMonth = $monthlyPayrolls->sum('total_wage');
        $totalTakeHomePayThisMonth = $monthlyPayrolls->sum('take_home_pay');
        $totalBonThisMonth = $monthlyPayrolls->sum('total_bon');
        $totalPiecesThisMonth = $monthlyPayrolls->sum('total_pieces');

        // Master tarif upah penjahit di HPP (Direct Labor)
        $laborMaterials = Material::where('category', 'direct_labor')->orderBy('code')->get();

        return view('tailor-payrolls.index', compact(
            'payrolls',
            'totalWagesThisMonth',
            'totalTakeHomePayThisMonth',
            'totalBonThisMonth',
            'totalPiecesThisMonth',
            'laborMaterials'
        ));
    }

    /**
     * Tampilkan formulir pembuatan slip upah penjahit baru.
     */
    public function create()
    {
        // Ambil daftar karyawan (terutama penjahit / staff)
        $employees = Employee::where('status', 'active')
            ->orderBy('name')
            ->get();

        // Ambil seluruh komponen upah tenaga kerja langsung dari HPP (Direct Labor)
        $laborMaterials = Material::where('category', 'direct_labor')->orderBy('code')->get();

        // Akun pembayaran (Kas & Bank)
        $paymentAccounts = Account::where('type', 'asset')
            ->whereIn('code', ['1001', '1002'])
            ->get();

        // Tanggal dan label hari default (Indonesia)
        $today = now();
        $defaultDate = $today->format('Y-m-d');
        $defaultPeriodLabel = $today->translatedFormat('l, j F Y');

        return view('tailor-payrolls.create', compact(
            'employees',
            'laborMaterials',
            'paymentAccounts',
            'defaultDate',
            'defaultPeriodLabel'
        ));
    }

    /**
     * Simpan slip upah penjahit, rincian pekerjaan, kasbon, dan jurnal otomatis.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'nullable|exists:employees,id',
            'tailor_name' => 'required|string|max:255',
            'payroll_date' => 'required|date',
            'period_label' => 'nullable|string|max:255',
            'payment_method' => 'required|string|max:50',
            'account_id' => 'nullable|exists:accounts,id',
            'notes' => 'nullable|string|max:1000',

            // Rincian Item Jahitan
            'items' => 'required|array|min:1',
            'items.*.item_name' => 'required|string|max:255',
            'items.*.quantity' => 'required|integer|min:0',
            'items.*.rate_per_piece' => 'required|numeric|min:0',
            'items.*.material_id' => 'nullable|exists:materials,id',

            // Rincian Kasbon / Bon
            'advances' => 'nullable|array',
            'advances.*.advance_date' => 'nullable|date',
            'advances.*.description' => 'nullable|string|max:255',
            'advances.*.amount' => 'nullable|numeric|min:0',
        ]);

        $payroll = DB::transaction(function () use ($validated, $request) {
            $payrollDate = Carbon::parse($validated['payroll_date']);
            $periodLabel = ! empty($validated['period_label'])
                ? $validated['period_label']
                : $payrollDate->translatedFormat('l, j F Y');

            // Kalkulasi Total Item Jahitan
            $totalPieces = 0;
            $totalWage = 0.0;
            $itemsData = [];

            foreach ($validated['items'] as $index => $itemInput) {
                $qty = (int) ($itemInput['quantity'] ?? 0);
                $rate = (float) ($itemInput['rate_per_piece'] ?? 0);
                $subtotal = $qty * $rate;

                $totalPieces += $qty;
                $totalWage += $subtotal;

                $itemsData[] = [
                    'material_id' => $itemInput['material_id'] ?? null,
                    'item_name' => trim($itemInput['item_name']),
                    'quantity' => $qty,
                    'rate_per_piece' => $rate,
                    'subtotal' => $subtotal,
                    'order' => $index + 1,
                ];
            }

            // Kalkulasi Total Kasbon / Bon
            $totalBon = 0.0;
            $advancesData = [];

            if (! empty($validated['advances'])) {
                foreach ($validated['advances'] as $advanceInput) {
                    $amount = (float) ($advanceInput['amount'] ?? 0);
                    if ($amount > 0) {
                        $totalBon += $amount;
                        $advancesData[] = [
                            'advance_date' => $advanceInput['advance_date'] ?? $payrollDate->format('Y-m-d'),
                            'description' => trim($advanceInput['description'] ?? 'Kasbon penjahit'),
                            'amount' => $amount,
                        ];
                    }
                }
            }

            // Hitung Take Home Pay Bersih
            $takeHomePay = max(0.0, $totalWage - $totalBon);

            // Buat Record Header Slip Upah
            $tailorPayroll = TailorPayroll::create([
                'employee_id' => $validated['employee_id'] ?? null,
                'tailor_name' => trim($validated['tailor_name']),
                'payroll_date' => $payrollDate->format('Y-m-d'),
                'period_label' => $periodLabel,
                'payment_method' => $validated['payment_method'],
                'total_pieces' => $totalPieces,
                'total_wage' => $totalWage,
                'total_bon' => $totalBon,
                'take_home_pay' => $takeHomePay,
                'notes' => $validated['notes'] ?? null,
                'created_by_user_id' => auth()->id(),
                'paid_at' => now(),
            ]);

            // Simpan Item-Item Jahitan
            foreach ($itemsData as $item) {
                $tailorPayroll->items()->create($item);
            }

            // Simpan Rincian Kasbon
            foreach ($advancesData as $advance) {
                $tailorPayroll->advances()->create($advance);
            }

            // Pembukuan Otomatis ke Buku Besar Akuntansi
            $reference = 'UPH-JHT-'.strtoupper(Str::random(6));

            // Akun Biaya Tenaga Kerja / Beban Gaji (5002)
            $expenseAccount = Account::firstOrCreate(
                ['code' => '5002'],
                ['name' => 'Beban Gaji', 'type' => 'expense']
            );

            // Akun Kas / Bank Pembayaran
            $paymentAccountId = $request->input('account_id');
            $assetAccount = null;
            if ($paymentAccountId) {
                $assetAccount = Account::find($paymentAccountId);
            }
            if (! $assetAccount) {
                $assetAccount = str_contains(strtolower($validated['payment_method']), 'transfer')
                    ? Account::where('code', '1002')->first()
                    : Account::where('code', '1001')->first();
            }
            if (! $assetAccount) {
                $assetAccount = Account::where('type', 'asset')->first();
            }

            // Akun Piutang Kasbon (1005)
            $advanceAccount = Account::firstOrCreate(
                ['code' => '1005'],
                ['name' => 'Piutang Kasbon Karyawan & Penjahit', 'type' => 'asset']
            );

            $journal = JournalEntry::create([
                'reference' => $reference,
                'description' => "Upah Jahit Borongan: {$tailorPayroll->tailor_name} ({$totalPieces} pcs, Slip: {$periodLabel})",
                'date' => $payrollDate,
                'source' => 'website',
                'status' => 'verified',
            ]);

            // 1. DEBIT: Beban Gaji & Upah Penjahit (Total Kotor)
            JournalEntryLine::create([
                'journal_entry_id' => $journal->id,
                'account_id' => $expenseAccount->id,
                'debit' => $totalWage,
                'credit' => 0,
                'description' => "Upah Borongan {$tailorPayroll->tailor_name} ({$totalPieces} pcs)",
            ]);

            // 2. KREDIT: Kas / Bank (Sebesar Take Home Pay Bersih)
            if ($takeHomePay > 0 && $assetAccount) {
                JournalEntryLine::create([
                    'journal_entry_id' => $journal->id,
                    'account_id' => $assetAccount->id,
                    'debit' => 0,
                    'credit' => $takeHomePay,
                    'description' => "Pembayaran Bersih ({$validated['payment_method']})",
                ]);
            }

            // 3. KREDIT: Potongan Kasbon / Piutang Penjahit (Sebesar Total Bon)
            if ($totalBon > 0 && $advanceAccount) {
                JournalEntryLine::create([
                    'journal_entry_id' => $journal->id,
                    'account_id' => $advanceAccount->id,
                    'debit' => 0,
                    'credit' => $totalBon,
                    'description' => "Pemotongan Kasbon {$tailorPayroll->tailor_name}",
                ]);
            }

            $tailorPayroll->update(['journal_entry_id' => $journal->id]);

            return $tailorPayroll;
        });

        return redirect()->route('tailor-payrolls.show', $payroll)
            ->with('success', "Slip upah {$payroll->tailor_name} berhasil dibuat dan dibukukan ke jurnal {$payroll->journalEntry?->reference}.");
    }

    /**
     * Tampilkan lembar slip upah resmi penjahit (format cetak persis gambar Excel).
     */
    public function show(TailorPayroll $tailorPayroll)
    {
        $tailorPayroll->load(['items.material', 'advances', 'employee', 'journalEntry']);

        return view('tailor-payrolls.show', compact('tailorPayroll'));
    }

    /**
     * Hapus slip upah penjahit beserta jurnal akuntansinya.
     */
    public function destroy(TailorPayroll $tailorPayroll)
    {
        DB::transaction(function () use ($tailorPayroll) {
            if ($tailorPayroll->journal_entry_id) {
                $journal = $tailorPayroll->journalEntry;
                if ($journal) {
                    $journal->lines()->delete();
                    $journal->delete();
                }
            }

            $tailorPayroll->items()->delete();
            $tailorPayroll->advances()->delete();
            $tailorPayroll->delete();
        });

        return redirect()->route('tailor-payrolls.index')
            ->with('success', 'Slip upah penjahit berhasil dihapus.');
    }
}
