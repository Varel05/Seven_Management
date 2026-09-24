<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Administrator',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        $accounts = [
            ['code' => '1001', 'name' => 'Kas Operasional', 'type' => 'asset'],
            ['code' => '1002', 'name' => 'Bank BCA', 'type' => 'asset'],
            ['code' => '4001', 'name' => 'Pendapatan Usaha', 'type' => 'revenue'],
            ['code' => '5001', 'name' => 'Beban Operasional', 'type' => 'expense'],
            ['code' => '5002', 'name' => 'Beban Gaji', 'type' => 'expense'],
            ['code' => '5003', 'name' => 'Beban Perlengkapan Kantor', 'type' => 'expense'],
        ];

        foreach ($accounts as $acc) {
            Account::updateOrCreate(['code' => $acc['code']], $acc);
        }

        // Seed realistic sample transactions if database has fewer than 5 entries
        if (JournalEntry::count() < 5) {
            $kas = Account::where('code', '1001')->first();
            $bca = Account::where('code', '1002')->first();
            $pendapatan = Account::where('code', '4001')->first();
            $bebanOps = Account::where('code', '5001')->first();
            $bebanGaji = Account::where('code', '5002')->first();
            $bebanPerlengkapan = Account::where('code', '5003')->first();

            $currentYear = (int) now()->format('Y');

            $samples = [
                // Bulan-bulan sebelumnya di tahun berjalan
                ['ref' => 'TRX-202601-01', 'desc' => 'Pendapatan Kontrak Software Q1', 'date' => "$currentYear-01-15 10:00:00", 'dr_acc' => $bca->id, 'cr_acc' => $pendapatan->id, 'amount' => 12500000, 'src' => 'manual'],
                ['ref' => 'TRX-202601-02', 'desc' => 'Biaya Server Cloud AWS Jan', 'date' => "$currentYear-01-20 14:30:00", 'dr_acc' => $bebanOps->id, 'cr_acc' => $bca->id, 'amount' => 2400000, 'src' => 'manual'],
                ['ref' => 'TRX-202602-01', 'desc' => 'Pendapatan Maintenance Sistem', 'date' => "$currentYear-02-12 11:00:00", 'dr_acc' => $kas->id, 'cr_acc' => $pendapatan->id, 'amount' => 8000000, 'src' => 'telegram'],
                ['ref' => 'TRX-202602-02', 'desc' => 'Penggajian Tim Developer Feb', 'date' => "$currentYear-02-27 16:00:00", 'dr_acc' => $bebanGaji->id, 'cr_acc' => $bca->id, 'amount' => 5000000, 'src' => 'manual'],
                ['ref' => 'TRX-202603-01', 'desc' => 'Pendapatan Konsultasi IT Maret', 'date' => "$currentYear-03-10 09:15:00", 'dr_acc' => $bca->id, 'cr_acc' => $pendapatan->id, 'amount' => 15000000, 'src' => 'telegram'],
                ['ref' => 'TRX-202603-02', 'desc' => 'Pembelian Perlengkapan Kantor Q1', 'date' => "$currentYear-03-18 13:45:00", 'dr_acc' => $bebanPerlengkapan->id, 'cr_acc' => $kas->id, 'amount' => 1850000, 'src' => 'telegram'],
                ['ref' => 'TRX-202604-01', 'desc' => 'Pendapatan Langganan SaaS April', 'date' => "$currentYear-04-08 10:00:00", 'dr_acc' => $bca->id, 'cr_acc' => $pendapatan->id, 'amount' => 11000000, 'src' => 'manual'],
                ['ref' => 'TRX-202605-01', 'desc' => 'Pendapatan Lisensi Sistem Mei', 'date' => "$currentYear-05-14 11:20:00", 'dr_acc' => $bca->id, 'cr_acc' => $pendapatan->id, 'amount' => 13500000, 'src' => 'telegram'],
                ['ref' => 'TRX-202606-01', 'desc' => 'Pengeluaran Event & Workshop', 'date' => "$currentYear-06-22 15:00:00", 'dr_acc' => $bebanOps->id, 'cr_acc' => $kas->id, 'amount' => 3200000, 'src' => 'manual'],
                ['ref' => 'TRX-202607-01', 'desc' => 'Pendapatan Retainer Client Q3', 'date' => "$currentYear-07-05 10:30:00", 'dr_acc' => $bca->id, 'cr_acc' => $pendapatan->id, 'amount' => 16000000, 'src' => 'telegram'],
                ['ref' => 'TRX-202608-01', 'desc' => 'Pendapatan Pembuatan Web App', 'date' => "$currentYear-08-19 14:00:00", 'dr_acc' => $bca->id, 'cr_acc' => $pendapatan->id, 'amount' => 9500000, 'src' => 'telegram'],

                // Bulan berjalan (September) - Harian
                ['ref' => 'TRX-202609-01', 'desc' => 'Pendapatan Invoice Jasa Konsultasi', 'date' => "$currentYear-09-03 09:00:00", 'dr_acc' => $kas->id, 'cr_acc' => $pendapatan->id, 'amount' => 4500000, 'src' => 'telegram'],
                ['ref' => 'TRX-202609-02', 'desc' => 'Pembelian ATK & Kertas Kantor', 'date' => "$currentYear-09-06 11:30:00", 'dr_acc' => $bebanPerlengkapan->id, 'cr_acc' => $kas->id, 'amount' => 650000, 'src' => 'telegram'],
                ['ref' => 'TRX-202609-03', 'desc' => 'Pelunasan Modul Integrasi API', 'date' => "$currentYear-09-10 14:15:00", 'dr_acc' => $bca->id, 'cr_acc' => $pendapatan->id, 'amount' => 8500000, 'src' => 'manual'],
                ['ref' => 'TRX-202609-04', 'desc' => 'Biaya Internet & Server Dedicated', 'date' => "$currentYear-09-14 16:45:00", 'dr_acc' => $bebanOps->id, 'cr_acc' => $bca->id, 'amount' => 1750000, 'src' => 'telegram'],
                ['ref' => 'TRX-202609-05', 'desc' => 'Pendapatan Deployment AI Agent', 'date' => "$currentYear-09-18 10:20:00", 'dr_acc' => $bca->id, 'cr_acc' => $pendapatan->id, 'amount' => 12000000, 'src' => 'telegram'],
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
}
