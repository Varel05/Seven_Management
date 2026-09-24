<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\CustomSuitOrder;
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
}
