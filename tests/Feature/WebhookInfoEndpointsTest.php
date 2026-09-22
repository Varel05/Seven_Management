<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebhookInfoEndpointsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.webhook.secret' => 'test_secret_key']);

        Account::create(['code' => '1001', 'name' => 'Kas Operasional', 'type' => 'asset']);
        Account::create(['code' => '1002', 'name' => 'Bank BCA', 'type' => 'asset']);
        Account::create(['code' => '4001', 'name' => 'Pendapatan Usaha', 'type' => 'revenue']);
        Account::create(['code' => '5001', 'name' => 'Beban Operasional', 'type' => 'expense']);
    }

    public function test_balance_endpoint_requires_webhook_secret(): void
    {
        $response = $this->getJson('/api/webhook/balance');
        $response->assertStatus(401);
    }

    public function test_balance_endpoint_returns_accurate_asset_balances(): void
    {
        $kas = Account::where('code', '1001')->first();
        $bca = Account::where('code', '1002')->first();
        $revenue = Account::where('code', '4001')->first();

        // Transaksi 1: Kas bertambah Rp 50.000
        $entry1 = JournalEntry::create([
            'reference'   => 'TRX-001',
            'date'        => now()->toDateString(),
            'description' => 'Pendapatan tunai',
            'status'      => 'verified',
        ]);
        JournalEntryLine::create(['journal_entry_id' => $entry1->id, 'account_id' => $kas->id, 'debit' => 50000, 'credit' => 0]);
        JournalEntryLine::create(['journal_entry_id' => $entry1->id, 'account_id' => $revenue->id, 'debit' => 0, 'credit' => 50000]);

        // Transaksi 2: BCA bertambah Rp 150.000
        $entry2 = JournalEntry::create([
            'reference'   => 'TRX-002',
            'date'        => now()->toDateString(),
            'description' => 'Pendapatan transfer BCA',
            'status'      => 'verified',
        ]);
        JournalEntryLine::create(['journal_entry_id' => $entry2->id, 'account_id' => $bca->id, 'debit' => 150000, 'credit' => 0]);
        JournalEntryLine::create(['journal_entry_id' => $entry2->id, 'account_id' => $revenue->id, 'debit' => 0, 'credit' => 150000]);

        $response = $this->getJson('/api/webhook/balance', [
            'X-Webhook-Secret' => 'test_secret_key',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonPath('total', 200000)
            ->assertJsonPath('formatted_total', 'Rp 200.000');

        $this->assertStringContainsString('Bank BCA', $response->json('message'));
        $this->assertStringContainsString('Rp 150.000', $response->json('message'));
        $this->assertStringContainsString('Kas Operasional', $response->json('message'));
        $this->assertStringContainsString('Rp 50.000', $response->json('message'));
    }

    public function test_summary_endpoint_returns_monthly_profit_and_loss(): void
    {
        $kas = Account::where('code', '1001')->first();
        $revenue = Account::where('code', '4001')->first();
        $expense = Account::where('code', '5001')->first();

        // Pemasukan Rp 500.000
        $entryRev = JournalEntry::create([
            'reference'   => 'TRX-REV',
            'date'        => now()->toDateString(),
            'description' => 'Penjualan',
            'status'      => 'verified',
        ]);
        JournalEntryLine::create(['journal_entry_id' => $entryRev->id, 'account_id' => $kas->id, 'debit' => 500000, 'credit' => 0]);
        JournalEntryLine::create(['journal_entry_id' => $entryRev->id, 'account_id' => $revenue->id, 'debit' => 0, 'credit' => 500000]);

        // Beban Rp 200.000
        $entryExp = JournalEntry::create([
            'reference'   => 'TRX-EXP',
            'date'        => now()->toDateString(),
            'description' => 'Biaya listrik',
            'status'      => 'pending',
        ]);
        JournalEntryLine::create(['journal_entry_id' => $entryExp->id, 'account_id' => $expense->id, 'debit' => 200000, 'credit' => 0]);
        JournalEntryLine::create(['journal_entry_id' => $entryExp->id, 'account_id' => $kas->id, 'debit' => 0, 'credit' => 200000]);

        $response = $this->getJson('/api/webhook/summary', [
            'X-Webhook-Secret' => 'test_secret_key',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonPath('revenue', 500000)
            ->assertJsonPath('expense', 200000)
            ->assertJsonPath('net_profit', 300000)
            ->assertJsonPath('pending_count', 1)
            ->assertJsonPath('verified_count', 1);

        $this->assertStringContainsString('Pemasukan', $response->json('message'));
        $this->assertStringContainsString('Rp 500.000', $response->json('message'));
        $this->assertStringContainsString('Pengeluaran', $response->json('message'));
        $this->assertStringContainsString('Rp 200.000', $response->json('message'));
        $this->assertStringContainsString('Laba Bersih', $response->json('message'));
        $this->assertStringContainsString('Rp 300.000', $response->json('message'));
    }
}
