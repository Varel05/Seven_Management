<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardLiveDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_dashboard_live_data(): void
    {
        $response = $this->getJson('/dashboard/live-data');

        $response->assertStatus(401);
    }

    public function test_authenticated_user_can_fetch_dashboard_live_data(): void
    {
        $user = User::factory()->create();

        // Siapkan akun dan jurnal
        $cashAccount = Account::create([
            'code' => '1001',
            'name' => 'Kas Operasional',
            'type' => 'asset',
        ]);

        $revenueAccount = Account::create([
            'code' => '4001',
            'name' => 'Pendapatan Jasa',
            'type' => 'revenue',
        ]);

        $entry = JournalEntry::create([
            'reference' => 'TG-TEST001',
            'description' => 'Penjualan Project Web',
            'date' => now(),
            'source' => 'telegram',
            'status' => 'verified',
        ]);

        JournalEntryLine::create([
            'journal_entry_id' => $entry->id,
            'account_id' => $cashAccount->id,
            'debit' => 1500000,
            'credit' => 0,
            'description' => 'Penerimaan kas',
        ]);

        JournalEntryLine::create([
            'journal_entry_id' => $entry->id,
            'account_id' => $revenueAccount->id,
            'debit' => 0,
            'credit' => 1500000,
            'description' => 'Pendapatan project',
        ]);

        $response = $this->actingAs($user)->getJson('/dashboard/live-data');

        $response->assertOk()
            ->assertJsonStructure([
                'has_changes',
                'hash',
                'timestamp',
                'latest_entry_id',
                'kpi' => [
                    'totalKasDanBank',
                    'totalKas',
                    'pemasukanBulanIni',
                    'pengeluaranBulanIni',
                    'labaBersihBulanIni',
                    'is_profit',
                    'pendingCount',
                    'verifiedCount',
                ],
                'accounts',
                'chartData',
                'items',
                'table_html',
            ]);

        $this->assertTrue($response->json('has_changes'));
        $this->assertEquals(1500000, $response->json('kpi.totalKasDanBank'));
        $this->assertEquals(1500000, $response->json('kpi.pemasukanBulanIni'));
        $this->assertEquals(1, $response->json('kpi.verifiedCount'));
    }

    public function test_returns_has_changes_false_when_hash_matches(): void
    {
        $user = User::factory()->create();

        // First fetch to get initial hash
        $firstResponse = $this->actingAs($user)->getJson('/dashboard/live-data');
        $hash = $firstResponse->json('hash');

        // Second fetch passing the same hash
        $secondResponse = $this->actingAs($user)->getJson('/dashboard/live-data?hash='.$hash);

        $secondResponse->assertOk();
        $this->assertFalse($secondResponse->json('has_changes'));
        $this->assertEquals($hash, $secondResponse->json('hash'));
    }

    public function test_returns_fresh_data_when_force_parameter_is_passed_even_if_hash_matches(): void
    {
        $user = User::factory()->create();

        $firstResponse = $this->actingAs($user)->getJson('/dashboard/live-data');
        $hash = $firstResponse->json('hash');

        $secondResponse = $this->actingAs($user)->getJson('/dashboard/live-data?hash='.$hash.'&force=1');

        $secondResponse->assertOk();
        $this->assertTrue($secondResponse->json('has_changes'));
        $this->assertArrayHasKey('kpi', $secondResponse->json());
    }

    public function test_detects_new_telegram_transaction_and_provides_toast_alert(): void
    {
        $user = User::factory()->create();

        $cashAccount = Account::create([
            'code' => '1001',
            'name' => 'Kas Operasional',
            'type' => 'asset',
        ]);
        $expenseAccount = Account::create([
            'code' => '5001',
            'name' => 'Beban Operasional',
            'type' => 'expense',
        ]);

        $initialResponse = $this->actingAs($user)->getJson('/dashboard/live-data');
        $initialHash = $initialResponse->json('hash');
        $initialLastId = $initialResponse->json('latest_entry_id') ?? 0;

        // Simulasi n8n memproses chat Telegram baru
        $newTelegramEntry = JournalEntry::create([
            'reference' => 'TG-NEW099',
            'description' => 'Beli ATK dan Snack Kantor',
            'date' => now(),
            'source' => 'telegram',
            'status' => 'pending',
        ]);

        JournalEntryLine::create([
            'journal_entry_id' => $newTelegramEntry->id,
            'account_id' => $expenseAccount->id,
            'debit' => 75000,
            'credit' => 0,
            'description' => 'Beban ATK',
        ]);

        JournalEntryLine::create([
            'journal_entry_id' => $newTelegramEntry->id,
            'account_id' => $cashAccount->id,
            'debit' => 0,
            'credit' => 75000,
            'description' => 'Kas keluar',
        ]);

        $pollResponse = $this->actingAs($user)->getJson('/dashboard/live-data?hash='.$initialHash.'&last_entry_id='.$initialLastId);

        $pollResponse->assertOk();
        $this->assertTrue($pollResponse->json('has_changes'));
        $this->assertNotNull($pollResponse->json('new_transaction'));
        $this->assertEquals('TG-NEW099', $pollResponse->json('new_transaction.reference'));
        $this->assertEquals('Beli ATK dan Snack Kantor', $pollResponse->json('new_transaction.description'));
        $this->assertTrue($pollResponse->json('new_transaction.is_telegram'));
        $this->assertEquals(75000, $pollResponse->json('new_transaction.amount'));
        $this->assertEquals(1, $pollResponse->json('kpi.pendingCount'));
    }
}
