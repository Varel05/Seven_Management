<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Material;
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

        // Siapkan Komponen Biaya Tenaga Kerja HPP (Direct Labor)
        Material::firstOrCreate(
            ['code' => 'LAB-JHT-REG'],
            ['name' => 'Upah Penjahit Jas Reguler', 'category' => 'direct_labor', 'unit' => 'pcs', 'standard_cost' => 50000, 'stock' => 0, 'min_stock' => 0]
        );
        Material::firstOrCreate(
            ['code' => 'LAB-JHT-VST'],
            ['name' => 'Upah Penjahit Rompi / Vest', 'category' => 'direct_labor', 'unit' => 'pcs', 'standard_cost' => 37000, 'stock' => 0, 'min_stock' => 0]
        );
        Material::firstOrCreate(
            ['code' => 'LAB-JHT-PRM'],
            ['name' => 'Upah Penjahit Jas Premium', 'category' => 'direct_labor', 'unit' => 'pcs', 'standard_cost' => 150000, 'stock' => 0, 'min_stock' => 0]
        );
        Material::firstOrCreate(
            ['code' => 'LAB-JHT-REV'],
            ['name' => 'Upah Revisi / Perbaikan Jahitan', 'category' => 'direct_labor', 'unit' => 'pcs', 'standard_cost' => 0, 'stock' => 0, 'min_stock' => 0]
        );
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
        $reguler = Material::where('code', 'LAB-JHT-REG')->first();
        $vest = Material::where('code', 'LAB-JHT-VST')->first();

        $payload = [
            'tailor_name' => 'Penjahit Adriana',
            'payroll_date' => '2026-10-01',
            'period_label' => 'Kamis, 1 Oktober 2026',
            'payment_method' => 'Tunai',
            'items' => [
                [
                    'material_id' => $reguler->id,
                    'item_name' => 'Jas Reguler',
                    'quantity' => 10,
                    'rate_per_piece' => 50000, // Subtotal 500.000
                ],
                [
                    'material_id' => $vest->id,
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
        $reguler = Material::where('code', 'LAB-JHT-REG')->first();

        $payload = [
            'tailor_name' => 'Penjahit Adriana',
            'payroll_date' => '2026-10-01',
            'period_label' => 'Kamis, 1 Oktober 2026',
            'payment_method' => 'Transfer (Bank BCA)',
            'items' => [
                [
                    'material_id' => $reguler->id,
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
        $reguler = Material::where('code', 'LAB-JHT-REG')->first();

        $responsePost = $this->actingAs($this->user)->post(route('tailor-payrolls.store'), [
            'tailor_name' => 'Penjahit Budi',
            'payroll_date' => '2026-10-01',
            'payment_method' => 'Tunai',
            'items' => [
                [
                    'material_id' => $reguler->id,
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
}
