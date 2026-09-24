<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Product;
use App\Models\RetailSale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RetailManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Account $cashAccount;

    protected Account $bankAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        $this->cashAccount = Account::create(['code' => '1001', 'name' => 'Kas Operasional', 'type' => 'asset']);
        $this->bankAccount = Account::create(['code' => '1002', 'name' => 'Bank BCA', 'type' => 'asset']);
        Account::create(['code' => '1003', 'name' => 'Persediaan Barang Dagang (Retail)', 'type' => 'asset']);
        Account::create(['code' => '4002', 'name' => 'Pendapatan Penjualan Retail', 'type' => 'revenue']);
        Account::create(['code' => '5004', 'name' => 'Beban Pokok Penjualan (HPP) Retail', 'type' => 'expense']);
    }

    public function test_retail_dashboard_page_loads_successfully(): void
    {
        $product = Product::create([
            'code' => 'JAS-001',
            'name' => 'Navy Wool Blazer',
            'category' => 'jas_blazer_pria',
            'size' => 'L',
            'color' => 'Navy',
            'cost_price' => 700000,
            'selling_price' => 1350000,
            'stock' => 10,
            'min_stock' => 3,
        ]);

        $response = $this->actingAs($this->user)->get(route('retail.index'));

        $response->assertOk();
        $response->assertSee('Navy Wool Blazer');
        $response->assertSee('JAS-001');
    }

    public function test_can_create_new_retail_product(): void
    {
        $payload = [
            'code' => 'TXD-101',
            'name' => 'Satin Lapel Tuxedo',
            'category' => 'tuksedo',
            'size' => 'XL',
            'color' => 'Hitam',
            'cost_price' => 1200000,
            'selling_price' => 2400000,
            'stock' => 5,
            'min_stock' => 2,
            'description' => 'Tuksedo formal premium.',
        ];

        $response = $this->actingAs($this->user)->post(route('retail.products.store'), $payload);

        $response->assertRedirect();
        $this->assertDatabaseHas('products', [
            'code' => 'TXD-101',
            'name' => 'Satin Lapel Tuxedo',
            'category' => 'tuksedo',
            'stock' => 5,
        ]);
    }

    public function test_can_process_retail_sale_with_stock_decrement_and_journal_posting(): void
    {
        $product = Product::create([
            'code' => 'KMJ-001',
            'name' => 'White Formal Shirt',
            'category' => 'kemeja',
            'size' => 'M',
            'color' => 'Putih',
            'cost_price' => 100000,
            'selling_price' => 250000,
            'stock' => 15,
            'min_stock' => 3,
        ]);

        $payload = [
            'customer_name' => 'Budi Santoso',
            'payment_method' => 'cash',
            'account_id' => $this->cashAccount->id,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                ],
            ],
        ];

        $response = $this->actingAs($this->user)->post(route('retail.sales.store'), $payload);

        $response->assertRedirect();

        // 1. Stok berkurang
        $this->assertEquals(13, $product->fresh()->stock);

        // 2. Retail Sale dibuat
        $this->assertDatabaseHas('retail_sales', [
            'customer_name' => 'Budi Santoso',
            'total_amount' => 500000, // 2 x 250.000
            'total_cost' => 200000,   // 2 x 100.000
        ]);

        // 3. Jurnal akuntansi terposting
        $sale = RetailSale::first();
        $this->assertNotNull($sale->journal_entry_id);
        $this->assertDatabaseHas('journal_entries', [
            'id' => $sale->journal_entry_id,
            'source' => 'retail',
            'status' => 'verified',
        ]);
    }

    public function test_webhook_can_check_retail_stock_and_record_sale_from_telegram(): void
    {
        $secret = 'test-secret';
        config(['services.webhook.secret' => $secret]);

        $product = Product::create([
            'code' => 'POL-001',
            'name' => 'Classic Polo Shirt',
            'category' => 'polo',
            'cost_price' => 60000,
            'selling_price' => 150000,
            'stock' => 8,
            'min_stock' => 2,
        ]);

        // 1. Cek stok via Webhook n8n
        $stockResponse = $this->withHeader('X-Webhook-Secret', $secret)
            ->getJson('/api/webhook/retail/stock?q=Polo');

        $stockResponse->assertOk();
        $stockResponse->assertJsonFragment(['status' => true]);
        $this->assertStringContainsString('Classic Polo Shirt', $stockResponse->json('message'));

        // 2. Catat penjualan retail via Webhook n8n Telegram
        $saleResponse = $this->withHeader('X-Webhook-Secret', $secret)
            ->postJson('/api/webhook/retail/sale', [
                'product_code' => 'POL-001',
                'quantity' => 2,
                'customer_name' => 'Pelanggan Telegram',
                'account_code' => '1001',
            ]);

        $saleResponse->assertOk();
        $this->assertEquals(6, $product->fresh()->stock);
        $this->assertDatabaseHas('retail_sales', [
            'customer_name' => 'Pelanggan Telegram',
            'total_amount' => 300000,
        ]);
    }
}
