<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\User;
use App\Services\TaxService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaxCalculationTest extends TestCase
{
    use RefreshDatabase;

    protected TaxService $taxService;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.webhook.secret' => 'test_secret_key']);
        config(['services.telegram.owner_phones' => ['08123456789']]);

        $this->taxService = app(TaxService::class);

        Account::firstOrCreate(['code' => '1001'], ['name' => 'Kas Operasional', 'type' => 'asset']);
        Account::firstOrCreate(['code' => '1002'], ['name' => 'Bank BCA', 'type' => 'asset']);
        Account::firstOrCreate(['code' => '4001'], ['name' => 'Pendapatan Usaha', 'type' => 'revenue']);
        Account::firstOrCreate(['code' => '4002'], ['name' => 'Pendapatan Retail', 'type' => 'revenue']);
        Account::firstOrCreate(['code' => '5001'], ['name' => 'Beban Operasional', 'type' => 'expense']);
    }

    public function test_tax_service_calculates_badan_usaha_pph_final_without_exemption(): void
    {
        $year = 2026;
        $month = 10;
        $kas = Account::where('code', '1001')->first();
        $revenue = Account::where('code', '4001')->first();

        // Buat transaksi pendapatan Rp 10.000.000
        $journal = JournalEntry::create([
            'reference' => 'TRX-TAX-01',
            'date' => "{$year}-10-05",
            'description' => 'Pendapatan jasa sewa pakaian',
            'status' => 'verified',
        ]);
        JournalEntryLine::create(['journal_entry_id' => $journal->id, 'account_id' => $kas->id, 'debit' => 10000000, 'credit' => 0]);
        JournalEntryLine::create(['journal_entry_id' => $journal->id, 'account_id' => $revenue->id, 'debit' => 0, 'credit' => 10000000]);

        $taxData = $this->taxService->calculateMonthlyTax($year, $month);

        $this->assertEquals(10000000, $taxData['gross_revenue']);
        // Tarif 0.5% x 10.000.000 = 50.000 (tidak ada potongan 500jt karena Badan Usaha)
        $this->assertEquals(50000, $taxData['tax_amount']);
        $this->assertEquals('Badan Usaha (CV/PT)', $taxData['entity_type']);
        $this->assertEquals('411128', $taxData['kap']);
        $this->assertEquals('420', $taxData['kjs']);
        $this->assertFalse($taxData['is_paid']);
    }

    public function test_recording_tax_payment_creates_journal_and_marks_period_paid(): void
    {
        $year = 2026;
        $month = 10;
        $kas = Account::where('code', '1001')->first();
        $revenue = Account::where('code', '4001')->first();

        $journal = JournalEntry::create([
            'reference' => 'TRX-TAX-02',
            'date' => "{$year}-10-05",
            'description' => 'Pendapatan jasa',
            'status' => 'verified',
        ]);
        JournalEntryLine::create(['journal_entry_id' => $journal->id, 'account_id' => $kas->id, 'debit' => 6000000, 'credit' => 0]);
        JournalEntryLine::create(['journal_entry_id' => $journal->id, 'account_id' => $revenue->id, 'debit' => 0, 'credit' => 6000000]);

        // Pajak 0.5% x 6jt = 30.000
        $payJournal = $this->taxService->recordTaxPayment(
            year: $year,
            month: $month,
            amount: 30000,
            paymentAccountCode: '1002',
            ntpn: 'TESTNTPN12345678',
            source: 'test'
        );

        $this->assertNotNull($payJournal);
        $this->assertStringContainsString('TESTNTPN12345678', $payJournal->reference);

        // Cek jurnal debit 5005 & kredit 1002
        $taxExpenseLine = $payJournal->lines->firstWhere('account.code', '5005');
        $this->assertNotNull($taxExpenseLine);
        $this->assertEquals(30000, $taxExpenseLine->debit);

        $paymentLine = $payJournal->lines->firstWhere('account.code', '1002');
        $this->assertNotNull($paymentLine);
        $this->assertEquals(30000, $paymentLine->credit);

        // Setelah bayar, status bulan tersebut menjadi lunas
        $taxAfterPay = $this->taxService->calculateMonthlyTax($year, $month);
        $this->assertTrue($taxAfterPay['is_paid']);
        $this->assertNotNull($taxAfterPay['payment_info']);
    }

    public function test_webhook_tax_summary_endpoint(): void
    {
        $kas = Account::where('code', '1001')->first();
        $revenue = Account::where('code', '4001')->first();

        $journal = JournalEntry::create([
            'reference' => 'TRX-TAX-03',
            'date' => now()->toDateString(),
            'description' => 'Pendapatan retail',
            'status' => 'verified',
        ]);
        JournalEntryLine::create(['journal_entry_id' => $journal->id, 'account_id' => $kas->id, 'debit' => 4000000, 'credit' => 0]);
        JournalEntryLine::create(['journal_entry_id' => $journal->id, 'account_id' => $revenue->id, 'debit' => 0, 'credit' => 4000000]);

        $response = $this->postJson('/api/webhook/tax', [
            'sender_phone' => '08123456789',
            'year' => now()->year,
            'month' => now()->month,
        ], [
            'X-Webhook-Secret' => 'test_secret_key',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.gross_revenue', 4000000)
            ->assertJsonPath('data.tax_amount', 20000)
            ->assertJsonPath('data.entity_type', 'Badan Usaha (CV/PT)');
    }

    public function test_webhook_record_tax_payment_endpoint(): void
    {
        $kas = Account::where('code', '1001')->first();
        $revenue = Account::where('code', '4001')->first();

        $journal = JournalEntry::create([
            'reference' => 'TRX-TAX-04',
            'date' => now()->toDateString(),
            'description' => 'Pendapatan usaha',
            'status' => 'verified',
        ]);
        JournalEntryLine::create(['journal_entry_id' => $journal->id, 'account_id' => $kas->id, 'debit' => 2000000, 'credit' => 0]);
        JournalEntryLine::create(['journal_entry_id' => $journal->id, 'account_id' => $revenue->id, 'debit' => 0, 'credit' => 2000000]);

        $response = $this->postJson('/api/webhook/tax/pay', [
            'sender_phone' => '08123456789',
            'year' => now()->year,
            'month' => now()->month,
            'amount' => 10000,
            'payment_account' => '1001',
            'ntpn' => 'NTPN987654321',
        ], [
            'X-Webhook-Secret' => 'test_secret_key',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonPath('amount', 10000);

        $this->assertDatabaseHas('journal_entries', [
            'reference' => 'TAX-NTPN987654321',
        ]);
    }

    public function test_dashboard_displays_tax_widget_and_allows_payment_post(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard');
        $response->assertStatus(200);
        $response->assertSee('Ringkasan Pajak PPh Final UMKM');
        $response->assertSee('Badan Usaha (0,5% PP 55/2022)');
        $response->assertSee('Kode Billing DJP');

        // Test POST /tax/pay dari form dashboard
        $payResponse = $this->actingAs($user)->post('/tax/pay', [
            'year' => now()->year,
            'month' => now()->month,
            'amount' => 25000,
            'account_code' => '1001',
            'ntpn' => 'DASHBOARDNTPN123',
            'notes' => 'Pembayaran via kas dashboard',
        ]);

        $payResponse->assertRedirect('/dashboard');
        $payResponse->assertSessionHas('success');

        $this->assertDatabaseHas('journal_entries', [
            'reference' => 'TAX-DASHBOARDNTPN123',
        ]);
    }
}
