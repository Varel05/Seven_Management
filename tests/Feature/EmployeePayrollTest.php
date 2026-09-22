<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Employee;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeePayrollTest extends TestCase
{
    use RefreshDatabase;

    protected Account $assetAccount;
    protected Account $salaryExpenseAccount;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.webhook.secret' => 'test_secret_key']);

        $this->assetAccount = Account::create([
            'code' => '1002',
            'name' => 'Bank BCA',
            'type' => 'asset',
        ]);

        $this->salaryExpenseAccount = Account::create([
            'code' => '5002',
            'name' => 'Beban Gaji',
            'type' => 'expense',
        ]);
    }

    public function test_user_can_view_employees_page(): void
    {
        $user = User::factory()->create();

        Employee::create([
            'name'             => 'Budi Santoso',
            'position'         => 'Senior Developer',
            'base_salary'      => 8000000,
            'current_points'   => 10,
            'rate_per_point'   => 50000,
            'pay_day'          => 25,
            'asset_account_id' => $this->assetAccount->id,
            'status'           => 'active',
        ]);

        $response = $this->actingAs($user)->get('/employees');

        $response->assertStatus(200);
        $response->assertSee('Budi Santoso');
        $response->assertSee('Senior Developer');
        $response->assertSee('Rp 8.000.000');
        $response->assertSee('Batas:');
        $response->assertSee('Cari staf, jabatan, akun...');
        $response->assertSee('Lunas Bulan Ini');
        $response->assertSee('data karyawan');
    }

    public function test_user_can_create_employee(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/employees', [
            'name'             => 'Siti Aminah',
            'position'         => 'Finance Officer',
            'phone'            => '08123456789',
            'base_salary'      => 6000000,
            'current_points'   => 5,
            'rate_per_point'   => 100000,
            'pay_day'          => 28,
            'asset_account_id' => $this->assetAccount->id,
            'status'           => 'active',
        ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('employees', [
            'name'        => 'Siti Aminah',
            'position'    => 'Finance Officer',
            'base_salary' => 6000000,
            'pay_day'     => 28,
        ]);
    }

    public function test_user_can_update_employee_details(): void
    {
        $user = User::factory()->create();

        $employee = Employee::create([
            'name'             => 'Asep Sunandar',
            'position'         => 'Staff Gudang',
            'base_salary'      => 4500000,
            'current_points'   => 0,
            'rate_per_point'   => 25000,
            'pay_day'          => 20,
            'asset_account_id' => $this->assetAccount->id,
            'status'           => 'active',
        ]);

        $response = $this->actingAs($user)->put("/employees/{$employee->id}", [
            'name'             => 'Asep Sunandar Pratama',
            'position'         => 'Kepala Gudang',
            'base_salary'      => 5500000,
            'current_points'   => 4,
            'rate_per_point'   => 30000,
            'pay_day'          => 20,
            'asset_account_id' => $this->assetAccount->id,
            'status'           => 'active',
        ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('employees', [
            'id'          => $employee->id,
            'name'        => 'Asep Sunandar Pratama',
            'position'    => 'Kepala Gudang',
            'base_salary' => 5500000,
        ]);
    }

    public function test_user_can_update_employee_points(): void
    {
        $user = User::factory()->create();

        $employee = Employee::create([
            'name'             => 'Rian Hidayat',
            'position'         => 'Marketing',
            'base_salary'      => 5000000,
            'current_points'   => 5,
            'rate_per_point'   => 50000,
            'pay_day'          => 25,
            'asset_account_id' => $this->assetAccount->id,
            'status'           => 'active',
        ]);

        // Add 3 points
        $response = $this->actingAs($user)->patch("/employees/{$employee->id}/points", [
            'points' => 3,
            'mode'   => 'add',
        ]);

        $response->assertSessionHas('success');
        $this->assertEquals(8, $employee->fresh()->current_points);
        // Bonus should now be 8 * 50,000 = 400,000; Total = 5,400,000
        $this->assertEquals(400000, $employee->fresh()->bonus_salary);
        $this->assertEquals(5400000, $employee->fresh()->total_salary);
    }

    public function test_user_can_pay_salary_from_web_dashboard(): void
    {
        $user = User::factory()->create();

        $employee = Employee::create([
            'name'             => 'Dewi Lestari',
            'position'         => 'Designer',
            'base_salary'      => 6000000,
            'current_points'   => 4,
            'rate_per_point'   => 100000,
            'pay_day'          => 25,
            'asset_account_id' => $this->assetAccount->id,
            'status'           => 'active',
        ]);

        // Total salary = 6,000,000 + 400,000 = 6,400,000
        $response = $this->actingAs($user)->post("/employees/{$employee->id}/pay");

        $response->assertSessionHas('success');
        $this->assertNotNull($employee->fresh()->last_paid_at);

        // Check Journal Entry
        $journal = JournalEntry::where('source', 'web_payroll')->latest()->first();
        $this->assertNotNull($journal);
        $this->assertStringContainsString('Dewi Lestari', $journal->description);

        // Check Debit 5002 (Beban Gaji)
        $this->assertDatabaseHas('journal_entry_lines', [
            'journal_entry_id' => $journal->id,
            'account_id'       => $this->salaryExpenseAccount->id,
            'debit'            => 6400000,
            'credit'           => 0,
        ]);

        // Check Credit Asset Account
        $this->assertDatabaseHas('journal_entry_lines', [
            'journal_entry_id' => $journal->id,
            'account_id'       => $this->assetAccount->id,
            'debit'            => 0,
            'credit'           => 6400000,
        ]);
    }

    public function test_due_recurring_api_includes_employee_payroll(): void
    {
        Employee::create([
            'name'             => 'Hendra Wijaya',
            'position'         => 'Backend Engineer',
            'base_salary'      => 7500000,
            'current_points'   => 10,
            'rate_per_point'   => 50000,
            'pay_day'          => (int) now()->day, // due today
            'asset_account_id' => $this->assetAccount->id,
            'status'           => 'active',
        ]);

        $response = $this->withHeaders([
            'X-Webhook-Secret' => 'test_secret_key',
        ])->getJson('/api/webhook/recurring/due');

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
            ])
            ->assertJsonPath('payroll.due_today.0.name', 'Hendra Wijaya');

        $data = $response->json();
        $this->assertStringContainsString('Hendra Wijaya', $data['message']);
        $this->assertStringContainsString('5002', $data['message']);
    }

    public function test_manual_action_can_pay_employee_salary(): void
    {
        $employee = Employee::create([
            'name'             => 'Bambang Pamungkas',
            'position'         => 'Operations Lead',
            'base_salary'      => 9000000,
            'current_points'   => 2,
            'rate_per_point'   => 250000,
            'pay_day'          => 25,
            'asset_account_id' => $this->assetAccount->id,
            'status'           => 'active',
        ]);

        // Total = 9,000,000 + 500,000 = 9,500,000
        $response = $this->withHeaders([
            'X-Webhook-Secret' => 'test_secret_key',
        ])->postJson('/api/webhook/recurring/manual-action', [
            'query'  => 'gaji bambang',
            'action' => 'approve',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'status' => true,
                'data'   => [
                    'name'   => 'Bambang Pamungkas',
                    'amount' => 9500000,
                ],
            ]);

        $this->assertNotNull($employee->fresh()->last_paid_at);

        // Check Debit Account 5002 (Beban Gaji)
        $this->assertDatabaseHas('journal_entry_lines', [
            'account_id' => $this->salaryExpenseAccount->id,
            'debit'      => 9500000,
            'credit'     => 0,
        ]);
    }

    public function test_manual_action_can_pay_employee_by_name_fallback(): void
    {
        $employee = Employee::create([
            'name'             => 'Chandra Darusman',
            'position'         => 'Content Creator',
            'base_salary'      => 4000000,
            'current_points'   => 0,
            'rate_per_point'   => 50000,
            'pay_day'          => 25,
            'asset_account_id' => $this->assetAccount->id,
            'status'           => 'active',
        ]);

        // When query is just the name without 'gaji' prefix, e.g. /bayar chandra
        $response = $this->withHeaders([
            'X-Webhook-Secret' => 'test_secret_key',
        ])->postJson('/api/webhook/recurring/manual-action', [
            'query'  => 'chandra',
            'action' => 'approve',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'status' => true,
                'data'   => [
                    'name'   => 'Chandra Darusman',
                    'amount' => 4000000,
                ],
            ]);

        $this->assertNotNull($employee->fresh()->last_paid_at);
    }

    public function test_approve_payroll_webhook_endpoint(): void
    {
        $employee = Employee::create([
            'name'             => 'Fitriani',
            'position'         => 'Customer Support',
            'base_salary'      => 3500000,
            'current_points'   => 6,
            'rate_per_point'   => 50000,
            'pay_day'          => 25,
            'asset_account_id' => $this->assetAccount->id,
            'status'           => 'active',
        ]);

        // Total = 3,500,000 + 300,000 = 3,800,000
        $response = $this->withHeaders([
            'X-Webhook-Secret' => 'test_secret_key',
        ])->postJson("/api/webhook/payroll/{$employee->id}/approve");

        $response->assertStatus(201)
            ->assertJson([
                'status' => true,
                'data'   => [
                    'name'         => 'Fitriani',
                    'amount'       => 3800000,
                    'expense_code' => '5002',
                ],
            ]);

        $this->assertNotNull($employee->fresh()->last_paid_at);
    }

    public function test_skip_payroll_webhook_endpoint(): void
    {
        $employee = Employee::create([
            'name'             => 'Gani',
            'position'         => 'Intern',
            'base_salary'      => 2000000,
            'current_points'   => 0,
            'rate_per_point'   => 0,
            'pay_day'          => 25,
            'asset_account_id' => $this->assetAccount->id,
            'status'           => 'active',
        ]);

        $response = $this->withHeaders([
            'X-Webhook-Secret' => 'test_secret_key',
        ])->postJson("/api/webhook/payroll/{$employee->id}/skip");

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
            ]);

        $this->assertNotNull($employee->fresh()->last_paid_at);
        // No journal entry should be created for skip
        $this->assertEquals(0, JournalEntry::count());
    }

    public function test_manual_action_with_gaji_query_displays_payroll_list_and_status(): void
    {
        Employee::create([
            'name'             => 'Doni Salman',
            'position'         => 'Staff Gudang',
            'base_salary'      => 3500000,
            'current_points'   => 10,
            'rate_per_point'   => 10000,
            'pay_day'          => 20,
            'asset_account_id' => $this->assetAccount->id,
            'status'           => 'active',
        ]);

        // Test with 'gaji'
        $response = $this->withHeaders([
            'X-Webhook-Secret' => 'test_secret_key',
        ])->postJson('/api/webhook/recurring/manual-action', [
            'query' => 'gaji',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'count'  => 1,
            ])
            ->assertJsonPath('status', true);

        $this->assertStringContainsString('Daftar Gaji Karyawan & Status', $response->json('message'));
        $this->assertStringContainsString('Doni Salman', $response->json('message'));

        // Test with '/gaji'
        $responseSlash = $this->withHeaders([
            'X-Webhook-Secret' => 'test_secret_key',
        ])->postJson('/api/webhook/recurring/manual-action', [
            'query' => '/gaji',
        ]);

        $responseSlash->assertStatus(200)
            ->assertJson([
                'status' => true,
                'count'  => 1,
            ]);

        // Test direct endpoint /api/webhook/payroll/due
        $responseDue = $this->withHeaders([
            'X-Webhook-Secret' => 'test_secret_key',
        ])->getJson('/api/webhook/payroll/due');

        $responseDue->assertStatus(200)
            ->assertJson([
                'status' => true,
                'count'  => 1,
            ]);
    }
}
