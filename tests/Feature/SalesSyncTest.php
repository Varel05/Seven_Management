<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Employee;
use App\Models\EmployeePointLog;
use App\Models\JournalEntry;
use App\Services\SalesSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SalesSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Account::firstOrCreate(['code' => '1001'], ['name' => 'Kas Operasional', 'type' => 'asset']);
        Account::firstOrCreate(['code' => '1002'], ['name' => 'Bank BCA', 'type' => 'asset']);
        Account::firstOrCreate(['code' => '4001'], ['name' => 'Pendapatan Usaha', 'type' => 'revenue']);
    }

    public function test_can_record_sales_transaction_to_account_4001_and_bca(): void
    {
        $service = app(SalesSyncService::class);

        $result = $service->recordTransaction([
            'reference' => 'ORD-CS1792143229',
            'amount' => 120000,
            'type' => 'penjualan',
            'store' => 'DistroKorea.com',
            'product' => '2ALPHASK57',
            'payment_method' => 'bca',
            'date' => '2026-10-16',
        ]);

        $this->assertTrue($result['success']);
        $this->assertEquals('created', $result['status']);

        $entry = JournalEntry::where('reference', 'ORD-CS1792143229')->first();
        $this->assertNotNull($entry);
        $this->assertEquals('web_penjualan', $entry->source);
        $this->assertEquals('verified', $entry->status);
        $this->assertStringContainsString('Penjualan', $entry->description);
        $this->assertStringContainsString('DistroKorea.com', $entry->description);

        $lines = $entry->lines;
        $this->assertCount(2, $lines);

        $debitLine = $lines->firstWhere('debit', '>', 0);
        $creditLine = $lines->firstWhere('credit', '>', 0);

        $this->assertEquals(120000, $debitLine->debit);
        $this->assertEquals('1002', $debitLine->account->code); // Bank BCA

        $this->assertEquals(120000, $creditLine->credit);
        $this->assertEquals('4001', $creditLine->account->code); // Pendapatan Usaha
    }

    public function test_can_record_rental_transaction_to_account_4001(): void
    {
        $service = app(SalesSyncService::class);

        $result = $service->recordTransaction([
            'reference' => 'SEWA-BLAZER-01',
            'amount' => 499000,
            'type' => 'sewa',
            'store' => 'Blazer Jaket',
            'product' => 'Blazer Keren Dingin',
            'payment_method' => 'kas',
            'date' => '2026-10-16',
        ]);

        $this->assertTrue($result['success']);
        $this->assertEquals('created', $result['status']);

        $entry = JournalEntry::where('reference', 'SEWA-BLAZER-01')->first();
        $this->assertNotNull($entry);
        $this->assertStringContainsString('Sewa', $entry->description);

        $debitLine = $entry->lines->firstWhere('debit', '>', 0);
        $creditLine = $entry->lines->firstWhere('credit', '>', 0);

        $this->assertEquals('1001', $debitLine->account->code); // Kas Operasional (karena cash/kas)
        $this->assertEquals('4001', $creditLine->account->code); // Pendapatan Usaha
    }

    public function test_record_transaction_is_idempotent_and_skips_duplicates(): void
    {
        $service = app(SalesSyncService::class);

        $payload = [
            'reference' => 'ORD-DUPLICATE-CHECK',
            'amount' => 200000,
            'type' => 'penjualan',
            'store' => 'Seven Domu',
            'product' => 'Jas Formal Hitam',
        ];

        $first = $service->recordTransaction($payload);
        $this->assertEquals('created', $first['status']);

        $second = $service->recordTransaction($payload);
        $this->assertEquals('skipped', $second['status']);

        $this->assertEquals(1, JournalEntry::where('reference', 'ORD-DUPLICATE-CHECK')->count());
    }

    public function test_sync_from_api_pulls_points_and_sewa(): void
    {
        Http::fake([
            '*/api/publik/poin*' => Http::response([
                'ringkas' => ['poin' => 1000, 'nota' => 1],
                'per_barang' => [
                    ['kode' => '2ALPHASK57', 'toko' => 21, 'poin' => 1000, 'unit' => 1, 'nota' => 1],
                ],
                'per_nota' => [
                    ['seri' => 'CS1792143229', 'tanggal' => '2026-10-16', 'poin' => 1000],
                ],
            ], 200),
            '*/api/publik/laporan/pendapatan*' => Http::response([
                'ringkas' => ['masuk' => 120000, 'lunas' => 1, 'terkirim' => 1],
            ], 200),
            '*/api/publik/katalog*kolam=sewa*' => Http::response([
                'barang' => [
                    [
                        'kode' => 'Blazer Keren Dingin',
                        'toko' => ['id' => 3, 'nama' => 'Blazer Jaket'],
                        'sewa' => ['keluar' => 1, 'harga_mulai' => 499000, 'harga_akhir' => 499000],
                    ],
                ],
            ], 200),
        ]);

        $service = app(SalesSyncService::class);
        $syncResult = $service->syncTransactionsFromApi('2026-10-01', '2026-10-19');

        $this->assertTrue($syncResult['success']);
        $this->assertEquals(2, $syncResult['synced']);
        $this->assertEquals(619000, $syncResult['total_amount']);

        $this->assertTrue(JournalEntry::where('reference', 'ORD-CS1792143229')->exists());
        $this->assertTrue(JournalEntry::where('reference', 'like', 'SEWA-%')->exists());
    }

    public function test_webhook_sales_order_endpoint_records_transaction_with_bearer_token(): void
    {
        $salesToken = config('services.sales_app.token');

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$salesToken}",
        ])->postJson('/api/webhook/sales-order', [
            'seri' => 'WEBHOOK-999',
            'amount' => 350000,
            'type' => 'penjualan',
            'toko' => 'Suit Men tailor',
            'produk' => 'Custom Blazer',
            'metode_bayar' => 'bca',
            'tanggal' => '2026-10-19',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'status' => 'created',
            ]);

        $entry = JournalEntry::where('reference', 'WEBHOOK-999')->first();
        $this->assertNotNull($entry);
        $this->assertEquals(350000, $entry->lines->firstWhere('credit', '>', 0)->credit);
    }

    public function test_artisan_command_syncs_sales_transactions(): void
    {
        Http::fake([
            '*/api/publik/poin*' => Http::response([
                'ringkas' => ['poin' => 1000, 'nota' => 1],
                'per_barang' => [
                    ['kode' => '2ALPHASK57', 'toko' => 21, 'poin' => 1000, 'unit' => 1, 'nota' => 1],
                ],
                'per_nota' => [
                    ['seri' => 'CS1792143229', 'tanggal' => '2026-10-16', 'poin' => 1000],
                ],
            ], 200),
            '*/api/publik/laporan/pendapatan*' => Http::response([
                'ringkas' => ['masuk' => 120000, 'lunas' => 1, 'terkirim' => 1],
            ], 200),
            '*/api/publik/katalog*kolam=sewa*' => Http::response([
                'barang' => [],
            ], 200),
        ]);

        $exitCode = Artisan::call('sales:sync-transactions', [
            '--start' => '2026-10-01',
            '--end' => '2026-10-19',
        ]);

        $this->assertEquals(0, $exitCode);
        $this->assertTrue(JournalEntry::where('reference', 'ORD-CS1792143229')->exists());
    }

    public function test_sync_points_from_api_updates_employee_points_tier_and_bonus(): void
    {
        // Buat karyawan CS
        $employee = Employee::create([
            'name' => 'Sakina Rahma',
            'nip' => 'CS/01-10-2026',
            'role' => 'cs',
            'position' => 'Customer Service',
            'current_points' => 0,
            'claim_bonus' => true,
            'status' => 'active',
        ]);

        Http::fake([
            '*/api/publik/poin*' => Http::response([
                'ringkas' => ['poin' => 500, 'nota' => 1],
                'per_barang' => [
                    ['kode' => 'CELANA BAHAN SENDIR', 'toko' => 21, 'poin' => 500, 'unit' => 1, 'nota' => 1],
                ],
                'per_nota' => [
                    ['seri' => 'CS1792143229', 'tanggal' => '2026-10-16', 'poin' => 500],
                ],
            ], 200),
        ]);

        $service = app(SalesSyncService::class);
        $result = $service->syncPointsAndBonusFromApi('2026-10-01', '2026-10-20');

        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['synced']);
        $this->assertEquals(500, $result['total_points']);

        $employee->refresh();
        $this->assertEquals(500, $employee->current_points);
        // >= 500 poin adalah Tier 5 dengan tarif Rp 2.600 / pt
        $this->assertEquals('Tier 5', $employee->tier_name);
        $this->assertEquals(2600.0, (float) $employee->rate_per_point);
        // Bonus salary = 500 * 2600 = 1.300.000
        $this->assertEquals(1300000.0, (float) $employee->bonus_salary);

        // Cek log tercatat
        $this->assertTrue(EmployeePointLog::where('employee_id', $employee->id)->where('notes', 'like', '%CS1792143229%')->exists());
    }

    public function test_sync_points_is_idempotent_and_does_not_duplicate_points(): void
    {
        $employee = Employee::create([
            'name' => 'Sakina Rahma',
            'nip' => 'CS/01-10-2026',
            'role' => 'cs',
            'position' => 'Customer Service',
            'current_points' => 100,
            'claim_bonus' => true,
            'status' => 'active',
        ]);

        Http::fake([
            '*/api/publik/poin*' => Http::response([
                'per_nota' => [
                    ['seri' => 'CS99999999', 'tanggal' => '2026-10-16', 'poin' => 150],
                ],
                'per_barang' => [],
            ], 200),
        ]);

        $service = app(SalesSyncService::class);

        // Eksekusi pertama
        $res1 = $service->syncPointsAndBonusFromApi('2026-10-01', '2026-10-20');
        $this->assertEquals(1, $res1['synced']);
        $employee->refresh();
        $this->assertEquals(250, $employee->current_points); // 100 + 150

        // Eksekusi kedua (harus skipped, poin tidak bertambah lagi)
        $res2 = $service->syncPointsAndBonusFromApi('2026-10-01', '2026-10-20');
        $this->assertEquals(0, $res2['synced']);
        $this->assertEquals(1, $res2['skipped']);

        $employee->refresh();
        $this->assertEquals(250, $employee->current_points);
    }

    public function test_webhook_sales_order_with_points_awards_bonus_to_cs(): void
    {
        $salesToken = config('services.sales_app.token');

        $employee = Employee::create([
            'name' => 'Budi CS',
            'nip' => 'CS/02-10-2026',
            'role' => 'cs',
            'position' => 'Customer Service',
            'current_points' => 0,
            'claim_bonus' => true,
            'status' => 'active',
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$salesToken}",
        ])->postJson('/api/webhook/sales-order', [
            'seri' => 'CS77889900',
            'amount' => 500000,
            'poin' => 300,
            'employee_id' => $employee->id,
            'type' => 'penjualan',
            'toko' => 'Blazer Jaket',
            'produk' => 'Jas Premium',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'status' => 'created',
            ]);

        $employee->refresh();
        $this->assertEquals(300, $employee->current_points);
        // 300 poin adalah Tier 2 (295 - 369 poin) dengan tarif Rp 1.400 / pt
        $this->assertEquals('Tier 2', $employee->tier_name);
        $this->assertEquals(1400.0, (float) $employee->rate_per_point);
        // Bonus salary = 300 * 1400 = 420.000
        $this->assertEquals(420000.0, (float) $employee->bonus_salary);
    }

    public function test_webhook_receive_sales_points_batch_endpoint(): void
    {
        $salesToken = config('services.sales_app.token');

        $empDayat = Employee::create([
            'name' => 'Dayat Hidayat',
            'nip' => 'DA/01-10-2026',
            'role' => 'cs',
            'position' => 'CS Online',
            'current_points' => 50,
            'claim_bonus' => true,
            'status' => 'active',
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$salesToken}",
        ])->postJson('/api/webhook/sales/points', [
            'per_nota' => [
                ['seri' => 'DA1792485470', 'tanggal' => '2026-10-20', 'poin' => 200],
            ],
            'per_barang' => [
                ['kode' => 'Blazer Keren Dingin', 'toko' => 32, 'poin' => 200, 'nota' => 1],
            ],
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'synced' => 1,
                'total_points' => 200,
            ]);

        $empDayat->refresh();
        $this->assertEquals(250, $empDayat->current_points); // 50 + 200 = 250 pt -> Tier 1 (Rp 1.000 / pt)
        $this->assertEquals('Tier 1', $empDayat->tier_name);
        $this->assertEquals(250000.0, (float) $empDayat->bonus_salary);
    }

    public function test_artisan_command_syncs_sales_points(): void
    {
        $emp = Employee::create([
            'name' => 'Amira Staf',
            'nip' => 'AM/01-10-2026',
            'role' => 'cs',
            'position' => 'Customer Service',
            'current_points' => 0,
            'claim_bonus' => true,
            'status' => 'active',
        ]);

        Http::fake([
            '*/api/publik/poin*' => Http::response([
                'ringkas' => ['poin' => 400, 'nota' => 1],
                'per_barang' => [
                    ['kode' => 'CELANA BAHAN SENDIR', 'toko' => 3, 'poin' => 400, 'unit' => 1, 'nota' => 1],
                ],
                'per_nota' => [
                    ['seri' => 'AM1792238626', 'tanggal' => '2026-10-17', 'poin' => 400],
                ],
            ], 200),
        ]);

        $exitCode = Artisan::call('sales:sync-points', [
            '--start' => '2026-10-01',
            '--end' => '2026-10-24',
        ]);

        $this->assertEquals(0, $exitCode);

        $emp->refresh();
        $this->assertEquals(400, $emp->current_points);
        // 400 poin adalah Tier 3 (370 - 444 poin) dengan tarif Rp 1.800 / pt
        $this->assertEquals('Tier 3', $emp->tier_name);
        $this->assertEquals(1800.0, (float) $emp->rate_per_point);
        // Bonus salary = 400 * 1800 = 720.000
        $this->assertEquals(720000.0, (float) $emp->bonus_salary);
    }
}
