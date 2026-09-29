<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // =========================================================================
        // 1. SEEDER UTAMA (PRODUCTION / DEPLOYMENT)
        // Berisi seluruh data penting yang wajib ada saat aplikasi dideploy ke
        // super database: Akun Admin, Bagan Akun COA (Kas, Bank, Beban, HPP),
        // Master Bahan Baku & Formulasi HPP/BOM Tailor, serta Aturan Poin CS.
        // =========================================================================
        $this->call(ProductionSeeder::class);

        // =========================================================================
        // 2. SEEDER DUMMY (LOCAL DEVELOPMENT / TESTING ONLY)
        // Berisi sampel transaksi finansial, sampel produk retail, sampel karyawan,
        // sampel tunjangan, serta transaksi kasir & jas custom untuk pengujian visual.
        // Tidak akan dieksekusi saat deploy di production kecuali SEED_DUMMY_DATA=true
        // atau dijalankan spesifik: `php artisan db:seed --class=DummyDataSeeder`.
        // =========================================================================
        if (filter_var(env('SEED_DUMMY_DATA', false), FILTER_VALIDATE_BOOLEAN)) {
            $this->call(DummyDataSeeder::class);
        }
    }
}
