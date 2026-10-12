<?php

namespace Database\Seeders;

use App\Enums\EmployeeRole;
use App\Models\Account;
use App\Models\Allowance;
use App\Models\Employee;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use Illuminate\Database\Seeder;

class DummyDataSeeder extends Seeder
{
    /**
     * Run the database seeds for testing and demonstration purposes.
     * Seeder ini HANYA untuk pengujian lokal / staging / demo dan TIDAK dijalankan di production.
     */
    public function run(): void
    {
        $kas = Account::where('code', '1001')->first() ?? Account::first();
        $bca = Account::where('code', '1002')->first() ?? $kas;
        $pendapatan = Account::where('code', '4001')->first() ?? $kas;
        $bebanOps = Account::where('code', '5001')->first() ?? $kas;
        $bebanGaji = Account::where('code', '5002')->first() ?? $kas;
        $bebanPerlengkapan = Account::where('code', '5003')->first() ?? $kas;

        // =========================================================================
        // 1. DATA DUMMY KARYAWAN & CS
        // =========================================================================
        $cs1 = Employee::updateOrCreate(
            ['phone' => '081234567891'],
            [
                'name' => 'Siti Rahma',
                'role' => EmployeeRole::Cs,
                'position' => 'Senior Customer Service',
                'base_salary' => 3200000,
                'current_points' => 320,
                'rate_per_point' => 1400,
                'claim_bonus' => true,
                'pay_day' => 25,
                'asset_account_id' => $kas->id,
                'status' => 'active',
            ]
        );

        $cs2 = Employee::updateOrCreate(
            ['phone' => '081234567892'],
            [
                'name' => 'Budi Santoso',
                'role' => EmployeeRole::Cs,
                'position' => 'Customer Service',
                'base_salary' => 3000000,
                'current_points' => 210,
                'rate_per_point' => 1000,
                'claim_bonus' => false,
                'pay_day' => 25,
                'asset_account_id' => $kas->id,
                'status' => 'active',
            ]
        );

        Employee::updateOrCreate(
            ['phone' => '081234567893'],
            [
                'name' => 'Agus Sudrajat',
                'role' => EmployeeRole::Staff,
                'position' => 'Master Tailor & Penjahit',
                'base_salary' => 3500000,
                'current_points' => 140,
                'rate_per_point' => 0,
                'claim_bonus' => true,
                'pay_day' => 25,
                'asset_account_id' => $bca->id,
                'status' => 'active',
            ]
        );

        Employee::updateOrCreate(
            ['phone' => '081234567894'],
            [
                'name' => 'Maya Anggraini',
                'role' => EmployeeRole::Akuntan,
                'position' => 'Finance & Accounting',
                'base_salary' => 4500000,
                'current_points' => 0,
                'rate_per_point' => 0,
                'claim_bonus' => true,
                'pay_day' => 25,
                'asset_account_id' => $bca->id,
                'status' => 'active',
            ]
        );

        // =========================================================================
        // 2. DATA DUMMY TUNJANGAN KARYAWAN
        // =========================================================================
        Allowance::updateOrCreate(
            ['name' => 'Tunjangan Uang Makan Harian'],
            [
                'amount' => 500000,
                'target_type' => 'all',
                'target_role' => null,
                'notes' => 'Subsidi makan siang seluruh staf dan karyawan aktif',
            ]
        );

        Allowance::updateOrCreate(
            ['name' => 'Tunjangan Transportasi CS'],
            [
                'amount' => 300000,
                'target_type' => 'role',
                'target_role' => 'cs',
                'notes' => 'Tunjangan mobilitas dan operasional customer service',
            ]
        );

        // =========================================================================
        // 3. DATA DUMMY HISTORI TRANSAKSI KEUANGAN (JURNAL UMUM & GRAFIK)
        // =========================================================================
        $currentYear = (int) now()->format('Y');

        $samples = [
            ['ref' => 'TRX-202601-01', 'desc' => 'Pendapatan Kontrak Jasa Q1', 'date' => "$currentYear-01-15 10:00:00", 'dr_acc' => $bca->id, 'cr_acc' => $pendapatan->id, 'amount' => 12500000, 'src' => 'manual'],
            ['ref' => 'TRX-202601-02', 'desc' => 'Biaya Operasional Toko Jan', 'date' => "$currentYear-01-20 14:30:00", 'dr_acc' => $bebanOps->id, 'cr_acc' => $bca->id, 'amount' => 2400000, 'src' => 'manual'],
            ['ref' => 'TRX-202602-01', 'desc' => 'Pendapatan Jasa Tailoring', 'date' => "$currentYear-02-12 11:00:00", 'dr_acc' => $kas->id, 'cr_acc' => $pendapatan->id, 'amount' => 8000000, 'src' => 'telegram'],
            ['ref' => 'TRX-202602-02', 'desc' => 'Penggajian Tim Karyawan Feb', 'date' => "$currentYear-02-27 16:00:00", 'dr_acc' => $bebanGaji->id, 'cr_acc' => $bca->id, 'amount' => 5000000, 'src' => 'manual'],
            ['ref' => 'TRX-202603-01', 'desc' => 'Pendapatan Jasa Maret', 'date' => "$currentYear-03-10 09:15:00", 'dr_acc' => $bca->id, 'cr_acc' => $pendapatan->id, 'amount' => 15000000, 'src' => 'telegram'],
            ['ref' => 'TRX-202603-02', 'desc' => 'Pembelian Perlengkapan Kantor Q1', 'date' => "$currentYear-03-18 13:45:00", 'dr_acc' => $bebanPerlengkapan->id, 'cr_acc' => $kas->id, 'amount' => 1850000, 'src' => 'telegram'],
            ['ref' => 'TRX-202604-01', 'desc' => 'Pendapatan Usaha April', 'date' => "$currentYear-04-08 10:00:00", 'dr_acc' => $bca->id, 'cr_acc' => $pendapatan->id, 'amount' => 11000000, 'src' => 'manual'],
            ['ref' => 'TRX-202605-01', 'desc' => 'Pendapatan Usaha Mei', 'date' => "$currentYear-05-14 11:20:00", 'dr_acc' => $bca->id, 'cr_acc' => $pendapatan->id, 'amount' => 13500000, 'src' => 'telegram'],
            ['ref' => 'TRX-202606-01', 'desc' => 'Pengeluaran Operasional Rutin', 'date' => "$currentYear-06-22 15:00:00", 'dr_acc' => $bebanOps->id, 'cr_acc' => $kas->id, 'amount' => 3200000, 'src' => 'manual'],
            ['ref' => 'TRX-202607-01', 'desc' => 'Pendapatan Kontrak Q3', 'date' => "$currentYear-07-05 10:30:00", 'dr_acc' => $bca->id, 'cr_acc' => $pendapatan->id, 'amount' => 16000000, 'src' => 'telegram'],
            ['ref' => 'TRX-202608-01', 'desc' => 'Pendapatan Jasa Tailoring Agustus', 'date' => "$currentYear-08-19 14:00:00", 'dr_acc' => $bca->id, 'cr_acc' => $pendapatan->id, 'amount' => 9500000, 'src' => 'telegram'],
            ['ref' => 'TRX-202609-01', 'desc' => 'Pendapatan Usaha September', 'date' => "$currentYear-09-03 09:00:00", 'dr_acc' => $kas->id, 'cr_acc' => $pendapatan->id, 'amount' => 4500000, 'src' => 'telegram'],
            ['ref' => 'TRX-202609-02', 'desc' => 'Pembelian ATK & Kertas Kantor', 'date' => "$currentYear-09-06 11:30:00", 'dr_acc' => $bebanPerlengkapan->id, 'cr_acc' => $kas->id, 'amount' => 650000, 'src' => 'telegram'],
            ['ref' => 'TRX-202609-03', 'desc' => 'Pelunasan Invoice Pelanggan', 'date' => "$currentYear-09-10 14:15:00", 'dr_acc' => $bca->id, 'cr_acc' => $pendapatan->id, 'amount' => 8500000, 'src' => 'manual'],
            ['ref' => 'TRX-202609-04', 'desc' => 'Biaya Internet & Listrik Toko', 'date' => "$currentYear-09-14 16:45:00", 'dr_acc' => $bebanOps->id, 'cr_acc' => $bca->id, 'amount' => 1750000, 'src' => 'telegram'],
            ['ref' => 'TRX-202609-05', 'desc' => 'Pendapatan Usaha Toko', 'date' => "$currentYear-09-18 10:20:00", 'dr_acc' => $bca->id, 'cr_acc' => $pendapatan->id, 'amount' => 12000000, 'src' => 'telegram'],
            ['ref' => 'TRX-202609-06', 'desc' => 'Gaji Pokok Staf Akuntansi & Admin', 'date' => "$currentYear-09-22 15:00:00", 'dr_acc' => $bebanGaji->id, 'cr_acc' => $bca->id, 'amount' => 4200000, 'src' => 'manual'],
        ];

        foreach ($samples as $s) {
            $entry = JournalEntry::updateOrCreate(
                ['reference' => $s['ref']],
                [
                    'description' => $s['desc'],
                    'date' => $s['date'],
                    'source' => $s['src'],
                    'status' => 'verified',
                ]
            );

            JournalEntryLine::updateOrCreate(
                ['journal_entry_id' => $entry->id, 'account_id' => $s['dr_acc']],
                [
                    'description' => $s['desc'],
                    'debit' => $s['amount'],
                    'credit' => 0,
                ]
            );

            JournalEntryLine::updateOrCreate(
                ['journal_entry_id' => $entry->id, 'account_id' => $s['cr_acc']],
                [
                    'description' => $s['desc'],
                    'debit' => 0,
                    'credit' => $s['amount'],
                ]
            );
        }
    }
}
