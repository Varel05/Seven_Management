<?php

namespace Tests\Feature;

use App\Enums\EmployeeRole;
use App\Models\Account;
use App\Models\Allowance;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeAllowanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.webhook.secret' => 'test_secret_key']);

        Account::firstOrCreate(
            ['code' => '1001'],
            ['name' => 'Kas Operasional', 'type' => 'asset']
        );

        Account::firstOrCreate(
            ['code' => '5002'],
            ['name' => 'Beban Gaji', 'type' => 'expense']
        );
    }

    public function test_user_can_create_update_toggle_and_delete_allowances(): void
    {
        $user = User::factory()->create();

        // 1. Create Allowance for all employees
        $response = $this->actingAs($user)->post('/allowances', [
            'name' => 'Tunjangan Makan',
            'target_type' => Allowance::TARGET_ALL,
            'amount' => 250000,
            'notes' => 'Uang makan bulanan staf',
            'is_active' => 1,
        ]);

        $response->assertRedirect(route('employees.index'));
        $this->assertDatabaseHas('allowances', [
            'name' => 'Tunjangan Makan',
            'target_type' => 'all',
            'amount' => 250000,
            'is_active' => true,
        ]);

        $allowance = Allowance::where('name', 'Tunjangan Makan')->first();

        // 2. Update Allowance
        $response = $this->actingAs($user)->put("/allowances/{$allowance->id}", [
            'name' => 'Tunjangan Makan & Minum',
            'target_type' => Allowance::TARGET_ALL,
            'amount' => 300000,
            'notes' => 'Uang makan disesuaikan',
            'is_active' => 1,
        ]);

        $response->assertRedirect(route('employees.index'));
        $this->assertDatabaseHas('allowances', [
            'id' => $allowance->id,
            'name' => 'Tunjangan Makan & Minum',
            'amount' => 300000,
        ]);

        // 3. Toggle Allowance
        $response = $this->actingAs($user)->patch("/allowances/{$allowance->id}/toggle");
        $response->assertRedirect(route('employees.index'));
        $this->assertFalse($allowance->fresh()->is_active);

        // 4. Delete Allowance
        $response = $this->actingAs($user)->delete("/allowances/{$allowance->id}");
        $response->assertRedirect(route('employees.index'));
        $this->assertDatabaseMissing('allowances', ['id' => $allowance->id]);
    }

    public function test_allowance_calculation_respects_target_types_and_status(): void
    {
        $csEmployee = Employee::create([
            'name' => 'Dini CS',
            'phone' => '081234567890',
            'role' => EmployeeRole::Cs,
            'position' => 'Customer Service',
            'base_salary' => 2500000,
            'current_points' => 200, // Tier 1 -> rate 1000 -> bonus 200.000
            'rate_per_point' => 1000,
            'pay_day' => 25,
            'status' => 'active',
        ]);

        $staffEmployee = Employee::create([
            'name' => 'Joko Gudang',
            'phone' => '087777777777',
            'role' => EmployeeRole::Staff,
            'position' => 'Staff Gudang',
            'base_salary' => 2800000,
            'current_points' => 0,
            'rate_per_point' => 0,
            'pay_day' => 25,
            'status' => 'active',
        ]);

        // Tunjangan 1: Berlaku untuk SEMUA (Rp 100.000)
        Allowance::create([
            'name' => 'Tunjangan Kehadiran',
            'target_type' => Allowance::TARGET_ALL,
            'amount' => 100000,
            'is_active' => true,
        ]);

        // Tunjangan 2: Khusus Divisi CS (Rp 150.000)
        Allowance::create([
            'name' => 'Tunjangan Komunikasi Pulsa',
            'target_type' => Allowance::TARGET_ROLE,
            'target_role' => EmployeeRole::Cs->value,
            'amount' => 150000,
            'is_active' => true,
        ]);

        // Tunjangan 3: Khusus Dini CS perorangan (Rp 200.000)
        Allowance::create([
            'name' => 'Tunjangan Senioritas',
            'target_type' => Allowance::TARGET_EMPLOYEE,
            'employee_id' => $csEmployee->id,
            'amount' => 200000,
            'is_active' => true,
        ]);

        // Tunjangan 4: Nonaktif (Rp 500.000) -> tidak boleh dihitung
        Allowance::create([
            'name' => 'Tunjangan Proyek Khusus (Selesai)',
            'target_type' => Allowance::TARGET_ALL,
            'amount' => 500000,
            'is_active' => false,
        ]);

        // Verifikasi Dini CS:
        // Base: 2.500.000
        // Tunjangan: 100.000 + 150.000 + 200.000 = 450.000
        // Bonus Poin: 200 * 1000 = 200.000
        // Total Gaji: 2.500.000 + 450.000 + 200.000 = 3.150.000
        $this->assertEquals(450000, $csEmployee->total_allowance);
        $this->assertEquals(3150000, $csEmployee->total_salary);
        $this->assertCount(3, $csEmployee->applicable_allowances);

        // Verifikasi Joko Gudang (Staff):
        // Base: 2.800.000
        // Tunjangan: 100.000 (hanya target 'all')
        // Bonus: 0
        // Total Gaji: 2.800.000 + 100.000 = 2.900.000
        $this->assertEquals(100000, $staffEmployee->total_allowance);
        $this->assertEquals(2900000, $staffEmployee->total_salary);
        $this->assertCount(1, $staffEmployee->applicable_allowances);
    }

    public function test_payroll_posting_includes_allowances_in_journal_and_total(): void
    {
        $employee = Employee::create([
            'name' => 'Hendra Penjahit',
            'phone' => '089999888877',
            'role' => EmployeeRole::Staff,
            'position' => 'Master Tailor',
            'base_salary' => 3500000,
            'pay_day' => 28,
            'status' => 'active',
        ]);

        Allowance::create([
            'name' => 'Tunjangan Alat Jahit',
            'target_type' => Allowance::TARGET_ROLE,
            'target_role' => EmployeeRole::Staff->value,
            'amount' => 250000,
            'is_active' => true,
        ]);

        Allowance::create([
            'name' => 'Tunjangan Transportasi',
            'target_type' => Allowance::TARGET_ALL,
            'amount' => 150000,
            'is_active' => true,
        ]);

        // Total Gaji: 3.500.000 + 400.000 = 3.900.000
        $this->assertEquals(400000, $employee->total_allowance);
        $this->assertEquals(3900000, $employee->total_salary);

        // Eksekusi pembukuan payroll
        $journalEntry = $employee->executePayrollPosting();

        $this->assertNotNull($journalEntry);
        $this->assertDatabaseHas('journal_entries', ['id' => $journalEntry->id]);

        // Cek baris jurnal beban gaji
        $expenseAccount = Account::where('code', '5002')->first();
        $this->assertDatabaseHas('journal_entry_lines', [
            'journal_entry_id' => $journalEntry->id,
            'account_id' => $expenseAccount->id,
            'debit' => 3900000,
            'credit' => 0,
        ]);

        $line = $journalEntry->lines()->where('account_id', $expenseAccount->id)->first();
        $this->assertStringContainsString('Tunjangan: Rp 400.000', $line->description);
        $this->assertStringContainsString('Tunjangan Alat Jahit', $line->description);
    }

    public function test_webhook_due_payroll_returns_allowance_details(): void
    {
        $employee = Employee::create([
            'name' => 'Budi Akuntan',
            'phone' => '083896693316',
            'role' => EmployeeRole::Akuntan,
            'position' => 'Akuntan Perusahaan',
            'base_salary' => 4000000,
            'pay_day' => now()->day, // Jatuh tempo hari ini
            'status' => 'active',
        ]);

        Allowance::create([
            'name' => 'Tunjangan Jabatan',
            'target_type' => Allowance::TARGET_EMPLOYEE,
            'employee_id' => $employee->id,
            'amount' => 500000,
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/webhook/payroll/due', [
            'X-Webhook-Secret' => 'test_secret_key',
            'X-Telegram-Phone' => $employee->phone,
        ]);

        $response->assertOk();
        $response->assertJsonPath('status', true);

        $dueToday = $response->json('due_today');
        $this->assertNotEmpty($dueToday);

        $empItem = collect($dueToday)->firstWhere('id', $employee->id);
        $this->assertNotNull($empItem);
        $this->assertEquals(500000, $empItem['total_allowance']);
        $this->assertEquals('Rp 500.000', $empItem['formatted_allowance']);
        $this->assertEquals(4500000, $empItem['total_salary']);

        $message = $response->json('message');
        $this->assertStringContainsString('Tunjangan: Rp 500.000', $message);
    }

    public function test_allowance_can_target_multiple_employees(): void
    {
        $user = User::factory()->create();

        $emp1 = Employee::create([
            'name' => 'Karyawan Satu',
            'phone' => '08111111111',
            'role' => EmployeeRole::Staff,
            'position' => 'Staff 1',
            'base_salary' => 2000000,
            'pay_day' => 25,
            'status' => 'active',
        ]);

        $emp2 = Employee::create([
            'name' => 'Karyawan Dua',
            'phone' => '08222222222',
            'role' => EmployeeRole::Staff,
            'position' => 'Staff 2',
            'base_salary' => 2000000,
            'pay_day' => 25,
            'status' => 'active',
        ]);

        $emp3 = Employee::create([
            'name' => 'Karyawan Tiga',
            'phone' => '08333333333',
            'role' => EmployeeRole::Staff,
            'position' => 'Staff 3',
            'base_salary' => 2000000,
            'pay_day' => 25,
            'status' => 'active',
        ]);

        // Simpan tunjangan untuk Karyawan 1 dan Karyawan 2
        $response = $this->actingAs($user)->post('/allowances', [
            'name' => 'Tunjangan Shift Khusus',
            'target_type' => Allowance::TARGET_EMPLOYEE,
            'target_employee_ids' => [$emp1->id, $emp2->id],
            'amount' => 175000,
            'is_active' => 1,
        ]);

        $response->assertRedirect(route('employees.index'));

        $allowance = Allowance::where('name', 'Tunjangan Shift Khusus')->first();
        $this->assertNotNull($allowance);
        $this->assertEquals([$emp1->id, $emp2->id], $allowance->target_employee_ids);

        // Cek bahwa emp1 dan emp2 dapat, sedangkan emp3 tidak
        $this->assertTrue($allowance->appliesTo($emp1));
        $this->assertTrue($allowance->appliesTo($emp2));
        $this->assertFalse($allowance->appliesTo($emp3));

        $this->assertEquals(175000, $emp1->fresh()->total_allowance);
        $this->assertEquals(175000, $emp2->fresh()->total_allowance);
        $this->assertEquals(0, $emp3->fresh()->total_allowance);

        $this->assertStringContainsString('Karyawan Satu', $allowance->target_label);
        $this->assertStringContainsString('Karyawan Dua', $allowance->target_label);

        // Update menjadi ketiga karyawan
        $this->actingAs($user)->put("/allowances/{$allowance->id}", [
            'name' => 'Tunjangan Shift Khusus',
            'target_type' => Allowance::TARGET_EMPLOYEE,
            'target_employee_ids' => [$emp1->id, $emp2->id, $emp3->id],
            'amount' => 200000,
            'is_active' => 1,
        ]);

        $allowance->refresh();
        $this->assertTrue($allowance->appliesTo($emp3));
        $this->assertEquals(200000, $emp3->fresh()->total_allowance);
        $this->assertStringContainsString('3 orang', $allowance->target_label);
    }
}
