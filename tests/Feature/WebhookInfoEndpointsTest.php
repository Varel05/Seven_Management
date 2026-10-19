<?php

namespace Tests\Feature;

use App\Enums\EmployeeRole;
use App\Models\Account;
use App\Models\Employee;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\Tailor;
use App\Models\TailorLaborRate;
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

    public function test_webhook_profit_loss_report(): void
    {
        config(['services.telegram.owner_phones' => ['08111111111']]);

        $kas = Account::where('code', '1001')->first();
        $revenue = Account::where('code', '4001')->first();
        $beban = Account::where('code', '5001')->first();

        $revEntry = JournalEntry::create([
            'reference' => 'REV-TEST-001',
            'date' => now()->toDateString(),
            'description' => 'Pendapatan Usaha',
            'status' => 'verified',
        ]);
        JournalEntryLine::create(['journal_entry_id' => $revEntry->id, 'account_id' => $kas->id, 'debit' => 1000000, 'credit' => 0]);
        JournalEntryLine::create(['journal_entry_id' => $revEntry->id, 'account_id' => $revenue->id, 'debit' => 0, 'credit' => 1000000]);

        $expEntry = JournalEntry::create([
            'reference' => 'EXP-TEST-001',
            'date' => now()->toDateString(),
            'description' => 'Beban Perlengkapan',
            'status' => 'verified',
        ]);
        JournalEntryLine::create(['journal_entry_id' => $expEntry->id, 'account_id' => $beban->id, 'debit' => 300000, 'credit' => 0]);
        JournalEntryLine::create(['journal_entry_id' => $expEntry->id, 'account_id' => $kas->id, 'debit' => 0, 'credit' => 300000]);

        $response = $this->getJson('/api/webhook/profit-loss', [
            'X-Webhook-Secret' => 'test_secret_key',
            'X-Telegram-Phone' => '08111111111',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('status', true);
        $this->assertEquals(1000000, $response->json('total_revenue'));
        $this->assertEquals(300000, $response->json('total_expense'));
        $this->assertEquals(700000, $response->json('net_profit'));
        $this->assertNotEmpty($response->json('excel_download_url'));

        $message = $response->json('message');
        $this->assertStringContainsString('LAPORAN LABA RUGI KOMPREHENSIF', $message);
        $this->assertStringContainsString('Total Pendapatan (A)', $message);
        $this->assertStringContainsString('Total Beban (B)', $message);
        $this->assertStringContainsString('SURPLUS BERSIH (LABA)', $message);
        $this->assertStringContainsString('Unduh Laporan Excel', $message);
    }

    public function test_webhook_pending_transactions_and_verify(): void
    {
        config(['services.telegram.owner_phones' => ['08111111111']]);

        $kas = Account::where('code', '1001')->first();
        $beban = Account::where('code', '5001')->first();

        $pendingEntry = JournalEntry::create([
            'reference' => 'PND-TEST-001',
            'date' => now()->toDateString(),
            'description' => 'Pengeluaran Menunggu Persetujuan',
            'status' => 'pending',
            'source' => 'telegram',
        ]);
        JournalEntryLine::create(['journal_entry_id' => $pendingEntry->id, 'account_id' => $beban->id, 'debit' => 250000, 'credit' => 0]);
        JournalEntryLine::create(['journal_entry_id' => $pendingEntry->id, 'account_id' => $kas->id, 'debit' => 0, 'credit' => 250000]);

        // 1. Cek list pending
        $responseList = $this->getJson('/api/webhook/transactions/pending', [
            'X-Webhook-Secret' => 'test_secret_key',
            'X-Telegram-Phone' => '08111111111',
        ]);
        $responseList->assertStatus(200);
        $responseList->assertJsonPath('status', true);
        $this->assertGreaterThanOrEqual(1, $responseList->json('count'));
        $this->assertStringContainsString('PND-TEST-001', $responseList->json('message'));

        // 2. Eksekusi Verifikasi
        $responseVerify = $this->postJson('/api/webhook/transaction/verify', [
            'reference' => 'PND-TEST-001',
            'action' => 'verify',
        ], [
            'X-Webhook-Secret' => 'test_secret_key',
            'X-Telegram-Phone' => '08111111111',
        ]);

        $responseVerify->assertStatus(200);
        $responseVerify->assertJsonPath('status', true);
        $responseVerify->assertJsonPath('action', 'verified');
        $this->assertStringContainsString('Berhasil Diverifikasi', $responseVerify->json('message'));

        $this->assertEquals('verified', $pendingEntry->fresh()->status);
    }

    public function test_webhook_tailors_list_and_register(): void
    {
        config(['services.telegram.owner_phones' => ['08111111111']]);

        // 1. Registrasi Penjahit Baru
        $responseReg = $this->postJson('/api/webhook/tailors/register', [
            'name' => 'Budi Taylor',
            'phone' => '081299998888',
            'payment_period' => 'weekly',
            'payment_day' => 'Jumat',
        ], [
            'X-Webhook-Secret' => 'test_secret_key',
            'X-Telegram-Phone' => '08111111111',
        ]);

        $responseReg->assertStatus(201);
        $responseReg->assertJsonPath('status', true);
        $this->assertStringContainsString('Budi Taylor', $responseReg->json('message'));

        // 2. Ambil Daftar Penjahit
        $responseList = $this->getJson('/api/webhook/tailors', [
            'X-Webhook-Secret' => 'test_secret_key',
            'X-Telegram-Phone' => '08111111111',
        ]);

        $responseList->assertStatus(200);
        $responseList->assertJsonPath('status', true);
        $this->assertGreaterThanOrEqual(1, $responseList->json('count'));
        $this->assertStringContainsString('Budi Taylor', $responseList->json('message'));
    }

    public function test_webhook_tailor_rates_endpoint_returns_active_rates(): void
    {
        config(['services.telegram.owner_phones' => ['08111111111']]);

        $response = $this->getJson('/api/webhook/tailor-rates', [
            'X-Webhook-Secret' => 'test_secret_key',
            'X-Telegram-Phone' => '08111111111',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('status', true);
        $response->assertJsonPath('action', 'rates');
        $this->assertGreaterThanOrEqual(1, $response->json('total_items'));
        $this->assertStringContainsString('Jas Reguler', $response->json('message'));
    }

    public function test_hermes_dynamically_detects_price_changes_and_new_products_from_web(): void
    {
        config(['services.telegram.owner_phones' => ['08111111111']]);

        // 1. Setup master penjahit dan pastikan tarif awal Jas Reguler Rp 50.000
        $tailor = Tailor::create([
            'name' => 'Penjahit Santoso',
            'payment_period' => 'weekly',
            'payment_day' => 'Jumat',
            'status' => 'active',
        ]);

        $regRate = TailorLaborRate::where('code', 'LAB-JHT-REG')->first();
        $regRate->update(['standard_cost' => 50000]);

        // Hermes request hitung upah: 10 pcs jas reguler
        $resp1 = $this->postJson('/api/webhook/tailor-payroll', [
            'tailor_name' => 'Santoso',
            'jas_reguler' => 10,
            'save' => false,
        ], [
            'X-Webhook-Secret' => 'test_secret_key',
            'X-Telegram-Phone' => '08111111111',
        ]);

        $resp1->assertOk();
        $this->assertEquals(500000, $resp1->json('total_wage'));
        $this->assertEquals('web_database', $resp1->json('rates_source'));

        // 2. Simulasi: Owner merubah harga di web dari Rp 50.000 menjadi Rp 75.000
        $regRate->update(['standard_cost' => 75000]);

        // Hermes request lagi untuk santoso dengan 10 pcs jas reguler
        $resp2 = $this->postJson('/api/webhook/tailor-payroll', [
            'tailor_name' => 'Santoso',
            'jas_reguler' => 10,
            'save' => false,
        ], [
            'X-Webhook-Secret' => 'test_secret_key',
            'X-Telegram-Phone' => '08111111111',
        ]);

        $resp2->assertOk();
        // Hermes LANGSUNG mendeteksi harga baru Rp 75.000 -> Total Upah = Rp 750.000
        $this->assertEquals(750000, $resp2->json('total_wage'));
        $this->assertStringContainsString('750.000', $resp2->json('message'));

        // 3. Simulasi: Owner menambahkan produk baru di web: Kemeja Batik Rp 40.000
        TailorLaborRate::create([
            'code' => 'LAB-JHT-BTK',
            'name' => 'Upah Penjahit Kemeja Batik',
            'unit' => 'pcs',
            'standard_cost' => 40000,
            'order' => 7,
            'is_active' => true,
        ]);

        // Hermes memproses jahit untuk Kemeja Batik (5 pcs)
        $resp3 = $this->postJson('/api/webhook/tailor-payroll', [
            'tailor_name' => 'Santoso',
            'items' => [
                ['name' => 'Kemeja Batik', 'quantity' => 5],
            ],
            'save' => false,
        ], [
            'X-Webhook-Secret' => 'test_secret_key',
            'X-Telegram-Phone' => '08111111111',
        ]);

        $resp3->assertOk();
        // 5 pcs x Rp 40.000 = Rp 200.000
        $this->assertEquals(200000, $resp3->json('total_wage'));
        $this->assertStringContainsString('Kemeja Batik', $resp3->json('message'));
        $this->assertStringContainsString('200.000', $resp3->json('message'));
    }

    public function test_accounts_endpoint_returns_eight_official_active_accounts_with_semantics(): void
    {
        $response = $this->getJson('/api/webhook/accounts', [
            'X-Webhook-Secret' => 'test_secret_key',
        ]);

        $response->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('total', 8);

        $accounts = collect($response->json('accounts'));
        $codes = $accounts->pluck('code')->sort()->values()->all();

        $expectedCodes = ['1001', '1002', '1005', '4001', '5001', '5002', '5003', '5005'];
        $this->assertEquals($expectedCodes, $codes);

        // Verifikasi metadata semantik untuk akun kasbon (1005)
        $kasbon = $accounts->firstWhere('code', '1005');
        $this->assertEquals('Piutang Kasbon Karyawan & Penjahit', $kasbon['name']);
        $this->assertEquals('asset', $kasbon['type']);
        $this->assertEquals('debit', $kasbon['normal_balance']);
        $this->assertContains('kasbon', $kasbon['keywords']);

        // Verifikasi metadata semantik untuk pajak (5005)
        $pajak = $accounts->firstWhere('code', '5005');
        $this->assertEquals('Beban Pajak Penghasilan (PPh Final)', $pajak['name']);
        $this->assertEquals('expense', $pajak['type']);
        $this->assertContains('pph final', $pajak['keywords']);

        // Verifikasi metadata semantik untuk ATK (5003)
        $atk = $accounts->firstWhere('code', '5003');
        $this->assertEquals('Beban Perlengkapan & ATK', $atk['name']);
        $this->assertContains('nota', $atk['keywords']);
    }

    public function test_accounts_endpoint_detects_appropriate_account_from_natural_text(): void
    {
        // 1. Deteksi transaksi perlengkapan & nota kasir -> 5003
        $resp1 = $this->getJson('/api/webhook/accounts?detect=beli+buku+nota+kasir+3+rangkap+dan+pulpen', [
            'X-Webhook-Secret' => 'test_secret_key',
        ]);
        $resp1->assertOk()
            ->assertJsonPath('detection_result.action_type', 'expense')
            ->assertJsonPath('detection_result.detected_target_account.code', '5003')
            ->assertJsonPath('detection_result.suggested_journal.debit.code', '5003');

        // 2. Deteksi transaksi pembayaran pajak PPh Final -> 5005
        $resp2 = $this->getJson('/api/webhook/accounts?detect=setor+pajak+pph+final+umkm+via+bca', [
            'X-Webhook-Secret' => 'test_secret_key',
        ]);
        $resp2->assertOk()
            ->assertJsonPath('detection_result.detected_target_account.code', '5005')
            ->assertJsonPath('detection_result.detected_asset_account.code', '1002')
            ->assertJsonPath('detection_result.suggested_journal.debit.code', '5005')
            ->assertJsonPath('detection_result.suggested_journal.credit.code', '1002');

        // 3. Deteksi kasbon penjahit -> 1005 (bukan akun beban!)
        $resp3 = $this->getJson('/api/webhook/accounts?detect=kasbon+penjahit+ahmad+200rb', [
            'X-Webhook-Secret' => 'test_secret_key',
        ]);
        $resp3->assertOk()
            ->assertJsonPath('detection_result.is_kasbon', true)
            ->assertJsonPath('detection_result.action_type', 'kasbon')
            ->assertJsonPath('detection_result.suggested_journal.debit.code', '1005')
            ->assertJsonPath('detection_result.suggested_journal.credit.code', '1001');
    }

    public function test_transaction_recording_correctly_debits_piutang_kasbon_for_cash_advance(): void
    {
        // Transaksi pengeluaran kasbon via webhook
        $resp = $this->postJson('/api/webhook/transaction', [
            'description' => 'Kasbon penjahit Rudi 150rb via tunai kas',
            'amount' => 150000,
            'type' => 'expense',
            'source' => 'telegram',
        ], [
            'X-Webhook-Secret' => 'test_secret_key',
            'X-Telegram-Phone' => '08111111111',
        ]);

        $resp->assertCreated();

        $journal = JournalEntry::with('lines.account')->latest('id')->first();
        $this->assertNotNull($journal);

        // Harus mendebit akun Piutang Kasbon 1005 (BUKAN beban 5001)
        $debitLine = $journal->lines->firstWhere('debit', '>', 0);
        $this->assertEquals('1005', $debitLine->account->code);
        $this->assertEquals('Piutang Kasbon Karyawan & Penjahit', $debitLine->account->name);
        $this->assertEquals(150000, (float) $debitLine->debit);

        // Dan mengkredit Kas Operasional 1001
        $creditLine = $journal->lines->firstWhere('credit', '>', 0);
        $this->assertEquals('1001', $creditLine->account->code);
        $this->assertEquals(150000, (float) $creditLine->credit);
    }

    public function test_transaction_recording_maps_legacy_codes_in_double_entry_mode(): void
    {
        // Simulasi request double-entry dengan kode lama dari prompt legacy (5008 -> 5005)
        $resp = $this->postJson('/api/webhook/transaction', [
            'description' => 'Pembayaran PPh Final bulan September via BCA',
            'lines' => [
                ['account_code' => '5008', 'debit' => 250000, 'credit' => 0],
                ['account_code' => '1002', 'debit' => 0, 'credit' => 250000],
            ],
            'source' => 'telegram',
        ], [
            'X-Webhook-Secret' => 'test_secret_key',
            'X-Telegram-Phone' => '08111111111',
        ]);

        $resp->assertCreated();

        $journal = JournalEntry::with('lines.account')->latest('id')->first();
        $debitLine = $journal->lines->firstWhere('debit', '>', 0);

        // Kode 5008 otomatis dipetakan ke 5005 yang sah
        $this->assertEquals('5005', $debitLine->account->code);
        $this->assertEquals('Beban Pajak Penghasilan (PPh Final)', $debitLine->account->name);
    }
}
