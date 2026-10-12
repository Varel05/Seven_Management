<?php

namespace App\Services;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TaxService
{
    /**
     * Tarif PPh Final UMKM sesuai PP 55 Tahun 2022 (0,5% dari omzet bruto).
     */
    public const RATE_PPH_FINAL = 0.005;

    /**
     * Kode Akun Pajak (KAP) untuk PPh Final.
     */
    public const KAP = '411128';

    /**
     * Kode Jenis Setoran (KJS) untuk PPh Final UMKM PP 55/2022.
     */
    public const KJS = '420';

    /**
     * Kode Akun Buku Besar untuk Beban Pajak Penghasilan.
     */
    public const TAX_EXPENSE_CODE = '5005';

    /**
     * Entitas usaha Seven Management (Badan Usaha: CV/PT).
     */
    public const ENTITY_TYPE = 'Badan Usaha (CV/PT)';

    /**
     * Dapatkan atau buat akun buku besar Beban Pajak Penghasilan (5005).
     */
    public function getTaxExpenseAccount(): Account
    {
        return Account::firstOrCreate(
            ['code' => self::TAX_EXPENSE_CODE],
            [
                'name' => 'Beban Pajak Penghasilan (PPh Final)',
                'type' => 'expense',
            ]
        );
    }

    /**
     * Hitung peredaran bruto (omzet) dan estimasi PPh Final UMKM 0,5% untuk bulan tertentu.
     *
     * @return array<string, mixed>
     */
    public function calculateMonthlyTax(int $year, int $month): array
    {
        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = Carbon::create($year, $month, 1)->endOfMonth();
        $monthName = $startDate->translatedFormat('F Y');

        // Batas setor PPh Final adalah tanggal 15 bulan berikutnya
        $dueDate = (clone $startDate)->addMonth()->setDay(15);

        // Ambil semua transaksi pendapatan (revenue) aktif pada bulan & tahun tersebut
        $revenueLines = JournalEntryLine::active()
            ->whereHas('account', fn ($q) => $q->where('type', 'revenue'))
            ->whereHas('journalEntry', function ($q) use ($year, $month) {
                $q->whereYear('date', $year)->whereMonth('date', $month);
            })
            ->with('account')
            ->get();

        $grossRevenue = 0.0;
        $breakdown = [];

        foreach ($revenueLines as $line) {
            $code = $line->account->code ?? '4001';
            $name = $line->account->name ?? 'Pendapatan Usaha';
            $amount = (float) $line->credit;

            $grossRevenue += $amount;

            if (! isset($breakdown[$code])) {
                $breakdown[$code] = [
                    'code' => $code,
                    'name' => $name,
                    'amount' => 0.0,
                    'formatted_amount' => 'Rp 0',
                ];
            }

            $breakdown[$code]['amount'] += $amount;
            $breakdown[$code]['formatted_amount'] = 'Rp '.number_format($breakdown[$code]['amount'], 0, ',', '.');
        }

        // Urutkan rincian pendapatan berdasarkan kode akun
        ksort($breakdown);

        // Karena Seven Management berstatus Badan (CV/PT), PPh Final 0,5% dikenakan langsung atas seluruh omzet bruto
        $taxAmount = (float) round($grossRevenue * self::RATE_PPH_FINAL);

        // Periksa apakah pajak periode ini sudah dibayarkan
        $paidJournal = $this->findPaidTaxJournal($year, $month);
        $isPaid = $paidJournal !== null;

        return [
            'year' => $year,
            'month' => $month,
            'period' => $monthName,
            'entity_type' => self::ENTITY_TYPE,
            'tax_rate_percent' => 0.5,
            'tax_rate_decimal' => self::RATE_PPH_FINAL,
            'gross_revenue' => $grossRevenue,
            'formatted_gross_revenue' => 'Rp '.number_format($grossRevenue, 0, ',', '.'),
            'revenue_breakdown' => array_values($breakdown),
            'tax_amount' => $taxAmount,
            'formatted_tax_amount' => 'Rp '.number_format($taxAmount, 0, ',', '.'),
            'due_date' => $dueDate->toDateString(),
            'formatted_due_date' => $dueDate->translatedFormat('d F Y'),
            'kap' => self::KAP,
            'kjs' => self::KJS,
            'kap_kjs' => self::KAP.' - '.self::KJS,
            'is_paid' => $isPaid,
            'payment_info' => $isPaid ? [
                'journal_id' => $paidJournal->id,
                'reference' => $paidJournal->reference,
                'paid_date' => Carbon::parse($paidJournal->date)->translatedFormat('d F Y'),
                'amount' => (float) $paidJournal->lines->where('debit', '>', 0)->sum('debit'),
                'source' => $paidJournal->source,
            ] : null,
        ];
    }

