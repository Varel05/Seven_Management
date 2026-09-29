<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Employee;
use App\Models\EmployeePointLog;
use App\Models\PointSetting;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PointRulesAndBonusClaimTest extends TestCase
{
    use RefreshDatabase;

    protected Account $assetAccount;

    protected Employee $cs1;

    protected Employee $cs2;

    protected Product $tuxedoProduct;

    protected Product $shirtProduct;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.webhook.secret' => 'test_secret_key']);

        $this->assetAccount = Account::create([
            'code' => '1001',
            'name' => 'Kas Toko',
            'type' => 'asset',
        ]);

        Account::create([
            'code' => '4002',
            'name' => 'Pendapatan Penjualan Retail',
            'type' => 'revenue',
        ]);

        Account::create([
            'code' => '4003',
            'name' => 'Pendapatan Sewa Pakaian',
            'type' => 'revenue',
        ]);

        $this->cs1 = Employee::create([
            'name' => 'Siti CS 1',
            'position' => 'Customer Service',
            'role' => Employee::ROLE_CS,
            'phone' => '081211112222',
            'telegram_user_id' => '11112222',
            'base_salary' => 3000000,
            'current_points' => 0,
            'rate_per_point' => 0,
            'pay_day' => 25,
            'asset_account_id' => $this->assetAccount->id,
            'status' => 'active',
            'claim_bonus' => true,
        ]);

        $this->cs2 = Employee::create([
            'name' => 'Budi CS 2',
            'position' => 'Customer Service',
            'role' => Employee::ROLE_CS,
            'phone' => '081233334444',
            'telegram_user_id' => '33334444',
            'base_salary' => 3000000,
            'current_points' => 0,
            'rate_per_point' => 0,
            'pay_day' => 25,
            'asset_account_id' => $this->assetAccount->id,
            'status' => 'active',
            'claim_bonus' => true,
        ]);

        // Tuksedo: 20 poin standar
        $this->tuxedoProduct = Product::create([
            'code' => 'TUX-001',
            'name' => 'Tuksedo Klasik Black Tie',
            'category' => 'tuksedo',
            'selling_price' => 1500000,
            'rental_price' => 500000,
            'cost_price' => 800000,
            'stock' => 10,
        ]);

        // Kemeja: 5 poin standar
        $this->shirtProduct = Product::create([
            'code' => 'KEM-001',
            'name' => 'Kemeja Formal Putih',
            'category' => 'kemeja',
            'selling_price' => 250000,
            'rental_price' => 75000,
            'cost_price' => 120000,
            'stock' => 10,
        ]);
    }

    /**
     * Syarat 1 & 5: Poin mentah (jual / sewa) berdasarkan item, dan poin quantity bernilai 1 per pcs.
     */
    public function test_raw_points_for_sale_and_rental_are_based_on_item(): void
    {
        // 1. Penjualan 2 pcs Tuksedo (20 pt per item) via Telegram Webhook
        $responseSale = $this->withHeader('X-Webhook-Secret', 'test_secret_key')
            ->postJson('/api/webhook/retail/sale', [
                'product_code' => $this->tuxedoProduct->code,
                'quantity' => 2,
                'payment_method' => 'cash',
                'sender_telegram_id' => $this->cs1->telegram_user_id,
            ]);

        $responseSale->assertOk();

        // Poin: Item tuksedo (20 pt) + Qty (2 pt) = 22 pt
        $this->assertEquals(22, $this->cs1->fresh()->current_points);
        $this->assertDatabaseHas('employee_point_logs', [
            'employee_id' => $this->cs1->id,
            'category' => EmployeePointLog::CATEGORY_ITEM_SALE,
            'points' => 20,
        ]);
        $this->assertDatabaseHas('employee_point_logs', [
            'employee_id' => $this->cs1->id,
            'category' => EmployeePointLog::CATEGORY_QUANTITY,
            'points' => 2,
        ]);

        // 2. Penyewaan 1 pcs Kemeja (5 pt per item) via Telegram Webhook
        $responseRent = $this->withHeader('X-Webhook-Secret', 'test_secret_key')
            ->postJson('/api/webhook/retail/sale', [
                'product_code' => $this->shirtProduct->code,
                'quantity' => 1,
                'is_rental' => true,
                'payment_method' => 'cash',
                'sender_telegram_id' => $this->cs1->telegram_user_id,
            ]);

        $responseRent->assertOk();

        // Poin kemeja sewa berdasarkan item = 5 pt + Qty 1 pt = 6 pt tambahan -> Total 28 pt
        $this->assertEquals(28, $this->cs1->fresh()->current_points);
        $this->assertDatabaseHas('employee_point_logs', [
            'employee_id' => $this->cs1->id,
            'category' => EmployeePointLog::CATEGORY_ITEM_RENT,
            'points' => 5,
        ]);
    }

    /**
     * Syarat 2: Poin COD bernilai 1 setiap transaksi COD yang dilayani, trigger dari kata kunci 'cod'.
     */
    public function test_cod_points_awards_1_point_with_keyword_cod_from_telegram(): void
    {
        // Transaksi dengan kata kunci COD di catatan
        $response = $this->withHeader('X-Webhook-Secret', 'test_secret_key')
            ->postJson('/api/webhook/retail/sale', [
                'product_code' => $this->shirtProduct->code,
                'quantity' => 1,
                'payment_method' => 'transfer',
                'notes' => 'Pesanan COD kurir AnterAja',
                'sender_telegram_id' => $this->cs1->telegram_user_id,
            ]);

        $response->assertOk();

        // 5 pt (item) + 1 pt (qty) + 1 pt (cod) = 7 pt
        $this->assertEquals(7, $this->cs1->fresh()->current_points);
        $this->assertDatabaseHas('employee_point_logs', [
            'employee_id' => $this->cs1->id,
            'category' => EmployeePointLog::CATEGORY_COD,
            'points' => 1,
        ]);
    }

    /**
     * Syarat 3: Poin bonus perusahaan bernilai 1 jika CS yang melayani berbeda dari CS yang sedang berjaga.
     */
    public function test_company_bonus_awards_1_point_when_serving_cs_differs_from_on_duty_cs(): void
    {
        // Tetapkan CS 1 sebagai CS yang sedang berjaga
        $this->cs1->setAsOnDuty();
        $this->assertTrue($this->cs1->fresh()->is_on_duty);
        $this->assertFalse($this->cs2->fresh()->is_on_duty);

        // CS 2 melayani transaksi penjualan
        $response = $this->withHeader('X-Webhook-Secret', 'test_secret_key')
            ->postJson('/api/webhook/retail/sale', [
                'product_code' => $this->shirtProduct->code,
                'quantity' => 1,
                'payment_method' => 'cash',
                'sender_telegram_id' => $this->cs2->telegram_user_id,
            ]);

        $response->assertOk();

        // CS 2 mendapatkan: 5 pt (item) + 1 pt (qty) + 1 pt (bonus perusahaan karena CS 1 berjaga) = 7 pt
        $this->assertEquals(7, $this->cs2->fresh()->current_points);
        $this->assertDatabaseHas('employee_point_logs', [
            'employee_id' => $this->cs2->id,
            'category' => EmployeePointLog::CATEGORY_CROSS_COMPANY,
            'points' => 1,
        ]);

        // CS 1 yang sedang berjaga poinnya tidak berubah
        $this->assertEquals(0, $this->cs1->fresh()->current_points);
    }

    /**
     * Syarat 4: Poin review bernilai 1 untuk setiap transaksi review baik.
     */
    public function test_review_points_default_value_is_1(): void
    {
        $this->assertEquals(1, EmployeePointLog::DEFAULT_REVIEW_POINTS);
        $this->assertEquals(1, PointSetting::get('service:review'));

        // Pemberian poin review via webhook
        $response = $this->withHeader('X-Webhook-Secret', 'test_secret_key')
            ->postJson('/api/webhook/payroll/points', [
                'employee_id' => $this->cs1->id,
                'points' => 1,
                'category' => 'review',
                'notes' => 'Review bintang 5 di Google Maps',
            ]);

        $response->assertOk();
        $this->assertEquals(1, $this->cs1->fresh()->current_points);
        $this->assertDatabaseHas('employee_point_logs', [
            'employee_id' => $this->cs1->id,
            'category' => EmployeePointLog::CATEGORY_REVIEW,
            'points' => 1,
        ]);
    }

    /**
     * Tambahan: Jika poin diambil untuk bonus gaji, poin yang terakumulasi dikurangi besar poin tier tersebut.
     */
    public function test_accumulated_points_are_reduced_by_tier_points_when_salary_is_paid_with_bonus(): void
    {
        // CS 1 memiliki 250 poin (Tier 1: min 200 poin @ Rp 1.000)
        $this->cs1->update([
            'current_points' => 250,
            'rate_per_point' => 1000,
            'claim_bonus' => true,
        ]);

        // Bonus salary = 250 * 1000 = Rp 250.000
        $this->assertEquals(250000, $this->cs1->bonus_salary);
        $this->assertEquals(200, $this->cs1->tier_points_to_deduct);
        $this->assertEquals(3250000, $this->cs1->total_salary);

        // Eksekusi pembayaran payroll
        $journal = $this->cs1->executePayrollPosting(null, 'website');
        $this->assertNotNull($journal);

        // Poin terakumulasi berkurang sebesar besar poin tier 1 (200 poin) -> sisa 50 poin
        $freshCs1 = $this->cs1->fresh();
        $this->assertEquals(50, $freshCs1->current_points);

        // Tercatat log pengurangan poin
        $this->assertDatabaseHas('employee_point_logs', [
            'employee_id' => $this->cs1->id,
            'points' => -200,
            'category' => EmployeePointLog::CATEGORY_MANUAL,
        ]);
    }

    /**
     * Tambahan: CS memilih TIDAK mengambil bonus gaji bulan ini via Telegram.
     * Bonus menjadi 0 dan poin terakumulasi tidak dikurangi saat gajian.
     */
    public function test_cs_can_choose_not_to_claim_bonus_and_points_are_preserved(): void
    {
        // CS 1 memiliki 250 poin, tetapi memilih simpan poin (tidak klaim bonus bulan ini)
        $this->cs1->update([
            'current_points' => 250,
            'rate_per_point' => 1000,
            'claim_bonus' => false,
        ]);

        // Bonus gaji = Rp 0 karena memilih simpan poin
        $this->assertEquals(0, $this->cs1->bonus_salary);
        $this->assertEquals(0, $this->cs1->tier_points_to_deduct);
        $this->assertEquals(3000000, $this->cs1->total_salary); // Gaji Pokok saja

        // Eksekusi pembayaran payroll
        $this->cs1->executePayrollPosting(null, 'website');

        // Poin CS 1 tetap UTUH 250 poin (tidak dipotong)
        $this->assertEquals(250, $this->cs1->fresh()->current_points);
    }

    /**
     * Tambahan: CS dapat mengatur preferensi klaim bonus via Webhook Telegram (/klaimbonus).
     */
    public function test_cs_can_toggle_bonus_preference_via_webhook(): void
    {
        $this->cs1->update(['current_points' => 300, 'claim_bonus' => true]);

        // CS 1 mengirim permintaan untuk menyimpan poin (tidak ambil)
        $responseSave = $this->withHeader('X-Webhook-Secret', 'test_secret_key')
            ->postJson('/api/webhook/payroll/bonus-preference', [
                'sender_telegram_id' => $this->cs1->telegram_user_id,
                'choice' => 'simpan',
            ]);

        $responseSave->assertOk();
        $responseSave->assertJsonPath('claim_bonus', false);
        $this->assertFalse($this->cs1->fresh()->claim_bonus);
        $this->assertStringContainsString('MENYIMPAN POIN', $responseSave->json('message'));

        // CS 1 mengecek /poinsaya dan melihat status preferensinya
        $responseMyPoints = $this->withHeader('X-Webhook-Secret', 'test_secret_key')
            ->getJson('/api/webhook/payroll/my-points?sender_telegram_id='.$this->cs1->telegram_user_id);

        $responseMyPoints->assertOk();
        $this->assertStringContainsString('Simpan Poin', $responseMyPoints->json('message'));

        // CS 1 berubah pikiran dan ingin mencairkan bonus bulan ini
        $responseClaim = $this->withHeader('X-Webhook-Secret', 'test_secret_key')
            ->postJson('/api/webhook/payroll/bonus-preference', [
                'sender_telegram_id' => $this->cs1->telegram_user_id,
                'choice' => 'ambil',
            ]);

        $responseClaim->assertOk();
        $responseClaim->assertJsonPath('claim_bonus', true);
        $this->assertTrue($this->cs1->fresh()->claim_bonus);
        $this->assertStringContainsString('MENGAMBIL BONUS', $responseClaim->json('message'));

        // Test sending raw text "/klaimbonus simpan" to my-points (delegation)
        $responseRaw = $this->withHeader('X-Webhook-Secret', 'test_secret_key')
            ->postJson('/api/webhook/payroll/my-points', [
                'sender_telegram_id' => $this->cs1->telegram_user_id,
                'text' => '/klaimbonus simpan',
            ]);

        $responseRaw->assertOk();
        $responseRaw->assertJsonPath('claim_bonus', false);
        $this->assertFalse($this->cs1->fresh()->claim_bonus);
        $this->assertStringContainsString('MENYIMPAN POIN', $responseRaw->json('message'));
    }

    /**
     * CS dapat mengatur status piket/jaga via Webhook Telegram (/jaga).
     */
    public function test_cs_can_toggle_duty_status_via_webhook(): void
    {
        // CS 1 mengaktifkan jaga via text "/jaga"
        $responseOn = $this->withHeader('X-Webhook-Secret', 'test_secret_key')
            ->postJson('/api/webhook/payroll/duty', [
                'sender_telegram_id' => $this->cs1->telegram_user_id,
                'text' => '/jaga on',
            ]);

        $responseOn->assertOk();
        $responseOn->assertJsonPath('is_on_duty', true);
        $this->assertTrue($this->cs1->fresh()->is_on_duty);

        // CS 1 mengakhiri jaga via text "/jaga off"
        $responseOff = $this->withHeader('X-Webhook-Secret', 'test_secret_key')
            ->postJson('/api/webhook/payroll/duty', [
                'sender_telegram_id' => $this->cs1->telegram_user_id,
                'text' => '/jaga off',
            ]);

        $responseOff->assertOk();
        $responseOff->assertJsonPath('is_on_duty', false);
        $this->assertFalse($this->cs1->fresh()->is_on_duty);
    }
}

