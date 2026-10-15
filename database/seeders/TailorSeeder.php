<?php

namespace Database\Seeders;

use App\Models\Tailor;
use Illuminate\Database\Seeder;

class TailorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Tailor::firstOrCreate(
            ['name' => 'Penjahit Adriana'],
            [
                'phone' => '081234567801',
                'payment_period' => 'weekly',
                'payment_day' => 'Sabtu',
                'bank_name' => 'BCA',
                'bank_account_number' => '1234567890',
                'bank_account_holder' => 'Adriana',
                'status' => 'active',
                'notes' => 'Spesialis jas pria & rompi/vest.',
            ]
        );

        Tailor::firstOrCreate(
            ['name' => 'Penjahit Pak Joko'],
            [
                'phone' => '081234567802',
                'payment_period' => 'monthly',
                'payment_day' => '25',
                'bank_name' => 'Mandiri',
                'bank_account_number' => '9876543210',
                'bank_account_holder' => 'Joko Susilo',
                'status' => 'active',
                'notes' => 'Spesialis celana formal & safari.',
            ]
        );
    }
}
