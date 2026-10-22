<?php

namespace Tests\Feature;

use App\Models\Account;
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
}
