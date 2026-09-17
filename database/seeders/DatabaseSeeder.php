<?php

namespace Database\Seeders;

use App\Models\User;
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
        // User::factory(10)->create();

        User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Administrator',
                'password' => \Illuminate\Support\Facades\Hash::make('password'),
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
            \App\Models\Account::updateOrCreate(['code' => $acc['code']], $acc);
        }
    }
}