    /**
     * Hitung rekapitulasi tahunan PPh Final UMKM per bulan untuk tahun tertentu.
     *
     * @return array<string, mixed>
     */
    public function calculateAnnualSummary(int $year): array
    {
        $months = [];
        $totalAnnualRevenue = 0.0;
        $totalAnnualTax = 0.0;
        $totalPaidTax = 0.0;

        for ($m = 1; $m <= 12; $m++) {
            $monthData = $this->calculateMonthlyTax($year, $m);
            $months[$m] = $monthData;

            $totalAnnualRevenue += $monthData['gross_revenue'];
            $totalAnnualTax += $monthData['tax_amount'];

            if ($monthData['is_paid']) {
                $totalPaidTax += $monthData['tax_amount'];
            }
        }

        return [
            'year' => $year,
            'entity_type' => self::ENTITY_TYPE,
            'total_annual_revenue' => $totalAnnualRevenue,
            'formatted_total_annual_revenue' => 'Rp '.number_format($totalAnnualRevenue, 0, ',', '.'),
            'total_annual_tax' => $totalAnnualTax,
            'formatted_total_annual_tax' => 'Rp '.number_format($totalAnnualTax, 0, ',', '.'),
            'total_paid_tax' => $totalPaidTax,
            'formatted_total_paid_tax' => 'Rp '.number_format($totalPaidTax, 0, ',', '.'),
            'months' => $months,
        ];
    }

    /**
     * Mencari entri jurnal pembayaran pajak yang sudah terverifikasi untuk bulan & tahun tertentu.
     */
    public function findPaidTaxJournal(int $year, int $month): ?JournalEntry
    {
        $padMonth = str_pad((string) $month, 2, '0', STR_PAD_LEFT);
        $monthName = Carbon::create($year, $month, 1)->translatedFormat('F Y');

        return JournalEntry::where('status', '!=', 'rejected')
            ->where(function ($q) use ($year, $padMonth, $monthName) {
                $q->where('reference', 'LIKE', "TAX-{$year}{$padMonth}%")
                    ->orWhere('description', 'LIKE', "%PPh Final%Masa {$monthName}%")
                    ->orWhere('description', 'LIKE', "%PPh Final%{$padMonth}/{$year}%")
                    ->orWhere('description', 'LIKE', "%Pajak UMKM%{$monthName}%");
            })
            ->whereHas('lines.account', function ($q) {
                $q->where('code', self::TAX_EXPENSE_CODE);
            })
            ->with(['lines.account'])
            ->latest('date')
            ->first();
    }

