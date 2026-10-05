<?php

namespace Tests\Feature;

use App\Enums\EmployeeRole;
use App\Models\Account;
use App\Models\Employee;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\Material;
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

    public function test_my_points_endpoint_formats_status_tingkatan_with_tier_name_only_without_point_ranges(): void
    {
        $employee = Employee::create([
            'name' => 'Doni CS',
            'phone' => '081299998888',
            'role' => EmployeeRole::Cs,
            'position' => 'Customer Service',
            'base_salary' => 2500000,
            'current_points' => 250, // Tier 1
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/webhook/payroll/my-points', [
            'sender_phone' => '081299998888',
        ], [
            'X-Webhook-Secret' => 'test_secret_key',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonPath('tier_name', 'Tier 1');

        $message = $response->json('message');
        $this->assertStringContainsString('🏆 *Status Tingkatan*: *Tier 1*', $message);
        $this->assertStringNotContainsString('(200 - 294 Poin)', $message);
    }

    public function test_generate_payroll_slip_endpoint(): void
    {
        $employee = Employee::create([
            'name' => 'Maya Sari',
            'phone' => '081234567890',
            'role' => EmployeeRole::Cs,
            'position' => 'Customer Service',
            'daily_rate' => 100000,
            'base_salary' => 2700000,
            'discipline_rate' => 10000,
            'holiday_rate' => 50000,
            'current_points' => 300,
            'rate_per_point' => 1400,
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/webhook/payroll/slip', [
            'employee_query' => 'Maya',
            'total_shifts' => 27,
            'total_present' => 27,
            'late_count' => 0,
            'discipline_present' => 27,
            'holiday_shifts' => 1,
        ], [
            'X-Webhook-Secret' => 'test_secret_key',
            'X-Telegram-Phone' => '081234567890',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('status', true);
        $this->assertEquals('Maya Sari', $response->json('employee.name'));
        $this->assertEquals(3440000, $response->json('payroll.take_home_pay'));
        $message = $response->json('message');
        $this->assertStringContainsString('SLIP GAJI KARYAWAN', $message);
        $this->assertStringContainsString('Maya Sari', $message);
        $this->assertStringContainsString('Honor Utama', $message);
        $this->assertStringContainsString('Bonus Disiplin', $message);
        $this->assertStringContainsString('Bonus Penjualan', $message);
        $this->assertStringContainsString('Total Take Home Pay', $message);
    }

    public function test_webhook_tailor_payroll_rates(): void
    {
        config(['services.telegram.owner_phones' => ['08111111111']]);

        Material::firstOrCreate(
            ['code' => 'LAB-JHT-REG'],
            ['name' => 'Upah Penjahit Jas Reguler', 'category' => 'direct_labor', 'unit' => 'pcs', 'standard_cost' => 50000, 'stock' => 0, 'min_stock' => 0]
        );

        $response = $this->postJson('/api/webhook/tailor-payroll', [
            'action' => 'rates',
        ], [
            'X-Webhook-Secret' => 'test_secret_key',
            'X-Telegram-Phone' => '08111111111',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('status', true);
        $response->assertJsonPath('action', 'rates');
        $this->assertStringContainsString('Tarif Upah Penjahit di HPP', $response->json('message'));
        $this->assertStringContainsString('LAB-JHT-REG', $response->json('message'));
    }

    public function test_webhook_tailor_payroll_create_and_calculate(): void
    {
        config(['services.telegram.owner_phones' => ['08111111111']]);

        $response = $this->postJson('/api/webhook/tailor-payroll', [
            'tailor_name' => 'Adriana',
            'jas_reguler' => 10,
            'vest' => 5,
            'bon' => 150000,
            'bon_description' => 'Kasbon jajan',
        ], [
            'X-Webhook-Secret' => 'test_secret_key',
            'X-Telegram-Phone' => '08111111111',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('status', true);
        $this->assertEquals(15, $response->json('total_pieces'));
        $this->assertEquals(685000, $response->json('total_wage'));
        $this->assertEquals(150000, $response->json('total_bon'));
        $this->assertEquals(535000, $response->json('take_home_pay'));

        $message = $response->json('message');
        $this->assertStringContainsString('SLIP UPAH PENJAHIT (BORONGAN)', $message);
        $this->assertStringContainsString('Penjahit Adriana', $message);
        $this->assertStringContainsString('10 pcs x Rp 50.000', $message);
        $this->assertStringContainsString('5 pcs x Rp 37.000', $message);
        $this->assertStringContainsString('Jumlah Bon', $message);
        $this->assertStringContainsString('TAKE HOME PAY', $message);
        $this->assertStringContainsString('535.000', $message);
    }
}
