<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_manage_accounts(): void
    {
        $response = $this->post('/accounts', [
            'code' => '1002',
            'name' => 'Bank BCA',
            'type' => 'asset',
        ]);
        $response->assertRedirect('/login');
    }

    public function test_authenticated_user_can_create_finance_account(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/accounts', [
            'code' => '1002',
            'name' => 'Bank BCA Operasional',
            'type' => 'asset',
        ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('accounts', [
            'code' => '1002',
            'name' => 'Bank BCA Operasional',
            'type' => 'asset',
        ]);
    }

    public function test_cannot_create_account_with_duplicate_code(): void
    {
        $user = User::factory()->create();
        Account::create([
            'code' => '1001',
            'name' => 'Kas Operasional',
            'type' => 'asset',
        ]);

        $response = $this->actingAs($user)->post('/accounts', [
            'code' => '1001',
            'name' => 'Kas Duplikat',
            'type' => 'asset',
        ]);

        $response->assertSessionHasErrors('code');
    }

    public function test_authenticated_user_can_update_account(): void
    {
        $user = User::factory()->create();
        $account = Account::create([
            'code' => '5002',
            'name' => 'Beban Listrik',
            'type' => 'expense',
        ]);

        $response = $this->actingAs($user)->put("/accounts/{$account->id}", [
            'code' => '5002',
            'name' => 'Beban Listrik & Air PAM',
            'type' => 'expense',
        ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('accounts', [
            'id'   => $account->id,
            'name' => 'Beban Listrik & Air PAM',
        ]);
    }

    public function test_user_can_delete_account_without_transactions(): void
    {
        $user = User::factory()->create();
        $account = Account::create([
            'code' => '5099',
            'name' => 'Beban Sementara',
            'type' => 'expense',
        ]);

        $response = $this->actingAs($user)->delete("/accounts/{$account->id}");

        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('accounts', [
            'id' => $account->id,
        ]);
    }

    public function test_user_cannot_delete_account_with_transactions(): void
    {
        $user = User::factory()->create();
        $account = Account::create([
            'code' => '1001',
            'name' => 'Kas Utama',
            'type' => 'asset',
        ]);

        $entry = JournalEntry::create([
            'reference'   => 'TG-TEST001',
            'description' => 'Test Transaksi',
            'date'        => now(),
            'source'      => 'test',
            'status'      => 'verified',
        ]);

        JournalEntryLine::create([
            'journal_entry_id' => $entry->id,
            'account_id'       => $account->id,
            'description'      => 'Test',
            'debit'            => 100000,
            'credit'           => 0,
        ]);

        $response = $this->actingAs($user)->delete("/accounts/{$account->id}");

        $response->assertSessionHas('warning');
        $this->assertDatabaseHas('accounts', [
            'id' => $account->id,
        ]);
    }
}