    /**
     * Catat pembayaran / penyetoran PPh Final UMKM ke buku besar akuntansi.
     * Debit: 5005 (Beban Pajak Penghasilan)
     * Credit: 1001 (Kas Operasional) atau 1002 (Bank BCA)
     */
    public function recordTaxPayment(
        int $year,
        int $month,
        ?float $amount = null,
        string $paymentAccountCode = '1001',
        ?string $ntpn = null,
        string $source = 'website',
        ?string $notes = null
    ): JournalEntry {
        return DB::transaction(function () use ($year, $month, $amount, $paymentAccountCode, $ntpn, $source, $notes) {
            $monthlyTax = $this->calculateMonthlyTax($year, $month);
            $finalAmount = ($amount !== null && $amount > 0) ? $amount : $monthlyTax['tax_amount'];

            if ($finalAmount <= 0) {
                throw new \InvalidArgumentException('Nominal pembayaran pajak harus lebih besar dari 0.');
            }

            $expenseAccount = $this->getTaxExpenseAccount();

            // Akun Kas/Bank pembayaran
            $cleanCode = strtolower(trim($paymentAccountCode));
            $targetCode = (str_contains($cleanCode, 'bank') || str_contains($cleanCode, 'bca') || $cleanCode === '1002') ? '1002' : '1001';

            $fundAccount = Account::where('code', $targetCode)->first()
                ?? Account::where('code', '1001')->first()
                ?? Account::firstOrCreate(
                    ['code' => '1001'],
                    ['name' => 'Kas Operasional', 'type' => 'asset']
                );

            $periodName = Carbon::create($year, $month, 1)->translatedFormat('F Y');
            $padMonth = str_pad((string) $month, 2, '0', STR_PAD_LEFT);

            $refNumber = ! empty($ntpn)
                ? 'TAX-'.strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $ntpn))
                : ('TAX-'.$year.$padMonth.'-'.strtoupper(Str::random(4)));

            $description = "Penyetoran PPh Final UMKM 0,5% Masa {$periodName}".($ntpn ? " (NTPN: {$ntpn})" : '');
            if ($notes) {
                $description .= " - {$notes}";
            }

            $journal = JournalEntry::create([
                'reference' => $refNumber,
                'description' => $description,
                'date' => now(),
                'source' => $source,
                'status' => 'verified',
            ]);

            // 1. Debit Beban Pajak Penghasilan (5005)
            JournalEntryLine::create([
                'journal_entry_id' => $journal->id,
                'account_id' => $expenseAccount->id,
                'description' => "Beban PPh Final UMKM 0,5% Masa {$periodName}",
                'debit' => $finalAmount,
                'credit' => 0,
            ]);

            // 2. Kredit Kas/Bank (1001 / 1002)
            JournalEntryLine::create([
                'journal_entry_id' => $journal->id,
                'account_id' => $fundAccount->id,
                'description' => "Pembayaran setoran PPh Final via {$fundAccount->name}",
                'debit' => 0,
                'credit' => $finalAmount,
            ]);

            return $journal->load('lines.account');
        });
    }

    /**
     * Format ringkasan pajak ke teks pesan Telegram yang rapi dan informatif.
     */
    public function formatTelegramTaxSummary(array $taxData): string
    {
        $statusEmoji = $taxData['is_paid'] ? '✅ *LUNAS*' : '⏳ *BELUM DISETOR*';
        $statusDesc = $taxData['is_paid']
            ? "Telah disetor pada {$taxData['payment_info']['paid_date']} (Ref: `{$taxData['payment_info']['reference']}`)"
            : "Batas waktu setor: *{$taxData['formatted_due_date']}*";

        $lines = [
            '🏛️ *Ringkasan Pajak PPh Final UMKM*',
            "🏢 *Status Entitas*: {$taxData['entity_type']}",
            "📅 *Masa Pajak*: *{$taxData['period']}*",
            '───────────────────',
            "💰 *Total Omzet Bruto*: *{$taxData['formatted_gross_revenue']}*",
        ];

        if (! empty($taxData['revenue_breakdown'])) {
            foreach ($taxData['revenue_breakdown'] as $item) {
                $lines[] = "   • {$item['name']}: {$item['formatted_amount']}";
            }
        }

        $lines[] = '───────────────────';
        $lines[] = '🏷️ *Tarif Pajak*: 0,5% (PP 55/2022)';
        $lines[] = "💵 *Estimasi PPh Terutang*: *{$taxData['formatted_tax_amount']}*";
        $lines[] = "📋 *Kode Billing e-Billing*: KAP `{$taxData['kap']}` / KJS `{$taxData['kjs']}`";
        $lines[] = "📌 *Status Penyetoran*: {$statusEmoji}";
        $lines[] = "   {$statusDesc}";

        if (! $taxData['is_paid'] && $taxData['tax_amount'] > 0) {
            $lines[] = '';
            $lines[] = '💡 *Bayar/Catat Setoran via Bot*:';
            $lines[] = "`/setorpajak {$taxData['month']} {$taxData['year']} [kas/bank] [NTPN]`";
            $lines[] = "Contoh: `/setorpajak {$taxData['month']} {$taxData['year']} bank NTPN12345678`";
        }

        return implode("\n", $lines);
    }
}
