<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Tailor;
use App\Models\TailorPayroll;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TailorPayrollTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'role' => 'owner',
        ]);

        // Siapkan Akun Standar
        Account::firstOrCreate(['code' => '1001'], ['name' => 'Kas Operasional', 'type' => 'asset']);
        Account::firstOrCreate(['code' => '1002'], ['name' => 'Bank BCA', 'type' => 'asset']);
        Account::firstOrCreate(['code' => '1005'], ['name' => 'Piutang Kasbon Karyawan & Penjahit', 'type' => 'asset']);
        Account::firstOrCreate(['code' => '5002'], ['name' => 'Beban Gaji', 'type' => 'expense']);

    }

    public function test_tailor_payroll_index_page_can_be_rendered(): void
    {
        $response = $this->actingAs($this->user)->get(route('tailor-payrolls.index'));

        $response->assertStatus(200);
        $response->assertSee('Slip Upah Penjahit Borongan');
        $response->assertSee('LAB-JHT-REG');
    }

    public function test_tailor_payroll_create_page_can_be_rendered_with_hpp_materials(): void
    {
        $response = $this->actingAs($this->user)->get(route('tailor-payrolls.create'));

        $response->assertStatus(200);
        $response->assertSee('Kalkulator & Form', false);
        $response->assertSee('Jas Reguler');
        $response->assertSee('Vest');
        $response->assertSee('Jas Premium');
        $response->assertSee('Revisi');
    }

    public function test_can_create_tailor_payroll_with_hpp_rates_and_bon_deduction(): void
    {
        $payload = [
            'tailor_name' => 'Penjahit Adriana',
            'payroll_date' => '2026-10-01',
            'period_label' => 'Kamis, 1 Oktober 2026',
            'payment_method' => 'Tunai',
            'items' => [
                [
                    'material_id' => null,
                    'item_name' => 'Jas Reguler',
                    'quantity' => 10,
                    'rate_per_piece' => 50000, // Subtotal 500.000
                ],
                [
                    'material_id' => null,
                    'item_name' => 'Vest',
                    'quantity' => 5,
                    'rate_per_piece' => 37000, // Subtotal 185.000
                ],
            ],
            'advances' => [
                [
                    'advance_date' => '2026-09-28',
                    'description' => 'Kasbon jajan',
                    'amount' => 100000,
                ],
                [
                    'advance_date' => '2026-09-30',
                    'description' => 'Beli benang darurat',
                    'amount' => 50000,
                ],
            ],
        ];

        // Total Wage = 500.000 + 185.000 = 685.000
        // Total Bon = 100.000 + 50.000 = 150.000
        // Take Home Pay = 685.000 - 150.000 = 535.000
        $response = $this->actingAs($this->user)->post(route('tailor-payrolls.store'), $payload);

        $response->assertSessionHasNoErrors();
        $payroll = TailorPayroll::latest()->first();

        $this->assertNotNull($payroll);
        $this->assertEquals('Penjahit Adriana', $payroll->tailor_name);
        $this->assertEquals(15, $payroll->total_pieces);
        $this->assertEquals(685000.00, (float) $payroll->total_wage);
        $this->assertEquals(150000.00, (float) $payroll->total_bon);
        $this->assertEquals(535000.00, (float) $payroll->take_home_pay);

        $response->assertRedirect(route('tailor-payrolls.show', $payroll));

        // Pastikan Item & Kasbon tersimpan
        $this->assertCount(2, $payroll->items);
        $this->assertCount(2, $payroll->advances);
    }

    public function test_creating_tailor_payroll_automatically_generates_balanced_journal_entry(): void
    {
        $payload = [
            'tailor_name' => 'Penjahit Adriana',
            'payroll_date' => '2026-10-01',
            'period_label' => 'Kamis, 1 Oktober 2026',
            'payment_method' => 'Transfer (Bank BCA)',
            'items' => [
                [
                    'material_id' => null,
                    'item_name' => 'Jas Reguler',
                    'quantity' => 4,
                    'rate_per_piece' => 50000, // Total 200.000
                ],
            ],
            'advances' => [
                [
                    'advance_date' => '2026-09-30',
                    'description' => 'Kasbon awal',
                    'amount' => 50000,
                ],
            ],
        ];

        $this->actingAs($this->user)->post(route('tailor-payrolls.store'), $payload);

        $payroll = TailorPayroll::latest()->first();
        $this->assertNotNull($payroll->journal_entry_id);

        $journal = $payroll->journalEntry;
        $this->assertNotNull($journal);
        $this->assertEquals('verified', $journal->status);

        // Periksa keseimbangan Debit = Kredit
        $totalDebit = (float) $journal->lines->sum('debit');
        $totalCredit = (float) $journal->lines->sum('credit');

        $this->assertEquals(200000.00, $totalDebit);
        $this->assertEquals(200000.00, $totalCredit);
    }

    public function test_can_render_tailor_payroll_slip_sheet(): void
    {
        $payroll = TailorPayroll::create([
            'tailor_name' => 'Penjahit Adriana',
            'payroll_date' => '2026-10-01',
            'period_label' => 'Kamis, 1 Oktober 2026',
            'payment_method' => 'Tunai',
            'total_pieces' => 2,
            'total_wage' => 100000,
            'total_bon' => 20000,
            'take_home_pay' => 80000,
        ]);

        $payroll->items()->create([
            'item_name' => 'Jas Reguler',
            'quantity' => 2,
            'rate_per_piece' => 50000,
            'subtotal' => 100000,
        ]);

        $response = $this->actingAs($this->user)->get(route('tailor-payrolls.show', $payroll));

        $response->assertStatus(200);
        $response->assertSee('Penjahit Adriana');
        $response->assertSee('Jas Reguler');
        $response->assertSee('Take homepay');
        $response->assertSee('80.000');
    }

    public function test_can_delete_tailor_payroll_and_its_journal_entry(): void
    {
        $responsePost = $this->actingAs($this->user)->post(route('tailor-payrolls.store'), [
            'tailor_name' => 'Penjahit Budi',
            'payroll_date' => '2026-10-01',
            'payment_method' => 'Tunai',
            'items' => [
                [
                    'material_id' => null,
                    'item_name' => 'Jas Reguler',
                    'quantity' => 1,
                    'rate_per_piece' => 50000,
                ],
            ],
        ]);

        $responsePost->assertSessionHasNoErrors();
        $payroll = TailorPayroll::latest()->first();
        $journalId = $payroll->journal_entry_id;

        $response = $this->actingAs($this->user)->delete(route('tailor-payrolls.destroy', $payroll));

        $response->assertRedirect(route('tailor-payrolls.index'));
        $this->assertDatabaseMissing('tailor_payrolls', ['id' => $payroll->id]);
        if ($journalId) {
            $this->assertDatabaseMissing('journal_entries', ['id' => $journalId]);
        }
    }

    public function test_user_can_create_tailor_with_weekly_or_monthly_payment_schedule(): void
    {
        // 1. Tambah penjahit mingguan
        $responseWeekly = $this->actingAs($this->user)->post(route('tailors.store'), [
            'name' => 'Penjahit Agus',
            'phone' => '081234567899',
            'payment_period' => 'weekly',
            'payment_day' => 'Sabtu',
            'bank_name' => 'BCA',
            'bank_account_number' => '555666777',
            'bank_account_holder' => 'Agus Tailor',
            'status' => 'active',
            'notes' => 'Spesialis jas pesta',
        ]);

        $responseWeekly->assertRedirect(route('tailor-payrolls.index'));
        $responseWeekly->assertSessionHas('success');
        $this->assertDatabaseHas('tailors', [
            'name' => 'Penjahit Agus',
            'payment_period' => 'weekly',
            'payment_day' => 'Sabtu',
        ]);

        // 2. Tambah penjahit bulanan
        $responseMonthly = $this->actingAs($this->user)->post(route('tailors.store'), [
            'name' => 'Penjahit Bambang',
            'phone' => '087788990011',
            'payment_period' => 'monthly',
            'payment_day' => '25',
            'bank_name' => 'Mandiri',
            'status' => 'active',
        ]);

        $responseMonthly->assertRedirect(route('tailor-payrolls.index'));
        $this->assertDatabaseHas('tailors', [
            'name' => 'Penjahit Bambang',
            'payment_period' => 'monthly',
            'payment_day' => '25',
        ]);

        // Tampilan tabel upah penjahit harus menampilkan nama penjahit dan jadwalnya
        $view = $this->actingAs($this->user)->get(route('tailor-payrolls.index'));
        $view->assertOk();
        $view->assertSee('Penjahit Agus');
        $view->assertSee('Mingguan');
        $view->assertSee('Penjahit Bambang');
        $view->assertSee('Bulanan');
    }

    public function test_user_can_update_tailor_details(): void
    {
        $tailor = Tailor::create([
            'name' => 'Penjahit Citra',
            'phone' => '0811223344',
            'payment_period' => 'weekly',
            'payment_day' => 'Sabtu',
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->user)->put(route('tailors.update', $tailor), [
            'name' => 'Penjahit Citra Busana',
            'phone' => '0899887766',
            'payment_period' => 'monthly',
            'payment_day' => '28',
            'bank_name' => 'BRI',
            'bank_account_number' => '1122334455',
            'bank_account_holder' => 'Citra Dewi',
            'status' => 'active',
            'notes' => 'Diperbarui',
        ]);

        $response->assertRedirect(route('tailor-payrolls.index'));
        $response->assertSessionHas('success');

        $fresh = $tailor->fresh();
        $this->assertEquals('Penjahit Citra Busana', $fresh->name);
        $this->assertEquals('monthly', $fresh->payment_period);
        $this->assertEquals('28', $fresh->payment_day);
        $this->assertEquals('0899887766', $fresh->phone);
    }

    public function test_user_can_delete_tailor_without_payrolls(): void
    {
        $tailor = Tailor::create([
            'name' => 'Penjahit Baru',
            'payment_period' => 'weekly',
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->user)->delete(route('tailors.destroy', $tailor));

        $response->assertRedirect(route('tailor-payrolls.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('tailors', ['id' => $tailor->id]);
    }

    public function test_user_cannot_delete_tailor_with_payrolls(): void
    {
        $tailor = Tailor::create([
            'name' => 'Penjahit Senior',
            'payment_period' => 'weekly',
            'status' => 'active',
        ]);

        TailorPayroll::create([
            'tailor_id' => $tailor->id,
            'tailor_name' => $tailor->name,
            'payroll_date' => now()->format('Y-m-d'),
            'payment_method' => 'Tunai',
            'total_pieces' => 5,
            'total_wage' => 250000,
            'total_bon' => 0,
            'take_home_pay' => 250000,
        ]);

        $response = $this->actingAs($this->user)->delete(route('tailors.destroy', $tailor));

        $response->assertRedirect(route('tailor-payrolls.index'));
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('tailors', ['id' => $tailor->id]);
    }

    public function test_can_create_payroll_linked_to_registered_tailor(): void
    {
        $tailor = Tailor::create([
            'name' => 'Penjahit Danang',
            'phone' => '0855667788',
            'payment_period' => 'weekly',
            'payment_day' => 'Sabtu',
            'bank_name' => 'BCA',
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->user)->post(route('tailor-payrolls.store'), [
            'tailor_id' => $tailor->id,
            'tailor_name' => $tailor->name,
            'payroll_date' => '2026-10-14',
            'period_label' => 'Rabu, 14 Oktober 2026',
            'payment_method' => 'Transfer Bank BCA',
            'items' => [
                [
                    'material_id' => null,
                    'item_name' => 'Jas Premium',
                    'quantity' => 2,
                    'rate_per_piece' => 150000,
                ],
            ],
        ]);

        $response->assertSessionHasNoErrors();
        $payroll = TailorPayroll::where('tailor_id', $tailor->id)->first();
        $this->assertNotNull($payroll);
        $this->assertEquals($tailor->id, $payroll->tailor_id);
        $this->assertEquals(300000, (float) $payroll->take_home_pay);
        $this->assertEquals('Penjahit Danang', $payroll->tailor->name);
    }
}
