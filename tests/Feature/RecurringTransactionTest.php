<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\RecurringTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecurringTransactionTest extends TestCase
{
    use RefreshDatabase;

    protected Account $expenseAccount;

    protected Account $assetAccount;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.webhook.secret' => 'test_secret_key']);

        $this->assetAccount = Account::create([
            'code' => '1002',
            'name' => 'Bank BCA',
            'type' => 'asset',
        ]);

        $this->expenseAccount = Account::create([
            'code' => '5002',
            'name' => 'Beban Gaji',
            'type' => 'expense',
        ]);
    }

    public function test_user_can_create_recurring_transaction(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/recurring-transactions', [
            'name' => 'Gaji Karyawan',
            'amount' => 15000000,
            'frequency' => 'monthly',
            'day_of_month' => 25,
            'expense_account_id' => $this->expenseAccount->id,
            'asset_account_id' => $this->assetAccount->id,
            'status' => 'active',
            'notes' => 'Gaji rutin bulanan',
        ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('recurring_transactions', [
            'name' => 'Gaji Karyawan',
            'amount' => 15000000,
            'day_of_month' => 25,
        ]);
    }

    public function test_user_can_approve_recurring_from_web_dashboard(): void
    {
        $user = User::factory()->create();

        $recurring = RecurringTransaction::create([
            'name' => 'Langganan Server AWS',
            'amount' => 750000,
            'frequency' => 'monthly',
            'day_of_month' => now()->day,
            'expense_account_id' => $this->expenseAccount->id,
            'asset_account_id' => $this->assetAccount->id,
            'status' => 'active',
        ]);

        $this->assertTrue($recurring->isDueToday());

        $response = $this->actingAs($user)->post("/recurring-transactions/{$recurring->id}/approve");

        $response->assertSessionHas('success');

        // Pastikan jurnal tercipta
        $this->assertDatabaseHas('journal_entries', [
            'source' => 'website',
        ]);

        // Pastikan double-entry seimbang
        $this->assertDatabaseHas('journal_entry_lines', [
            'account_id' => $this->expenseAccount->id,
            'debit' => 750000,
            'credit' => 0,
        ]);

        $this->assertDatabaseHas('journal_entry_lines', [
            'account_id' => $this->assetAccount->id,
            'debit' => 0,
            'credit' => 750000,
        ]);

        // Recurring tidak lagi due today karena sudah di-post periode ini
        $recurring->refresh();
        $this->assertNotNull($recurring->last_posted_at);
        $this->assertFalse($recurring->isDueToday());
    }

    public function test_webhook_api_returns_due_recurring_expenses(): void
    {
        // Tagihan yang jatuh tempo hari ini
        $due = RecurringTransaction::create([
            'name' => 'Internet Kantor',
            'amount' => 500000,
            'frequency' => 'monthly',
            'day_of_month' => now()->day,
            'expense_account_id' => $this->expenseAccount->id,
            'asset_account_id' => $this->assetAccount->id,
            'status' => 'active',
        ]);

        // Tagihan yang jatuh tempo tanggal lain
        $notDueDay = (now()->day % 28) + 1;
        if ($notDueDay === now()->day) {
            $notDueDay = ($notDueDay % 28) + 2;
        }

        RecurringTransaction::create([
            'name' => 'Sewa Ruko',
            'amount' => 3000000,
            'frequency' => 'monthly',
            'day_of_month' => $notDueDay,
            'expense_account_id' => $this->expenseAccount->id,
            'asset_account_id' => $this->assetAccount->id,
            'status' => 'active',
        ]);

        $response = $this->getJson('/api/webhook/recurring/due', [
            'X-Webhook-Secret' => 'test_secret_key',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'status' => true,
            'count' => 1,
        ]);
        $response->assertJsonFragment([
            'name' => 'Internet Kantor',
        ]);
    }

    public function test_webhook_api_approve_recurring_creates_journal_entry(): void
    {
        $recurring = RecurringTransaction::create([
            'name' => 'Langganan Software Canva Pro',
            'amount' => 150000,
            'frequency' => 'monthly',
            'day_of_month' => now()->day,
            'expense_account_id' => $this->expenseAccount->id,
            'asset_account_id' => $this->assetAccount->id,
            'status' => 'active',
        ]);

        $response = $this->postJson("/api/webhook/recurring/{$recurring->id}/approve", [], [
            'X-Webhook-Secret' => 'test_secret_key',
        ]);

        $response->assertStatus(201);
        $response->assertJson([
            'status' => true,
        ]);

        // Cek database lines
        $this->assertDatabaseHas('journal_entry_lines', [
            'account_id' => $this->expenseAccount->id,
            'debit' => 150000,
        ]);

        $this->assertDatabaseHas('journal_entry_lines', [
            'account_id' => $this->assetAccount->id,
            'credit' => 150000,
        ]);

        $recurring->refresh();
        $this->assertNotNull($recurring->last_posted_at);
    }

    public function test_webhook_api_returns_upcoming_bills_for_current_month(): void
    {
        $today = now();
        // Hari mendatang bulan ini
        $upcomingDay = min(28, $today->day + 3);

        RecurringTransaction::create([
            'name' => 'Tagihan Hosting AWS',
            'amount' => 1200000,
            'frequency' => 'monthly',
            'day_of_month' => $upcomingDay,
            'expense_account_id' => $this->expenseAccount->id,
            'asset_account_id' => $this->assetAccount->id,
            'status' => 'active',
        ]);

        $response = $this->getJson('/api/webhook/recurring/due', [
            'X-Webhook-Secret' => 'test_secret_key',
        ]);

        $response->assertStatus(200);
        $this->assertNotEmpty($response->json('message'));
        $this->assertStringContainsString('Tagihan Hosting AWS', $response->json('message'));
        $this->assertStringContainsString('Rp 1.200.000', $response->json('message'));
    }

    public function test_manual_action_approves_recurring_by_id(): void
    {
        $recurring = RecurringTransaction::create([
            'name' => 'Listrik PLN',
            'amount' => 450000,
            'frequency' => 'monthly',
            'day_of_month' => now()->day,
            'expense_account_id' => $this->expenseAccount->id,
            'asset_account_id' => $this->assetAccount->id,
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/webhook/recurring/manual-action', [
            'query' => (string) $recurring->id,
            'action' => 'approve',
        ], [
            'X-Webhook-Secret' => 'test_secret_key',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('journal_entry_lines', [
            'account_id' => $this->expenseAccount->id,
            'debit' => 450000,
        ]);

        $recurring->refresh();
        $this->assertNotNull($recurring->last_posted_at);
    }

    public function test_manual_action_approves_recurring_by_name(): void
    {
        $recurring = RecurringTransaction::create([
            'name' => 'Gaji Staff Admin',
            'amount' => 3500000,
            'frequency' => 'monthly',
            'day_of_month' => now()->day,
            'expense_account_id' => $this->expenseAccount->id,
            'asset_account_id' => $this->assetAccount->id,
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/webhook/recurring/manual-action', [
            'query' => 'Staff Admin',
            'action' => 'approve',
        ], [
            'X-Webhook-Secret' => 'test_secret_key',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('journal_entry_lines', [
            'account_id' => $this->expenseAccount->id,
            'debit' => 3500000,
        ]);

        $recurring->refresh();
        $this->assertNotNull($recurring->last_posted_at);
    }
}
