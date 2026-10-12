<?php

namespace Tests\Feature;

use App\Enums\EmployeeRole;
use App\Models\Account;
use App\Models\Employee;
use App\Models\EmployeePointLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeRoleAndPointsTest extends TestCase
{
    use RefreshDatabase;

    protected Employee $ownerEmployee;

    protected Employee $akuntanEmployee;

    protected Employee $csEmployee;

    protected Account $assetAccount;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.webhook.secret' => 'test_secret_key',
            'services.telegram.owner_phones' => ['081211111111'],
            'services.telegram.akuntan_phones' => ['081222222222'],
            'services.telegram.owner_ids' => ['11111111'],
            'services.telegram.akuntan_ids' => ['22222222'],
        ]);

        $this->assetAccount = Account::create([
            'code' => '1001',
            'name' => 'Kas Toko',
            'type' => 'asset',
        ]);

        Account::create([
            'code' => '4001',
            'name' => 'Pendapatan Penjualan Retail',
            'type' => 'revenue',
        ]);

        Account::create([
            'code' => '4002',
            'name' => 'Pendapatan Custom Jas',
            'type' => 'revenue',
        ]);

        $this->ownerEmployee = Employee::create([
            'name' => 'Pak Owner',
            'position' => 'Owner',
            'role' => Employee::ROLE_OWNER,
            'phone' => '081211111111',
            'base_salary' => 15000000,
            'current_points' => 0,
            'rate_per_point' => 50000,
            'pay_day' => 25,
            'asset_account_id' => $this->assetAccount->id,
            'status' => 'active',
        ]);

        $this->akuntanEmployee = Employee::create([
            'name' => 'Siti Akuntan',
            'position' => 'Akuntan Keuangan',
            'role' => Employee::ROLE_AKUNTAN,
            'phone' => '081222222222',
            'base_salary' => 7000000,
            'current_points' => 0,
            'rate_per_point' => 50000,
            'pay_day' => 25,
            'asset_account_id' => $this->assetAccount->id,
            'status' => 'active',
        ]);

        $this->csEmployee = Employee::create([
            'name' => 'Rina CS',
            'position' => 'Customer Service',
            'role' => Employee::ROLE_CS,
            'phone' => '081233333333',
            'base_salary' => 4000000,
            'current_points' => 0,
            'rate_per_point' => 25000,
            'pay_day' => 25,
            'asset_account_id' => $this->assetAccount->id,
            'status' => 'active',
        ]);
    }

    public function test_cs_cannot_access_financial_balance_or_summary_or_payroll_due(): void
    {
        $headers = [
            'X-Webhook-Secret' => 'test_secret_key',
            'X-Telegram-Phone' => $this->csEmployee->phone,
        ];

        // 1. Balance endpoint
        $response = $this->getJson('/api/webhook/balance', $headers);
        $response->assertStatus(403)
            ->assertJson([
                'status' => false,
                'role' => 'cs',
            ]);

        // 2. Summary endpoint
        $response = $this->getJson('/api/webhook/summary', $headers);
        $response->assertStatus(403)
            ->assertJson([
                'status' => false,
                'role' => 'cs',
            ]);

        // 3. Due payroll endpoint
        $response = $this->getJson('/api/webhook/payroll/due', $headers);
        $response->assertStatus(403)
            ->assertJson([
                'status' => false,
                'role' => 'cs',
            ]);
    }

    public function test_owner_and_akuntan_can_access_balance_and_summary(): void
    {
        $ownerHeaders = [
            'X-Webhook-Secret' => 'test_secret_key',
            'X-Telegram-Phone' => $this->ownerEmployee->phone,
        ];

        $akuntanHeaders = [
            'X-Webhook-Secret' => 'test_secret_key',
            'X-Telegram-Phone' => $this->akuntanEmployee->phone,
        ];

        $this->getJson('/api/webhook/balance', $ownerHeaders)->assertStatus(200);
        $this->getJson('/api/webhook/balance', $akuntanHeaders)->assertStatus(200);

        $this->getJson('/api/webhook/summary', $ownerHeaders)->assertStatus(200);
        $this->getJson('/api/webhook/summary', $akuntanHeaders)->assertStatus(200);
    }

    public function test_cs_cannot_manually_update_points(): void
    {
        $payload = [
            'employee_id' => $this->csEmployee->id,
            'points' => 20,
            'operation' => 'add',
            'sender_phone' => $this->csEmployee->phone,
        ];

        $response = $this->postJson('/api/webhook/payroll/points', $payload, [
            'X-Webhook-Secret' => 'test_secret_key',
        ]);

        $response->assertStatus(403);
        $this->assertEquals(0, $this->csEmployee->fresh()->current_points);
    }

    public function test_owner_can_award_points_with_category_review_or_cross_company(): void
    {
        $payload = [
            'employee_id' => $this->csEmployee->id,
            'points' => 15,
            'operation' => 'add',
            'category' => EmployeePointLog::CATEGORY_REVIEW,
            'notes' => 'Review bintang 5 dari pelanggan di Google Maps',
            'sender_phone' => $this->ownerEmployee->phone,
        ];

        $response = $this->postJson('/api/webhook/payroll/points', $payload, [
            'X-Webhook-Secret' => 'test_secret_key',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
            ]);

        $this->assertEquals(15, $this->csEmployee->fresh()->current_points);

        $this->assertDatabaseHas('employee_point_logs', [
            'employee_id' => $this->csEmployee->id,
            'points' => 15,
            'category' => EmployeePointLog::CATEGORY_REVIEW,
            'notes' => 'Review bintang 5 dari pelanggan di Google Maps',
            'actor' => 'Pak Owner (owner)',
        ]);
    }

    public function test_my_points_endpoint_returns_monthly_point_breakdown_for_cs(): void
    {
        // Add sample logs
        $this->csEmployee->addPoints(15, EmployeePointLog::CATEGORY_ITEM_SALE, 'Penjualan Jas');
        $this->csEmployee->addPoints(10, EmployeePointLog::CATEGORY_COD, 'Layanan COD');
        $this->csEmployee->addPoints(5, EmployeePointLog::CATEGORY_REVIEW, 'Review Bintang 5');

        $response = $this->getJson('/api/webhook/payroll/my-points', [
            'X-Webhook-Secret' => 'test_secret_key',
            'X-Telegram-Phone' => $this->csEmployee->phone,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'current_points' => 30,
                'employee' => [
                    'name' => 'Rina CS',
                ],
                'category_summary' => [
                    'Penjualan Item' => 15,
                    'Layanan Cash On Delivery (COD)' => 10,
                    'Review Bagus Pelanggan' => 5,
                ],
            ]);
    }

    public function test_list_employee_points_masks_salary_for_cs(): void
    {
        $response = $this->getJson('/api/webhook/payroll/points', [
            'X-Webhook-Secret' => 'test_secret_key',
            'X-Telegram-Phone' => $this->csEmployee->phone,
        ]);

        $response->assertStatus(200);

        // Response should not leak formatted_total_salary or formatted_bonus_salary when accessed by CS
        $employees = $response->json('employees');
        $this->assertNotEmpty($employees);

        foreach ($employees as $emp) {
            $this->assertArrayNotHasKey('formatted_total_salary', $emp);
            $this->assertArrayNotHasKey('formatted_bonus_salary', $emp);
        }
    }

    public function test_web_ui_can_store_and_update_employee_with_role(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/employees', [
            'name' => 'Dimas CS Baru',
            'role' => Employee::ROLE_CS,
            'position' => 'Customer Care',
            'phone' => '08987654321',
            'base_salary' => 4500000,
            'current_points' => 0,
            'pay_day' => 25,
            'asset_account_id' => $this->assetAccount->id,
            'status' => 'active',
        ]);

        $response->assertRedirect('/employees');
        $this->assertDatabaseHas('employees', [
            'name' => 'Dimas CS Baru',
            'role' => Employee::ROLE_CS,
            'phone' => '08987654321',
        ]);

        $emp = Employee::where('phone', '08987654321')->first();

        // Update
        $updateResponse = $this->actingAs($user)->put("/employees/{$emp->id}", [
            'name' => 'Dimas CS Senior',
            'role' => Employee::ROLE_CS,
            'position' => 'Senior Customer Care',
            'phone' => '08987654321',
            'base_salary' => 5000000,
            'current_points' => 10,
            'pay_day' => 25,
            'asset_account_id' => $this->assetAccount->id,
            'status' => 'active',
        ]);

        $updateResponse->assertRedirect('/employees');
        $this->assertDatabaseHas('employees', [
            'id' => $emp->id,
            'name' => 'Dimas CS Senior',
            'position' => 'Senior Customer Care',
            'base_salary' => 5000000,
        ]);
    }

    public function test_employee_role_enum_hierarchy_and_levels(): void
    {
        $this->assertTrue(EmployeeRole::Owner->isAboveStaff());
        $this->assertTrue(EmployeeRole::Manager->isAboveStaff());
        $this->assertTrue(EmployeeRole::Akuntan->isAboveStaff());
        $this->assertTrue(EmployeeRole::Hrd->isAboveStaff());
        $this->assertTrue(EmployeeRole::Supervisor->isAboveStaff());

        $this->assertTrue(EmployeeRole::Staff->isStaff());
        $this->assertTrue(EmployeeRole::Cs->isStaff());

        $this->assertFalse(EmployeeRole::Staff->isAboveStaff());
        $this->assertFalse(EmployeeRole::Manager->isStaff());

        $this->assertEquals('Di Atas Staff (Manajemen)', EmployeeRole::Manager->levelLabel());
        $this->assertEquals('Tingkat Staff (Pelaksana)', EmployeeRole::Staff->levelLabel());
    }

    public function test_can_create_and_update_employees_with_above_staff_and_staff_roles(): void
    {
        $user = User::factory()->create();

        // 1. Buat Karyawan dengan Role di atas staff (Manager)
        $respManager = $this->actingAs($user)->post('/employees', [
            'name' => 'Bambang Manager Toko',
            'role' => EmployeeRole::Manager->value,
            'position' => 'Store Manager',
            'phone' => '0812334455',
            'base_salary' => 9000000,
            'current_points' => 0,
            'pay_day' => 25,
            'asset_account_id' => $this->assetAccount->id,
            'status' => 'active',
        ]);

        $respManager->assertRedirect('/employees');
        $this->assertDatabaseHas('employees', [
            'name' => 'Bambang Manager Toko',
            'role' => 'manager',
        ]);

        $manager = Employee::where('name', 'Bambang Manager Toko')->first();
        $this->assertTrue($manager->isManager());
        $this->assertTrue($manager->isAboveStaff());
        $this->assertFalse($manager->isStaff());
        $this->assertEquals('Manager / Pengelola', $manager->role_label);

        // 2. Buat Karyawan dengan Role di atas staff (HRD)
        $respHrd = $this->actingAs($user)->post('/employees', [
            'name' => 'Nadia HRD',
            'role' => EmployeeRole::Hrd->value,
            'position' => 'HR Specialist',
            'phone' => '0812334466',
            'base_salary' => 7500000,
            'current_points' => 0,
            'pay_day' => 25,
            'asset_account_id' => $this->assetAccount->id,
            'status' => 'active',
        ]);

        $respHrd->assertRedirect('/employees');
        $this->assertDatabaseHas('employees', [
            'name' => 'Nadia HRD',
            'role' => 'hrd',
        ]);

        $hrd = Employee::where('name', 'Nadia HRD')->first();
        $this->assertTrue($hrd->isHrd());
        $this->assertTrue($hrd->isAboveStaff());

        // 3. Buat Karyawan dengan Role staff umum
        $respStaff = $this->actingAs($user)->post('/employees', [
            'name' => 'Eko Staff Gudang',
            'role' => EmployeeRole::Staff->value,
            'position' => 'Staff Gudang',
            'phone' => '0812334477',
            'base_salary' => 4000000,
            'current_points' => 0,
            'pay_day' => 25,
            'asset_account_id' => $this->assetAccount->id,
            'status' => 'active',
        ]);

        $respStaff->assertRedirect('/employees');
        $this->assertDatabaseHas('employees', [
            'name' => 'Eko Staff Gudang',
            'role' => 'staff',
        ]);

        $staff = Employee::where('name', 'Eko Staff Gudang')->first();
        $this->assertTrue($staff->isStaff());
        $this->assertFalse($staff->isAboveStaff());
        $this->assertEquals('Staff Umum', $staff->role_label);

        // 4. Update dari Staff ke Supervisor (Promosi ke atas staff)
        $respPromote = $this->actingAs($user)->put("/employees/{$staff->id}", [
            'name' => 'Eko Supervisor Gudang',
            'role' => EmployeeRole::Supervisor->value,
            'position' => 'Warehouse Supervisor',
            'phone' => '0812334477',
            'base_salary' => 6000000,
            'current_points' => 0,
            'pay_day' => 25,
            'asset_account_id' => $this->assetAccount->id,
            'status' => 'active',
        ]);

        $respPromote->assertRedirect('/employees');
        $this->assertTrue($staff->fresh()->isSupervisor());
        $this->assertTrue($staff->fresh()->isAboveStaff());
    }

    public function test_cannot_create_employee_with_invalid_role(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/employees', [
            'name' => 'Hacker Account',
            'role' => 'superadmin_non_existent',
            'position' => 'Test',
            'base_salary' => 1000000,
            'pay_day' => 25,
            'status' => 'active',
        ]);

        $response->assertSessionHasErrors(['role']);
    }

    public function test_web_interface_displays_grouped_roles_and_badges(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/employees');

        $response->assertOk();
        $response->assertSee('Di Atas Staff');
        $response->assertSee('Tingkat Staff');
        $response->assertSee('Manager / Pengelola');
        $response->assertSee('HRD / Personalia');
        $response->assertSee('Supervisor / Pengawas');
        $response->assertSee('Staff Umum');
    }

    public function test_employee_find_by_phone_with_various_formats(): void
    {
        // $this->csEmployee phone is '081233333333'
        $this->assertNotNull(Employee::findByPhone('081233333333'));
        $this->assertNotNull(Employee::findByPhone('+6281233333333'));
        $this->assertNotNull(Employee::findByPhone('6281233333333'));
        $this->assertNotNull(Employee::findByPhone('0812-3333-3333'));
        $this->assertNotNull(Employee::findByPhone('0812 3333 3333'));

        $this->assertEquals($this->csEmployee->id, Employee::findByPhone('+6281233333333')->id);
        $this->assertNull(Employee::findByPhone('089999999999'));
    }

    public function test_telegram_authorization_via_mobile_phone_number(): void
    {
        config(['services.telegram.owner_phones' => ['081211111111']]);
        config(['services.telegram.akuntan_phones' => ['081222222222']]);

        // 1. Owner can access payroll report using sender_phone (+62 format)
        $ownerResp = $this->withHeader('X-Webhook-Secret', 'test_secret_key')
            ->postJson('/api/webhook/payroll/due', [
                'sender_phone' => '+6281211111111',
            ]);
        $ownerResp->assertOk();

        // 2. Akuntan can access payroll report using header X-Telegram-Phone
        $akuntanResp = $this->withHeaders([
            'X-Webhook-Secret' => 'test_secret_key',
            'X-Telegram-Phone' => '081222222222',
        ])->postJson('/api/webhook/payroll/due');
        $akuntanResp->assertOk();

        // 3. CS (Rina CS: 081233333333) is rejected from accessing payroll report
        $csResp = $this->withHeader('X-Webhook-Secret', 'test_secret_key')
            ->postJson('/api/webhook/payroll/due', [
                'sender_phone' => '081233333333',
            ]);
        $csResp->assertStatus(403);
        $csResp->assertJsonPath('role', Employee::ROLE_CS);
    }

    public function test_cs_can_check_personal_points_via_mobile_phone_number(): void
    {
        $this->csEmployee->addPoints(50, EmployeePointLog::CATEGORY_ITEM_SALE, 'Penjualan Jas', 'retail', 1, 'Test');

        $response = $this->withHeader('X-Webhook-Secret', 'test_secret_key')
            ->postJson('/api/webhook/payroll/my-points', [
                'sender_phone' => '+6281233333333',
            ]);

        $response->assertOk();
        $response->assertJsonPath('status', true);
        $response->assertJsonPath('employee.name', 'Rina CS');
        $response->assertJsonPath('current_points', 50);
    }
}
