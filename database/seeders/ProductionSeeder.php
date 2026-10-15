<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\PointSetting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ProductionSeeder extends Seeder
{
    /**
     * Run the database seeds for essential production master data.
     * Seeder ini WAJIB dieksekusi saat project dideploy ke environment produksi.
     */
    public function run(): void
    {
        DB::transaction(function () {
            // 1. Akun Pengguna Manajemen Utama (Owner & Akuntan)
            User::updateOrCreate(
                ['email' => env('ADMIN_EMAIL', 'admin@example.com')],
                [
                    'name' => env('ADMIN_NAME', 'Owner Seven Management'),
                    'role' => 'owner',
                    'password' => Hash::make(env('ADMIN_PASSWORD', 'password')),
                    'pin' => Hash::make(env('ADMIN_PIN', '123456')),
                    'email_verified_at' => now(),
                ]
            );

            User::updateOrCreate(
                ['email' => 'akuntan@seven.com'],
                [
                    'name' => 'Maya Anggraini (Akuntan)',
                    'role' => 'akuntan',
                    'password' => Hash::make('password'),
                    'pin' => Hash::make('123456'),
                    'email_verified_at' => now(),
                ]
            );

            // 2. Bagan Akun Akuntansi Standar (Chart of Accounts / COA)
            // Disederhanakan khusus untuk 4 pilar operasional inti Seven Management
            $accounts = [
                ['code' => '1001', 'name' => 'Kas Operasional', 'type' => 'asset'],
                ['code' => '1002', 'name' => 'Bank BCA', 'type' => 'asset'],
                ['code' => '1005', 'name' => 'Piutang Kasbon Karyawan & Penjahit', 'type' => 'asset'],
                ['code' => '4001', 'name' => 'Pendapatan Usaha', 'type' => 'revenue'],
                ['code' => '5001', 'name' => 'Beban Operasional', 'type' => 'expense'],
                ['code' => '5002', 'name' => 'Beban Gaji & Upah', 'type' => 'expense'],
                ['code' => '5003', 'name' => 'Beban Perlengkapan & ATK', 'type' => 'expense'],
                ['code' => '5005', 'name' => 'Beban Pajak Penghasilan (PPh Final)', 'type' => 'expense'],
            ];

            foreach ($accounts as $acc) {
                Account::updateOrCreate(['code' => $acc['code']], $acc);
            }

            // 3. Konfigurasi Standar Poin Insentif Pelayanan CS
            PointSetting::seedDefaultsIfEmpty();

            // 4. Data Master Penjahit Standar
            $this->call(TailorSeeder::class);
        });
    }
}
