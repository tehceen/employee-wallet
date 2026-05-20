<?php

namespace Tests\Feature\Bank;

use App\Enums\BankWithdrawalStatus;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WithdrawalApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_withdrawal_endpoint_locks_funds(): void
    {
        Queue::fake();

        $wallet = Wallet::factory()->create(['balance' => 2_000_00]);

        $response = $this->postJson("/api/wallets/{$wallet->id}/withdrawals", [
            'amount' => 500_00,
            'idempotency_key' => 'api-wd-1',
        ]);

        $response->assertAccepted()
            ->assertJson([
                'status' => BankWithdrawalStatus::Pending->value,
                'duplicate' => false,
                'wallet' => [
                    'available_balance' => 1_500_00,
                    'locked_balance' => 500_00,
                    'total_balance' => 2_000_00,
                ],
            ]);
    }

    public function test_bank_webhook_finalizes_withdrawal(): void
    {
        Queue::fake();

        $wallet = Wallet::factory()->create(['balance' => 1_000_00]);

        $create = $this->postJson("/api/wallets/{$wallet->id}/withdrawals", [
            'amount' => 400_00,
            'idempotency_key' => 'api-wd-wh',
        ]);

        $withdrawalId = $create->json('withdrawal_id');

        $withdrawal = $wallet->bankWithdrawals()->findOrFail($withdrawalId);
        $withdrawal->update([
            'status' => BankWithdrawalStatus::Processing,
            'bank_reference' => 'manual-bank-ref',
        ]);

        $this->postJson('/api/webhooks/bank/withdrawal-status', [
            'bank_reference' => 'manual-bank-ref',
            'status' => 'completed',
            'idempotency_key' => 'wh-cb-1',
            'external_event_id' => 'wh-evt-1',
        ])->assertOk()->assertJson(['status' => 'completed']);

        $wallet->refresh();
        $this->assertSame(600_00, $wallet->balance);
        $this->assertSame(0, $wallet->locked_balance);
    }
}
