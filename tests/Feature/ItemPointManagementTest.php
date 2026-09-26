<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Employee;
use App\Models\EmployeePointLog;
use App\Models\PointSetting;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ItemPointManagementTest extends TestCase
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

    public function test_can_update_individual_and_bulk_product_points_via_web(): void
    {
        $prod1 = Product::create([
            'code' => 'JAS-001',
            'name' => 'Italian Wool Tuxedo',
            'category' => 'jas_blazer_pria',
            'selling_price' => 1500000,
            'cost_price' => 800000,
            'stock' => 5,
            'point_reward' => null,
        ]);

        $prod2 = Product::create([
            'code' => 'DTI-001',
            'name' => 'Silk Bowtie',
            'category' => 'dasi_aksesoris',
            'selling_price' => 100000,
            'cost_price' => 40000,
            'stock' => 10,
            'point_reward' => null,
        ]);

        $response = $this->actingAs($this->user)->post(route('retail.products.update-points'), [
            'products' => [
                ['id' => $prod1->id, 'point_reward' => 28],
                ['id' => $prod2->id, 'point_reward' => 6],
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertEquals(28, $prod1->fresh()->point_reward);
        $this->assertEquals(6, $prod2->fresh()->point_reward);
        $this->assertEquals(28, $prod1->fresh()->effective_point_reward);
        $this->assertEquals(6, $prod2->fresh()->effective_point_reward);
    }

    public function test_can_reset_product_point_reward_to_inherit_category_default(): void
    {
        $prod = Product::create([
            'code' => 'KMG-001',
            'name' => 'Slim Fit Shirt',
            'category' => 'kemeja',
            'selling_price' => 250000,
            'cost_price' => 120000,
            'stock' => 10,
            'point_reward' => 20,
        ]);

        // Submit null/empty string to reset back to category default
        $response = $this->actingAs($this->user)->post(route('retail.products.update-points'), [
            'products' => [
                ['id' => $prod->id, 'point_reward' => ''],
            ],
        ]);

        $response->assertRedirect();
        $this->assertNull($prod->fresh()->point_reward);

        // Fallback to category default for 'kemeja' (which defaults to 5 points)
        $this->assertEquals(5, $prod->fresh()->effective_point_reward);
    }

    public function test_can_update_point_settings_for_categories_and_services(): void
    {
        $response = $this->actingAs($this->user)->post(route('retail.point-settings.update'), [
            'settings' => [
                ['key' => 'item_category:jas_blazer_pria', 'points' => 22],
                ['key' => 'service:cod', 'points' => 15],
                ['key' => 'service:quantity_extra', 'points' => 5],
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertEquals(22, PointSetting::get('item_category:jas_blazer_pria'));
        $this->assertEquals(15, PointSetting::get('service:cod'));
        $this->assertEquals(5, PointSetting::get('service:quantity_extra'));
    }

    public function test_retail_sale_awards_custom_item_points_to_employee(): void
    {
        $employee = Employee::create([
            'name' => 'Sarah CS Specialist',
            'position' => 'Customer Service',
            'role' => Employee::ROLE_CS,
            'status' => 'active',
        ]);

        $product = Product::create([
            'code' => 'JAS-SPEC',
            'name' => 'Handmade Bespoke Suit',
            'category' => 'jas_blazer_pria',
            'selling_price' => 2000000,
            'cost_price' => 1000000,
            'stock' => 10,
            'point_reward' => 35, // custom point override
        ]);

        $payload = [
            'customer_name' => 'Budi Pelanggan',
            'payment_method' => 'cash',
            'account_id' => $this->cashAccount->id,
            'employee_id' => $employee->id,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                ],
            ],
        ];

        $response = $this->actingAs($this->user)->post(route('retail.sales.store'), $payload);

        $response->assertRedirect();

        // Point logs check:
        // Log 1: Base points: 2 qty * 35 = 70 points
        // Log 2: Qty bonus: (2 - 1) * 2 points (default bonus) = 2 points
        // Total points earned: 72 points
        $logs = EmployeePointLog::where('employee_id', $employee->id)->get();
        $this->assertCount(2, $logs);

        $saleLog = $logs->firstWhere('category', EmployeePointLog::CATEGORY_ITEM_SALE);
        $this->assertNotNull($saleLog);
        $this->assertEquals(70, $saleLog->points);
        $this->assertStringContainsString('Handmade Bespoke Suit', $saleLog->notes);
        $this->assertStringContainsString('@35 pt', $saleLog->notes);

        $qtyLog = $logs->firstWhere('category', EmployeePointLog::CATEGORY_QUANTITY);
        $this->assertNotNull($qtyLog);
        $this->assertEquals(2, $qtyLog->points);

        $this->assertEquals(72, $employee->fresh()->current_points);
    }

    public function test_web_interface_displays_point_settings_and_item_points(): void
    {
        $product = Product::create([
            'code' => 'CEL-001',
            'name' => 'Wool Formal Trousers',
            'category' => 'celana_formal',
            'selling_price' => 450000,
            'cost_price' => 220000,
            'stock' => 8,
            'point_reward' => 12,
        ]);

        $response = $this->actingAs($this->user)->get(route('retail.index'));

        $response->assertOk();
        $response->assertSee('Atur Poin Item');
        $response->assertSee('Wool Formal Trousers');
        $response->assertSee('12 pt');
        $response->assertSee('Pengaturan Poin Insentif CS');
        $response->assertSee('Poin Setiap Item Produk');
        $response->assertSee('Standar Kategori');
    }
}
