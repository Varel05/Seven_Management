<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\PointSetting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ProductionSeeder extends Seeder
{
    /**
     * Run the database seeds for essential production master data.
     * Seeder ini WAJIB dieksekusi saat project dideploy ke environment produksi.
     */
    public function run(): void
    {
        // 1. Akun Pengguna Administrator Utama
        User::updateOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@example.com')],
            [
                'name' => env('ADMIN_NAME', 'Administrator Seven Management'),
                'password' => Hash::make(env('ADMIN_PASSWORD', 'password')),
                'email_verified_at' => now(),
            ]
        );

        // 2. Bagan Akun Akuntansi Standar (Chart of Accounts / COA)
        $accounts = [
            ['code' => '1001', 'name' => 'Kas Operasional', 'type' => 'asset'],
            ['code' => '1002', 'name' => 'Bank BCA', 'type' => 'asset'],
            ['code' => '1003', 'name' => 'Persediaan Barang Dagang (Retail)', 'type' => 'asset'],
            ['code' => '1004', 'name' => 'Persediaan Bahan Baku & Pembantu', 'type' => 'asset'],
            ['code' => '4001', 'name' => 'Pendapatan Usaha', 'type' => 'revenue'],
            ['code' => '4002', 'name' => 'Pendapatan Penjualan Retail', 'type' => 'revenue'],
            ['code' => '4003', 'name' => 'Pendapatan Jasa Pembuatan Jas Custom', 'type' => 'revenue'],
            ['code' => '5001', 'name' => 'Beban Operasional', 'type' => 'expense'],
            ['code' => '5002', 'name' => 'Beban Gaji', 'type' => 'expense'],
            ['code' => '5003', 'name' => 'Beban Perlengkapan Kantor', 'type' => 'expense'],
            ['code' => '5004', 'name' => 'Beban Pokok Penjualan (HPP) Retail', 'type' => 'expense'],
        ];

        foreach ($accounts as $acc) {
            Account::updateOrCreate(['code' => $acc['code']], $acc);
        }

        // 3. Konfigurasi Standar Poin Insentif Pelayanan CS
        PointSetting::seedDefaultsIfEmpty();

        // 4. Master Data Bahan Baku, Aksesoris & Formulasi HPP / BOM Tailor
        $this->call(MaterialAndCostSheetSeeder::class);
    }
}
