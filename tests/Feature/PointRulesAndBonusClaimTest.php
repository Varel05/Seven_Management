<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Employee;
use App\Models\EmployeePointLog;
use App\Models\PointSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PointRulesAndBonusClaimTest extends TestCase
{
    use RefreshDatabase;

    protected Account $assetAccount;

    protected Employee $cs1;

    protected Employee $cs2;

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
            'base_salary' => 3000000,
            'current_points' => 0,
            'rate_per_point' => 0,
            'pay_day' => 25,
            'asset_account_id' => $this->assetAccount->id,
            'status' => 'active',
            'claim_bonus' => true,
        ]);

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
                'sender_phone' => $this->cs1->phone,
                'choice' => 'simpan',
            ]);

        $responseSave->assertOk();
        $responseSave->assertJsonPath('claim_bonus', false);
        $this->assertFalse($this->cs1->fresh()->claim_bonus);
        $this->assertStringContainsString('MENYIMPAN POIN', $responseSave->json('message'));

        // CS 1 mengecek /poinsaya dan melihat status preferensinya
        $responseMyPoints = $this->withHeader('X-Webhook-Secret', 'test_secret_key')
            ->getJson('/api/webhook/payroll/my-points?sender_phone='.$this->cs1->phone);

        $responseMyPoints->assertOk();
        $this->assertStringContainsString('Simpan Poin', $responseMyPoints->json('message'));

        // CS 1 berubah pikiran dan ingin mencairkan bonus bulan ini
        $responseClaim = $this->withHeader('X-Webhook-Secret', 'test_secret_key')
            ->postJson('/api/webhook/payroll/bonus-preference', [
                'sender_phone' => $this->cs1->phone,
                'choice' => 'ambil',
            ]);

        $responseClaim->assertOk();
        $responseClaim->assertJsonPath('claim_bonus', true);
        $this->assertTrue($this->cs1->fresh()->claim_bonus);
        $this->assertStringContainsString('MENGAMBIL BONUS', $responseClaim->json('message'));

        // Test sending raw text "/klaimbonus simpan" to my-points (delegation)
        $responseRaw = $this->withHeader('X-Webhook-Secret', 'test_secret_key')
            ->postJson('/api/webhook/payroll/my-points', [
                'sender_phone' => $this->cs1->phone,
                'text' => '/klaimbonus simpan',
            ]);

        $responseRaw->assertOk();
        $responseRaw->assertJsonPath('claim_bonus', false);
        $this->assertFalse($this->cs1->fresh()->claim_bonus);
        $this->assertStringContainsString('MENYIMPAN POIN', $responseRaw->json('message'));
    }
}
