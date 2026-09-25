<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\CostSheet;
use App\Models\CostSheetItem;
use App\Models\CostSheetVariant;
use App\Models\CustomSuitOrder;
use App\Models\Material;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaterialAndProductionTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Account $account1004;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->account1004 = Account::create([
            'code' => '1004',
            'name' => 'Persediaan Bahan Baku & Pembantu',
            'type' => 'asset',
        ]);
    }

    public function test_can_list_materials_and_restock(): void
    {
        $material = Material::create([
            'code' => 'MAT-TEST',
            'name' => 'Kain Wol Cashmere',
            'category' => 'raw_material',
            'unit' => 'meter',
            'standard_cost' => 150000,
            'stock' => 10,
            'min_stock' => 5,
            'account_id' => $this->account1004->id,
        ]);

        $response = $this->actingAs($this->user)->get(route('materials.index'));
        $response->assertOk();
        $response->assertSee('Kain Wol Cashmere');

        // Restock
        $restockResponse = $this->actingAs($this->user)->post(route('materials.restock', $material), [
            'quantity' => 20,
            'unit_cost' => 145000,
            'reference_number' => 'PO-TEST-01',
            'notes' => 'Restock bahan supplier A',
        ]);

        $restockResponse->assertRedirect(route('materials.index'));
        $material->refresh();
        $this->assertEquals(30, $material->stock);
        $this->assertEquals(145000, $material->standard_cost);
    }

    public function test_can_calculate_cost_sheet_variant_totals(): void
    {
        $product = Product::create([
            'code' => 'PRD-JAS',
            'name' => 'Jas Regular Navy',
            'category' => 'jas_blazer_pria',
            'size' => 'S',
            'cost_price' => 0,
            'selling_price' => 500000,
            'stock' => 0,
            'min_stock' => 2,
        ]);

        $costSheet = CostSheet::create([
            'code' => 'HPP-JAS-NAVY',
            'name' => 'Jas Regular Navy',
            'category' => 'jas_reguler',
            'fabric_type' => 'Semiwool Navy',
            'product_id' => $product->id,
        ]);

        $variant = CostSheetVariant::create([
            'cost_sheet_id' => $costSheet->id,
            'size' => 'S',
        ]);

        $matKain = Material::create([
            'code' => 'MAT-KAIN',
            'name' => 'Kain Semiwool',
            'category' => 'raw_material',
            'unit' => 'meter',
            'standard_cost' => 80000,
            'stock' => 50,
        ]);

        $matJahit = Material::create([
            'code' => 'LAB-JHT',
            'name' => 'Ongkos Jahit Jas',
            'category' => 'direct_labor',
            'unit' => 'pcs',
            'standard_cost' => 70000,
            'stock' => 0,
        ]);

        CostSheetItem::create([
            'cost_sheet_variant_id' => $variant->id,
            'material_id' => $matKain->id,
            'quantity' => 1.5,
            'unit_price' => 80000,
            'subtotal' => 120000,
        ]);

        CostSheetItem::create([
            'cost_sheet_variant_id' => $variant->id,
            'material_id' => $matJahit->id,
            'quantity' => 1.0,
            'unit_price' => 70000,
            'subtotal' => 70000,
        ]);

        $variant->recalculateTotals();
        $this->assertEquals(120000, $variant->total_material_cost);
        $this->assertEquals(70000, $variant->total_labor_cost);
        $this->assertEquals(190000, $variant->total_cost_price);

        // Sync to product
        $syncResponse = $this->actingAs($this->user)->post(route('cost-sheets.sync-product', $variant), [
            'product_id' => $product->id,
        ]);

        $syncResponse->assertSessionHasNoErrors();
        $product->refresh();
        $this->assertEquals(190000, $product->cost_price);
        $this->assertEquals($variant->id, $product->cost_sheet_variant_id);
    }

    public function test_manual_production_adjusts_material_stock_and_product(): void
    {
        $product = Product::create([
            'code' => 'PRD-JAS-S',
            'name' => 'Jas Jetblack Size S',
            'category' => 'jas_blazer_pria',
            'size' => 'S',
            'cost_price' => 100000,
            'selling_price' => 350000,
            'stock' => 2,
            'min_stock' => 1,
        ]);

        $costSheet = CostSheet::create([
            'code' => 'HPP-JETBLACK',
            'name' => 'Jas Jetblack',
            'product_id' => $product->id,
        ]);

        $variant = CostSheetVariant::create([
            'cost_sheet_id' => $costSheet->id,
            'size' => 'S',
            'total_cost_price' => 150000,
        ]);

        $kain = Material::create([
            'code' => 'MAT-JETBLACK',
            'name' => 'Kain Jetblack Premium',
            'category' => 'raw_material',
            'unit' => 'meter',
            'standard_cost' => 60000,
            'stock' => 20.0,
            'min_stock' => 5.0,
        ]);

        // Produksi 5 pcs: Standar BOM butuh 5 x 1.6 = 8.0 meter.
        // User memasukkan manual 8.5 meter karena ada kain cacat (user requirement 2)!
        $response = $this->actingAs($this->user)->post(route('production.store'), [
            'cost_sheet_variant_id' => $variant->id,
            'product_id' => $product->id,
            'batch_quantity' => 5,
            'items' => [
                [
                    'material_id' => $kain->id,
                    'actual_quantity' => 8.5, // Manual adjustment
                    'unit_cost' => 60000,
                ],
            ],
            'update_product_cost' => 1,
            'notes' => 'Ada sisa kain terbuang 0.5 meter.',
        ]);

        $response->assertRedirect(route('materials.index'));
        $response->assertSessionHas('success');

        $kain->refresh();
        $product->refresh();

        // Stok kain berkurang dari 20.0 menjadi 11.5
        $this->assertEquals(11.5, $kain->stock);

        // Stok baju bertambah 5 (dari 2 menjadi 7)
        $this->assertEquals(7, $product->stock);

        // HPP baru per pc = (8.5 * 60.000) / 5 = 510.000 / 5 = 102.000
        $this->assertEquals(102000, $product->cost_price);
    }

    public function test_custom_suit_order_with_material_stock_deduction(): void
    {
        $kain = Material::create([
            'code' => 'MAT-WOOL',
            'name' => 'Kain Wool Italia',
            'category' => 'raw_material',
            'unit' => 'meter',
            'standard_cost' => 200000,
            'stock' => 15.0,
            'min_stock' => 2.0,
        ]);

        // Buat pesanan kustom menggunakan bahan kain ini
        $response = $this->actingAs($this->user)->post(route('custom-orders.store'), [
            'customer_name' => 'Bpk. Handoko',
            'customer_phone' => '081234567890',
            'order_date' => date('Y-m-d'),
            'suit_type' => 'jas_blazer_pria',
            'material_id' => $kain->id,
            'material_meters' => 2.8,
            'fabric_type' => 'Kain Wool Italia',
            'color' => 'Navy Blue',
            'total_price' => 2500000,
            'down_payment' => 1000000,
            'account_id' => $this->account1004->id,
        ]);

        $order = CustomSuitOrder::where('customer_name', 'Bpk. Handoko')->first();
        $this->assertNotNull($order);
        $this->assertEquals($kain->id, $order->material_id);
        $this->assertEquals(2.8, $order->material_meters);
        $this->assertFalse($order->is_material_cut);

        // Ubah status ke cutting_sewing -> otomatis memotong stok bahan kain di gudang
        $statusResponse = $this->actingAs($this->user)->patch(route('custom-orders.status', $order), [
            'production_status' => 'cutting_sewing',
        ]);

        $statusResponse->assertSessionHas('success');

        $kain->refresh();
        $order->refresh();

        $this->assertTrue($order->is_material_cut);
        // Stok kain berkurang dari 15.0 - 2.8 = 12.2 meter
        $this->assertEquals(12.2, $kain->stock);
    }
}
