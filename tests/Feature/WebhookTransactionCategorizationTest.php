<?php

namespace Tests\Feature;

use App\Models\Account;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebhookTransactionCategorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.webhook.secret' => 'test_secret_key']);

        // Siapkan akun sesuai database
        Account::create(['code' => '1001', 'name' => 'Kas Operasional', 'type' => 'asset']);
        Account::create(['code' => '1002', 'name' => 'Bank BCA', 'type' => 'asset']);
        Account::create(['code' => '4001', 'name' => 'Pendapatan Usaha', 'type' => 'revenue']);
        Account::create(['code' => '5001', 'name' => 'Beban Operasional', 'type' => 'expense']);
        Account::create(['code' => '5002', 'name' => 'Beban Gaji', 'type' => 'expense']);
        Account::create(['code' => '5003', 'name' => 'Beban Perlengkapan Kantor', 'type' => 'expense']);
    }

    public function test_uang_masuk_bca_didebit_ke_bank_bca(): void
    {
        $response = $this->postJson('/api/webhook/transaction', [
            'description' => 'uang masuk bca 50000',
            'amount' => 50000,
            'type' => 'income',
        ], [
            'X-Webhook-Secret' => 'test_secret_key',
        ]);

        $response->assertStatus(201);

        // Pastikan baris debit masuk ke Bank BCA (1002)
        $this->assertDatabaseHas('journal_entry_lines', [
            'account_id' => Account::where('code', '1002')->first()->id,
            'debit' => 50000,
            'credit' => 0,
        ]);

        // Pastikan baris kredit masuk ke Pendapatan Usaha (4001)
        $this->assertDatabaseHas('journal_entry_lines', [
            'account_id' => Account::where('code', '4001')->first()->id,
            'debit' => 0,
            'credit' => 50000,
        ]);
    }

    public function test_uang_masuk_tanpa_sebut_bank_default_ke_kas_operasional(): void
    {
        $response = $this->postJson('/api/webhook/transaction', [
            'description' => 'uang masuk penjualan 75000',
            'amount' => 75000,
            'type' => 'income',
        ], [
            'X-Webhook-Secret' => 'test_secret_key',
        ]);

        $response->assertStatus(201);

        // Default ke Kas Operasional (1001)
        $this->assertDatabaseHas('journal_entry_lines', [
            'account_id' => Account::where('code', '1001')->first()->id,
            'debit' => 75000,
            'credit' => 0,
        ]);
    }

    public function test_pengeluaran_perlengkapan_lewat_bca(): void
    {
        $response = $this->postJson('/api/webhook/transaction', [
            'description' => 'beli perlengkapan kantor via bca 30000',
            'amount' => 30000,
            'type' => 'expense',
        ], [
            'X-Webhook-Secret' => 'test_secret_key',
        ]);

        $response->assertStatus(201);

        // Beban Perlengkapan Kantor (5003) didebit
        $this->assertDatabaseHas('journal_entry_lines', [
            'account_id' => Account::where('code', '5003')->first()->id,
            'debit' => 30000,
            'credit' => 0,
        ]);

        // Bank BCA (1002) dikredit
        $this->assertDatabaseHas('journal_entry_lines', [
            'account_id' => Account::where('code', '1002')->first()->id,
            'debit' => 0,
            'credit' => 30000,
        ]);
    }

    public function test_pengeluaran_gaji_didebit_ke_beban_gaji(): void
    {
        $response = $this->postJson('/api/webhook/transaction', [
            'description' => 'bayar gaji staff 1200000',
            'amount' => 1200000,
            'type' => 'expense',
        ], [
            'X-Webhook-Secret' => 'test_secret_key',
        ]);

        $response->assertStatus(201);

        // Beban Gaji (5002) didebit
        $this->assertDatabaseHas('journal_entry_lines', [
            'account_id' => Account::where('code', '5002')->first()->id,
            'debit' => 1200000,
            'credit' => 0,
        ]);

        // Kas Operasional (1001) dikredit
        $this->assertDatabaseHas('journal_entry_lines', [
            'account_id' => Account::where('code', '1001')->first()->id,
            'debit' => 0,
            'credit' => 1200000,
        ]);
    }
}
