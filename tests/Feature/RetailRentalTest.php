<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Product;
use App\Models\RetailSale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RetailRentalTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Account $cashAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->cashAccount = Account::create([
            'code' => '1001',
            'name' => 'Kas Operasional',
            'type' => 'asset',
        ]);
    }

    public function test_can_process_clothing_rental_transaction(): void
    {
        $product = Product::create([
            'code' => 'JAS-RENT-001',
            'name' => 'Tuxedo Rental Luxury Black',
            'category' => 'tuksedo',
            'cost_price' => 500000,
            'selling_price' => 1500000,
            'rental_price' => 350000,
            'stock' => 5,
            'min_stock' => 2,
        ]);

        $response = $this->actingAs($this->user)->post(route('retail.sales.store'), [
            'transaction_type' => 'rental',
            'customer_name' => 'Bpk. Hendra Gunawan',
            'customer_phone' => '081234567890',
            'payment_method' => 'cash',
            'account_id' => $this->cashAccount->id,
            'rental_start_date' => now()->toDateString(),
            'rental_end_date' => now()->addDays(3)->toDateString(),
            'deposit_amount' => 100000,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                ],
            ],
        ]);

        $response->assertSessionHas('success');
        $this->assertEquals(4, $product->fresh()->stock);

        $sale = RetailSale::first();
        $this->assertNotNull($sale);
        $this->assertEquals('rental', $sale->transaction_type);
        $this->assertEquals('active', $sale->rental_status);
        $this->assertEquals(350000, $sale->total_amount);
        $this->assertEquals(100000, $sale->deposit_amount);
        $this->assertNotNull($sale->journal_entry_id);
    }

    public function test_can_return_rented_clothing_and_restore_stock(): void
    {
        $product = Product::create([
            'code' => 'JAS-RENT-002',
            'name' => 'Blazer Navy Blue Fit',
            'category' => 'jas_blazer_pria',
            'cost_price' => 400000,
            'selling_price' => 1200000,
            'rental_price' => 250000,
            'stock' => 2,
            'min_stock' => 1,
        ]);

        // Sewa produk
        $this->actingAs($this->user)->post(route('retail.sales.store'), [
            'transaction_type' => 'rental',
            'customer_name' => 'Bpk. Ahmad',
            'payment_method' => 'cash',
            'account_id' => $this->cashAccount->id,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                ],
            ],
        ]);

        $this->assertEquals(1, $product->fresh()->stock);

        $sale = RetailSale::first();
        $this->assertEquals('active', $sale->rental_status);

        // Kembalikan pakaian sewa
        $returnResponse = $this->actingAs($this->user)->post(route('retail.rentals.return', $sale));
        $returnResponse->assertSessionHas('success');

        $this->assertEquals('returned', $sale->fresh()->rental_status);
        $this->assertNotNull($sale->fresh()->rental_return_date);
        $this->assertEquals(2, $product->fresh()->stock); // Stok dipulihkan
    }
}
