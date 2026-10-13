<?php

use App\Models\Account;
use App\Models\JournalEntryLine;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations to simplify Chart of Accounts (COA) for the focused 4-pillar scope:
     * 1. Transaksi Keuangan & Kas/Bank (1001, 1002, 4001, 5001, 5003)
     * 2. Gaji Karyawan (5002)
     * 3. Upah Borongan Penjahit & Kasbon (5002, 1005)
     * 4. Pajak PPh Final UMKM 0,5% Badan Usaha (5005)
     */
    public function up(): void
    {
        // Jika database masih baru/kosong (seperti pada RefreshDatabase test suite), lewati migrasi data
        if (Account::count() === 0) {
            return;
        }

        DB::transaction(function () {
            // 1. Pastikan akun standar inti tersedia & terupdate
            $pendapatanUsaha = Account::firstOrCreate(
                ['code' => '4001'],
                ['name' => 'Pendapatan Usaha', 'type' => 'revenue']
            );

            $bebanOperasional = Account::firstOrCreate(
                ['code' => '5001'],
                ['name' => 'Beban Operasional', 'type' => 'expense']
            );

            // Perbarui nama akun 5002 & 5003 agar lebih mencakup operasional terfokus
            Account::where('code', '5002')->update(['name' => 'Beban Gaji & Upah']);
            Account::where('code', '5003')->update(['name' => 'Beban Perlengkapan & ATK']);

            // Tambahkan akun 5005 untuk Beban Pajak PPh Final UMKM
            Account::firstOrCreate(
                ['code' => '5005'],
                ['name' => 'Beban Pajak Penghasilan (PPh Final)', 'type' => 'expense']
            );

            // 2. Reklasifikasi baris jurnal lama yang masih menunjuk akun usang
            $obsoleteRevenueIds = Account::whereIn('code', ['4002', '4003'])->pluck('id');
            if ($obsoleteRevenueIds->isNotEmpty()) {
                JournalEntryLine::whereIn('account_id', $obsoleteRevenueIds)
                    ->update(['account_id' => $pendapatanUsaha->id]);
            }

            $obsoleteExpenseIds = Account::whereIn('code', ['1004', '5000'])->pluck('id');
            if ($obsoleteExpenseIds->isNotEmpty()) {
                JournalEntryLine::whereIn('account_id', $obsoleteExpenseIds)
                    ->update(['account_id' => $bebanOperasional->id]);
            }

            // Bersihkan baris jurnal retail HPP & stok lama (1003 & 5004) agar jurnal tetap seimbang
            $obsoleteRetailIds = Account::whereIn('code', ['1003', '5004'])->pluck('id');
            if ($obsoleteRetailIds->isNotEmpty()) {
                JournalEntryLine::whereIn('account_id', $obsoleteRetailIds)->delete();
            }

            // 3. Hapus akun-akun usang dari master Chart of Accounts
            Account::whereIn('code', ['1003', '1004', '4002', '4003', '5004', '5000'])->delete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Kembalikan akun jika di-rollback
        Account::firstOrCreate(['code' => '1003'], ['name' => 'Persediaan Barang Dagang (Retail)', 'type' => 'asset']);
        Account::firstOrCreate(['code' => '1004'], ['name' => 'Persediaan Bahan Baku & Pembantu', 'type' => 'asset']);
        Account::firstOrCreate(['code' => '4002'], ['name' => 'Pendapatan Penjualan Retail', 'type' => 'revenue']);
        Account::firstOrCreate(['code' => '4003'], ['name' => 'Pendapatan Jasa Pembuatan Jas Custom', 'type' => 'revenue']);
        Account::firstOrCreate(['code' => '5004'], ['name' => 'Beban Pokok Penjualan (HPP) Retail', 'type' => 'expense']);
    }
};
