<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Employee;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\User;
use App\Services\TransactionCancellationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionCancellationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Account::firstOrCreate(['code' => '1001'], ['name' => 'Kas Operasional', 'type' => 'asset']);
        Account::firstOrCreate(['code' => '1002'], ['name' => 'Bank BCA', 'type' => 'asset']);
        Account::firstOrCreate(['code' => '1003'], ['name' => 'Persediaan Barang Dagang (Retail)', 'type' => 'asset']);
        Account::firstOrCreate(['code' => '1004'], ['name' => 'Persediaan Bahan Baku & Pembantu', 'type' => 'asset']);
        Account::firstOrCreate(['code' => '4001'], ['name' => 'Pendapatan Jas & Custom', 'type' => 'revenue']);
        Account::firstOrCreate(['code' => '4002'], ['name' => 'Pendapatan Penjualan Retail', 'type' => 'revenue']);
        Account::firstOrCreate(['code' => '5001'], ['name' => 'Beban Operasional & Kantor', 'type' => 'expense']);
        Account::firstOrCreate(['code' => '5002'], ['name' => 'Beban Gaji', 'type' => 'expense']);
        Account::firstOrCreate(['code' => '5004'], ['name' => 'Beban Pokok Penjualan (HPP) Retail', 'type' => 'expense']);
    }

    public function test_can_cancel_expense_transaction_from_web_dashboard(): void
    {
        $user = User::factory()->create();
        $kas = Account::where('code', '1001')->first();
        $beban = Account::where('code', '5001')->first();

        // Saldo awal kas: setoran modal Rp 1.000.000
        $initialEntry = JournalEntry::create([
            'reference' => 'INIT-001',
            'description' => 'Saldo Awal',
            'date' => now(),
            'source' => 'manual',
            'status' => 'verified',
        ]);
        JournalEntryLine::create(['journal_entry_id' => $initialEntry->id, 'account_id' => $kas->id, 'debit' => 1000000, 'credit' => 0]);
        JournalEntryLine::create(['journal_entry_id' => $initialEntry->id, 'account_id' => Account::where('code', '4001')->first()->id, 'debit' => 0, 'credit' => 1000000]);

        // Catat pengeluaran salah input Rp 300.000
        $expenseEntry = JournalEntry::create([
            'reference' => 'EXP-001',
            'description' => 'Beli Alat Tulis Salah Input',
            'date' => now(),
            'source' => 'manual',
            'status' => 'verified',
        ]);
        JournalEntryLine::create(['journal_entry_id' => $expenseEntry->id, 'account_id' => $beban->id, 'debit' => 300000, 'credit' => 0]);
        JournalEntryLine::create(['journal_entry_id' => $expenseEntry->id, 'account_id' => $kas->id, 'debit' => 0, 'credit' => 300000]);

        // Cek saldo kas sebelum pembatalan: 1.000.000 - 300.000 = 700.000
        $this->assertEquals(700000, (float) (
            JournalEntryLine::active()->where('account_id', $kas->id)->sum('debit')
            - JournalEntryLine::active()->where('account_id', $kas->id)->sum('credit')
        ));

        // Eksekusi pembatalan transaksi via Web Route
        $response = $this->actingAs($user)
            ->post(route('journal-entries.cancel', $expenseEntry), [
                'reason' => 'Salah ketik nominal harusnya Rp 30.000',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('warning');

        $expenseEntry->refresh();
        $this->assertEquals('rejected', $expenseEntry->status);
        $this->assertStringContainsString('[BATAL: Salah ketik nominal harusnya Rp 30.000]', $expenseEntry->description);

        // Saldo kas setelah pembatalan harus kembali penuh ke Rp 1.000.000
        $this->assertEquals(1000000, (float) (
            JournalEntryLine::active()->where('account_id', $kas->id)->sum('debit')
            - JournalEntryLine::active()->where('account_id', $kas->id)->sum('credit')
        ));
    }

    public function test_cancel_employee_payroll_resets_last_paid_at_and_deletes_payroll_slip(): void
    {
        $employee = Employee::create([
            'name' => 'Rina Staff',
            'phone' => '081234567890',
            'position' => 'Staff Sales',
            'status' => 'active',
            'base_salary' => 2700000,
            'current_points' => 100,
            'rate_per_point' => 3000,
            'pay_day' => 25,
        ]);

        // Posting gaji
        $journalEntry = $employee->executePayrollPosting(2700000, 'website', [
            'period' => 'Oktober 2026',
        ]);

        $employee->refresh();
        $this->assertNotNull($employee->last_paid_at);
        $this->assertTrue($employee->isPaidThisMonth());
        $this->assertDatabaseHas('employee_payrolls', [
            'journal_entry_id' => $journalEntry->id,
            'employee_id' => $employee->id,
        ]);

        // Batalkan transaksi gaji
        $cancellationService = app(TransactionCancellationService::class);
        $result = $cancellationService->cancel($journalEntry, 'Koreksi absensi dan lembur', 'HRD');

        $this->assertTrue($result['success']);
        $employee->refresh();
        // last_paid_at harus direset sehingga karyawan dapat digaji kembali
        $this->assertNull($employee->last_paid_at);
        $this->assertFalse($employee->isPaidThisMonth());
        $this->assertDatabaseMissing('employee_payrolls', [
            'journal_entry_id' => $journalEntry->id,
        ]);
        $this->assertEquals('rejected', $journalEntry->fresh()->status);
    }

    public function test_webhook_api_can_cancel_transaction_by_reference_and_by_latest(): void
    {
        $webhookSecret = 'FpQqz4n9Czoh423Ok2tDs9RPGfAl4gfC';
        config(['services.webhook.secret' => $webhookSecret]);

        // 1. Rekam transaksi via webhook
        $createResponse = $this->withHeader('X-Webhook-Secret', $webhookSecret)
            ->postJson('/api/webhook/transaction', [
                'description' => 'Beli solar genset 50 liter',
                'amount' => 350000,
                'type' => 'expense',
                'account' => 'kas operasional',
            ]);

        $createResponse->assertCreated();
        $ref = $createResponse->json('data.reference');
        $this->assertNotEmpty($ref);

        // 2. Batalkan spesifik via reference
        $cancelResponse = $this->withHeader('X-Webhook-Secret', $webhookSecret)
            ->postJson('/api/webhook/transaction/cancel', [
                'reference' => $ref,
                'reason' => 'Salah ketik harusnya 5 liter',
            ]);

        $cancelResponse->assertOk();
        $this->assertTrue($cancelResponse->json('status'));
        $this->assertEquals('rejected', $cancelResponse->json('data.status'));
        $this->assertStringContainsString('Transaksi Berhasil Dibatalkan', $cancelResponse->json('message'));

        // 3. Rekam transaksi kedua untuk uji pembatalan transaksi terakhir otomatis
        $createResponse2 = $this->withHeader('X-Webhook-Secret', $webhookSecret)
            ->postJson('/api/webhook/transaction', [
                'description' => 'Pembelian snack rapat',
                'amount' => 75000,
                'type' => 'expense',
                'account' => 'kas operasional',
            ]);
        $createResponse2->assertCreated();
        $ref2 = $createResponse2->json('data.reference');

        // Batalkan tanpa reference (otomatis membatalkan transaksi aktif terakhir)
        $cancelLatestResponse = $this->withHeader('X-Webhook-Secret', $webhookSecret)
            ->postJson('/api/webhook/transaction/cancel', [
                'reason' => 'Salah input transaksi barusan',
            ]);

        $cancelLatestResponse->assertOk();
        $this->assertTrue($cancelLatestResponse->json('status'));
        $this->assertEquals($ref2, $cancelLatestResponse->json('data.reference'));
        $this->assertEquals('rejected', $cancelLatestResponse->json('data.status'));
    }
}
