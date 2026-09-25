<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\CustomSuitOrder;
use App\Models\Material;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomSuitOrderTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Account $cashAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        $this->cashAccount = Account::create(['code' => '1001', 'name' => 'Kas Operasional', 'type' => 'asset']);
        Account::create(['code' => '4003', 'name' => 'Pendapatan Jasa Pembuatan Jas Custom', 'type' => 'revenue']);
    }

    public function test_custom_orders_page_loads_successfully(): void
    {
        $response = $this->actingAs($this->user)->get(route('custom-orders.index'));

        $response->assertOk();
        $response->assertSee('Jasa Pembuatan Jas Custom');
    }

    public function test_estimate_calculation_ajax_endpoint_returns_valid_data(): void
    {
        $response = $this->actingAs($this->user)->postJson(route('custom-orders.estimate'), [
            'suit_type' => 'tuksedo',
            'height' => 175,
            'chest' => 100,
            'waist' => 84,
            'fabric_price' => 300000,
            'labor_cost' => 800000,
            'target_margin' => 50,
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $this->assertGreaterThan(0, $response->json('data.materials.main_fabric_meters'));
        $this->assertGreaterThan(0, $response->json('data.financial.suggested_price'));
    }

    public function test_can_create_custom_suit_order_with_dp_and_journal_posting(): void
    {
        $payload = [
            'customer_name' => 'Dr. Robert William',
            'customer_phone' => '08123456789',
            'order_date' => now()->toDateString(),
            'due_date' => now()->addDays(14)->toDateString(),
            'suit_type' => 'jas_blazer_pria',
            'fabric_type' => 'Super 120s Wool',
            'color' => 'Navy',
            'height' => 175,
            'chest' => 102,
            'waist' => 86,
            'total_price' => 2800000,
            'down_payment' => 1400000,
            'account_id' => $this->cashAccount->id,
            'notes' => 'Notch lapel, two-button, horn buttons.',
        ];

        $response = $this->actingAs($this->user)->post(route('custom-orders.store'), $payload);

        $response->assertRedirect();
        $order = CustomSuitOrder::first();
        $this->assertNotNull($order);
        $this->assertEquals('Dr. Robert William', $order->customer_name);
        $this->assertEquals('partial_dp', $order->payment_status);
        $this->assertEquals(1400000, $order->remaining_payment);

        // Jurnal DP harus terbuat otomatis
        $this->assertNotNull($order->journal_entry_id);
        $this->assertDatabaseHas('journal_entries', [
            'id' => $order->journal_entry_id,
            'source' => 'custom_suit',
            'status' => 'verified',
        ]);
    }

    public function test_can_update_production_status_and_record_settlement_payment(): void
    {
        $order = CustomSuitOrder::create([
            'order_number' => 'CST-20260924-TEST',
            'customer_name' => 'Alexander',
            'order_date' => now(),
            'suit_type' => 'tuksedo',
            'total_cost' => 1200000,
            'total_price' => 2500000,
            'down_payment' => 1000000,
            'payment_status' => 'partial_dp',
            'production_status' => 'consultation',
        ]);

        // 1. Update status produksi
        $statusResponse = $this->actingAs($this->user)->patch(route('custom-orders.status', $order), [
            'production_status' => 'cutting_sewing',
        ]);
        $statusResponse->assertRedirect();
        $this->assertEquals('cutting_sewing', $order->fresh()->production_status);

        // 2. Catat pelunasan sisa tagihan 1.500.000
        $paymentResponse = $this->actingAs($this->user)->post(route('custom-orders.payment', $order), [
            'amount' => 1500000,
            'account_id' => $this->cashAccount->id,
        ]);
        $paymentResponse->assertRedirect();
        $this->assertEquals('paid', $order->fresh()->payment_status);
        $this->assertEquals(2500000, $order->fresh()->down_payment);
    }

    public function test_n8n_telegram_webhook_can_estimate_and_create_order(): void
    {
        $secret = 'test-secret';
        config(['services.webhook.secret' => $secret]);

        // 1. Webhook AI Estimasi dari Telegram
        $estimateResponse = $this->withHeader('X-Webhook-Secret', $secret)
            ->postJson('/api/webhook/custom-suit/estimate', [
                'suit_type' => 'jas_blazer_pria',
                'height' => 170,
                'chest' => 96,
                'waist' => 82,
            ]);

        $estimateResponse->assertOk();
        $estimateResponse->assertJsonPath('status', true);
        $this->assertStringContainsString('Estimasi Bahan', $estimateResponse->json('message'));

        // 2. Webhook Pembuatan Pesanan dari Bot Telegram
        $orderResponse = $this->withHeader('X-Webhook-Secret', $secret)
            ->postJson('/api/webhook/custom-suit/order', [
                'customer_name' => 'Bpk. Ahmad Telegram',
                'customer_phone' => '0899887766',
                'suit_type' => 'setelan_formal',
                'down_payment' => 500000,
                'account_code' => '1001',
            ]);

        $orderResponse->assertOk();
        $orderResponse->assertJsonPath('status', true);
        $this->assertDatabaseHas('custom_suit_orders', [
            'customer_name' => 'Bpk. Ahmad Telegram',
            'suit_type' => 'setelan_formal',
            'source' => 'telegram',
        ]);
    }

    public function test_n8n_telegram_webhook_can_track_and_update_custom_suit_status(): void
    {
        $secret = 'test-secret';
        config(['services.webhook.secret' => $secret]);

        $order = CustomSuitOrder::create([
            'order_number' => 'CST-TEST-001',
            'customer_name' => 'Bpk. Hendra Testing',
            'customer_phone' => '0812345678',
            'order_date' => now()->toDateString(),
            'due_date' => now()->addDays(14)->toDateString(),
            'suit_type' => 'jas_blazer_pria',
            'production_status' => 'consultation',
            'payment_status' => 'partial_dp',
            'total_price' => 1500000,
            'down_payment' => 500000,
        ]);

        // 1. Cek Tracking Status via Webhook
        $trackResponse = $this->withHeader('X-Webhook-Secret', $secret)
            ->getJson('/api/webhook/custom-suit/status?q=Hendra');

        $trackResponse->assertOk();
        $trackResponse->assertJsonPath('status', true);
        $this->assertStringContainsString('CST-TEST-001', $trackResponse->json('message'));
        $this->assertStringContainsString('Konsultasi / Ukur', $trackResponse->json('message'));

        // 2. Update Status Produksi via Webhook
        $updateResponse = $this->withHeader('X-Webhook-Secret', $secret)
            ->postJson('/api/webhook/custom-suit/update-status', [
                'order_query' => 'CST-TEST-001',
                'status' => 'fitting',
            ]);

        $updateResponse->assertOk();
        $updateResponse->assertJsonPath('status', true);
        $this->assertEquals('fitting', $order->fresh()->production_status);
        $this->assertStringContainsString('Fitting Klien', $updateResponse->json('message'));
    }

    public function test_estimate_integrates_supporting_materials_labor_and_overhead_from_hpp(): void
    {
        // Buat master material HPP di database
        $furing = Material::create([
            'code' => 'MAT-DRM',
            'name' => 'Furing Durmil',
            'category' => 'supporting_material',
            'unit' => 'meter',
            'standard_cost' => 13000,
            'stock' => 50,
            'min_stock' => 5,
        ]);

        $listrik = Material::create([
            'code' => 'BOP-ELC-7',
            'name' => 'Beban Listrik Operasional (Standar)',
            'category' => 'overhead',
            'unit' => 'pcs',
            'standard_cost' => 7000,
            'stock' => 0,
            'min_stock' => 0,
        ]);

        $response = $this->actingAs($this->user)->postJson(route('custom-orders.estimate'), [
            'suit_type' => 'jas_blazer_pria',
            'quality_tier' => 'reguler',
            'height' => 170,
            'chest' => 96,
            'waist' => 82,
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);

        // Pastikan bahan pendukung otomatis terisi
        $supporting = $response->json('data.materials.supporting_materials');
        $this->assertIsArray($supporting);
        $this->assertNotEmpty($supporting);

        // Furing Durmil harus ada dengan harga dan material_id dari database
        $furingItem = collect($supporting)->firstWhere('code', 'MAT-DRM');
        $this->assertNotNull($furingItem);
        $this->assertEquals($furing->id, $furingItem['material_id']);
        $this->assertEquals(13000, $furingItem['unit_price']);

        // Jasa jahit & finishing harus terstruktur
        $laborItems = $response->json('data.labor.items');
        $this->assertNotEmpty($laborItems);
        $this->assertGreaterThan(0, $response->json('data.labor.labor_cost'));

        // Kost tambahan / overhead listrik harus terintegrasi
        $overheadItems = $response->json('data.overhead.items');
        $this->assertNotEmpty($overheadItems);
        $this->assertGreaterThan(0, $response->json('data.overhead.total_overhead_cost'));
        $elcItem = collect($overheadItems)->firstWhere('code', 'BOP-ELC-7');
        $this->assertNotNull($elcItem);
        $this->assertEquals(7000, $elcItem['unit_price']);

        // Total HPP harus mencakup bahan utama + bahan tambahan + jasa jahit + overhead
        $financial = $response->json('data.financial');
        $this->assertEquals(
            $financial['material_cost'] + $financial['labor_cost'] + $financial['overhead_cost'],
            $financial['total_cost']
        );
    }

    public function test_cutting_material_deducts_both_main_fabric_and_supporting_materials(): void
    {
        $kain = Material::create([
            'code' => 'MAT-JTB',
            'name' => 'Kain Jetblack',
            'category' => 'raw_material',
            'unit' => 'meter',
            'standard_cost' => 60000,
            'stock' => 10.0,
            'min_stock' => 2.0,
        ]);

        $furing = Material::create([
            'code' => 'MAT-DRM',
            'name' => 'Furing Durmil',
            'category' => 'supporting_material',
            'unit' => 'meter',
            'standard_cost' => 13000,
            'stock' => 20.0,
            'min_stock' => 2.0,
        ]);

        $kancing = Material::create([
            'code' => 'ACC-KCB',
            'name' => 'Kancing Besar Niko',
            'category' => 'accessory',
            'unit' => 'pcs',
            'standard_cost' => 700,
            'stock' => 100.0,
            'min_stock' => 10.0,
        ]);

        // Buat pesanan jas custom baru
        $createResponse = $this->actingAs($this->user)->post(route('custom-orders.store'), [
            'customer_name' => 'Bpk. Ridwan',
            'order_date' => now()->toDateString(),
            'suit_type' => 'jas_blazer_pria',
            'material_id' => $kain->id,
            'material_meters' => 2.5,
            'total_price' => 1500000,
            'down_payment' => 500000,
            'account_id' => $this->cashAccount->id,
        ]);

        $createResponse->assertRedirect();
        $order = CustomSuitOrder::where('customer_name', 'Bpk. Ridwan')->first();
        $this->assertNotNull($order);
        $this->assertFalse($order->is_material_cut);
        $this->assertGreaterThan(0, $order->overhead_cost);

        // Eksekusi potong bahan
        $cutResponse = $this->actingAs($this->user)->post(route('custom-orders.cut-material', $order));
        $cutResponse->assertRedirect();
        $this->assertTrue($order->fresh()->is_material_cut);

        // Stok kain utama berkurang dari 10.0 - 2.5 = 7.5
        $this->assertEquals(7.5, $kain->fresh()->stock);

        // Stok furing durmil berkurang dari stok awal
        $this->assertLessThan(20.0, $furing->fresh()->stock);

        // Stok kancing berkurang dari 100
        $this->assertLessThan(100.0, $kancing->fresh()->stock);
    }
}
