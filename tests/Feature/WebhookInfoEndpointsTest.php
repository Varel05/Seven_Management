<?php

namespace Tests\Feature;

use App\Enums\EmployeeRole;
use App\Models\Account;
use App\Models\Employee;
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
            'reference' => 'TRX-001',
            'date' => now()->toDateString(),
            'description' => 'Pendapatan tunai',
            'status' => 'verified',
        ]);
        JournalEntryLine::create(['journal_entry_id' => $entry1->id, 'account_id' => $kas->id, 'debit' => 50000, 'credit' => 0]);
        JournalEntryLine::create(['journal_entry_id' => $entry1->id, 'account_id' => $revenue->id, 'debit' => 0, 'credit' => 50000]);

        // Transaksi 2: BCA bertambah Rp 150.000
        $entry2 = JournalEntry::create([
            'reference' => 'TRX-002',
            'date' => now()->toDateString(),
            'description' => 'Pendapatan transfer BCA',
            'status' => 'verified',
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
            'reference' => 'TRX-REV',
            'date' => now()->toDateString(),
            'description' => 'Penjualan',
            'status' => 'verified',
        ]);
        JournalEntryLine::create(['journal_entry_id' => $entryRev->id, 'account_id' => $kas->id, 'debit' => 500000, 'credit' => 0]);
        JournalEntryLine::create(['journal_entry_id' => $entryRev->id, 'account_id' => $revenue->id, 'debit' => 0, 'credit' => 500000]);

        // Beban Rp 200.000
        $entryExp = JournalEntry::create([
            'reference' => 'TRX-EXP',
            'date' => now()->toDateString(),
            'description' => 'Biaya listrik',
            'status' => 'pending',
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

    public function test_identify_endpoint_rejects_unregistered_phone(): void
    {
        $response = $this->postJson('/api/webhook/auth/identify', [
            'sender_phone' => '089999999999',
            'sender_telegram_id' => '12345678',
        ], [
            'X-Webhook-Secret' => 'test_secret_key',
        ]);

        $response->assertStatus(404)
            ->assertJsonPath('status', false)
            ->assertJsonPath('authenticated', false)
            ->assertJsonPath('level', 'guest');
    }

    public function test_identify_endpoint_authenticates_cs_staff_and_binds_telegram_id(): void
    {
        $employee = Employee::create([
            'name' => 'Budi CS',
            'phone' => '081234567890',
            'role' => EmployeeRole::Cs,
            'position' => 'Customer Service',
            'base_salary' => 2500000,
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/webhook/auth/identify', [
            'sender_phone' => '081234567890',
            'sender_telegram_id' => '99887766',
            'sender_username' => 'budi_cs',
        ], [
            'X-Webhook-Secret' => 'test_secret_key',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonPath('authenticated', true)
            ->assertJsonPath('level', 'staff')
            ->assertJsonPath('role', 'cs')
            ->assertJsonPath('is_staff', true)
            ->assertJsonPath('is_above_staff', false)
            ->assertJsonPath('employee.id', $employee->id);

        $this->assertDatabaseHas('employees', [
            'id' => $employee->id,
            'telegram_user_id' => '99887766',
            'telegram_username' => 'budi_cs',
        ]);
    }

    public function test_identify_endpoint_authenticates_owner_from_config_or_role(): void
    {
        config(['services.telegram.owner_phones' => ['08111111111']]);

        $response = $this->postJson('/api/webhook/auth/identify', [
            'sender_phone' => '+628111111111',
            'sender_telegram_id' => '11223344',
        ], [
            'X-Webhook-Secret' => 'test_secret_key',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonPath('authenticated', true)
            ->assertJsonPath('level', 'above_staff')
            ->assertJsonPath('role', 'owner')
            ->assertJsonPath('is_above_staff', true);
    }

    public function test_balance_endpoint_denies_access_to_staff_role(): void
    {
        Employee::create([
            'name' => 'Siti Staff',
            'phone' => '08777777777',
            'role' => EmployeeRole::Staff,
            'position' => 'Staff Toko',
            'base_salary' => 2200000,
            'status' => 'active',
        ]);

        $response = $this->getJson('/api/webhook/balance?sender_phone=08777777777', [
            'X-Webhook-Secret' => 'test_secret_key',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('status', false)
            ->assertJsonPath('role', 'staff');
    }

    public function test_balance_endpoint_allows_access_to_above_staff_role(): void
    {
        Employee::create([
            'name' => 'Pak Manager',
            'phone' => '08888888888',
            'role' => EmployeeRole::Manager,
            'position' => 'Store Manager',
            'base_salary' => 5000000,
            'status' => 'active',
        ]);

        $response = $this->getJson('/api/webhook/balance?sender_phone=08888888888', [
            'X-Webhook-Secret' => 'test_secret_key',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', true);
    }
}
