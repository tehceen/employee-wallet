<?php

namespace Tests\Feature\Bank;

use App\Data\Bank\BankCallbackInput;
use App\Enums\BankWithdrawalStatus;
use App\Enums\LedgerEntryType;
use App\Exceptions\InsufficientBalanceException;
use App\Models\BankWithdrawal;
use App\Models\LedgerEntry;
use App\Models\Wallet;
use App\Services\Bank\BankCallbackService;
use App\Services\Bank\BankWithdrawalService;
use App\Services\Wallet\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class BankWithdrawalServiceTest extends TestCase
{
    use RefreshDatabase;

    private BankWithdrawalService $withdrawalService;

    private BankCallbackService $callbackService;

    private WalletService $walletService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withdrawalService = app(BankWithdrawalService::class);
        $this->callbackService = app(BankCallbackService::class);
        $this->walletService = app(WalletService::class);
    }

    public function test_request_locks_funds_and_creates_pending_withdrawal(): void
    {
        Queue::fake();

        $wallet = Wallet::factory()->create(['balance' => 1_000_00]);

        $result = $this->withdrawalService->initiate($wallet, 300_00, 'wd-req-1');
        $withdrawal = $result->withdrawal;

        $wallet->refresh();

        $this->assertSame(BankWithdrawalStatus::Pending, $withdrawal->status);
        $this->assertSame(700_00, $wallet->balance);
        $this->assertSame(300_00, $wallet->locked_balance);
        $this->assertNotNull($withdrawal->hold_ledger_entry_id);
        $this->assertDatabaseHas('ledger_entries', [
            'id' => $withdrawal->hold_ledger_entry_id,
            'type' => LedgerEntryType::WithdrawalHold->value,
            'amount' => -300_00,
        ]);
    }

    public function test_locked_funds_cannot_be_spent(): void
    {
        Queue::fake();

        $wallet = Wallet::factory()->create(['balance' => 500_00]);
        $this->withdrawalService->initiate($wallet, 400_00, 'wd-lock-spend');

        $wallet->refresh();

        $this->expectException(InsufficientBalanceException::class);
        $this->walletService->debit($wallet, 200_00, 'Should fail');
    }

    public function test_duplicate_request_does_not_double_lock(): void
    {
        Queue::fake();

        $wallet = Wallet::factory()->create(['balance' => 1_000_00]);

        $this->withdrawalService->initiate($wallet, 250_00, 'wd-dup');
        $this->withdrawalService->initiate($wallet, 250_00, 'wd-dup');

        $wallet->refresh();

        $this->assertSame(750_00, $wallet->balance);
        $this->assertSame(250_00, $wallet->locked_balance);
        $this->assertSame(1, BankWithdrawal::query()->count());
        $this->assertSame(1, LedgerEntry::query()->where('type', LedgerEntryType::WithdrawalHold)->count());
    }

    public function test_success_callback_finalizes_withdrawal(): void
    {
        $wallet = Wallet::factory()->create(['balance' => 800_00]);
        $withdrawal = $this->createProcessingWithdrawal($wallet, 200_00, 'wd-success');

        $result = $this->callbackService->apply(new BankCallbackInput(
            bankReference: $withdrawal->bank_reference,
            status: 'completed',
            idempotencyKey: 'cb-success-1',
            externalEventId: 'evt-success-1',
        ));

        $wallet->refresh();
        $withdrawal->refresh();

        $this->assertFalse($result->wasDuplicate);
        $this->assertSame(BankWithdrawalStatus::Completed, $withdrawal->status);
        $this->assertSame(600_00, $wallet->balance);
        $this->assertSame(0, $wallet->locked_balance);
        $this->assertNotNull($withdrawal->settle_ledger_entry_id);
    }

    public function test_failure_callback_releases_locked_funds(): void
    {
        $wallet = Wallet::factory()->create(['balance' => 800_00]);
        $withdrawal = $this->createProcessingWithdrawal($wallet, 200_00, 'wd-fail');

        $this->callbackService->apply(new BankCallbackInput(
            bankReference: $withdrawal->bank_reference,
            status: 'failed',
            idempotencyKey: 'cb-fail-1',
            externalEventId: 'evt-fail-1',
            failureReason: 'Insufficient bank balance',
        ));

        $wallet->refresh();
        $withdrawal->refresh();

        $this->assertSame(BankWithdrawalStatus::Failed, $withdrawal->status);
        $this->assertSame(800_00, $wallet->balance);
        $this->assertSame(0, $wallet->locked_balance);
        $this->assertNotNull($withdrawal->release_ledger_entry_id);
    }

    public function test_callback_returns_clear_error_for_unknown_bank_reference(): void
    {
        $response = $this->postJson('/api/webhooks/bank/withdrawal-status', [
            'bank_reference' => 'does-not-exist',
            'status' => 'completed',
            'idempotency_key' => 'cb-missing',
        ]);

        $response->assertNotFound()
            ->assertJsonPath('bank_reference', 'does-not-exist')
            ->assertJsonStructure(['message']);
    }

    public function test_duplicate_callback_does_not_double_settle(): void
    {
        $wallet = Wallet::factory()->create(['balance' => 500_00]);
        $withdrawal = $this->createProcessingWithdrawal($wallet, 100_00, 'wd-cb-dup');

        $payload = new BankCallbackInput(
            bankReference: $withdrawal->bank_reference,
            status: 'completed',
            idempotencyKey: 'cb-dup-key',
            externalEventId: 'evt-dup',
        );

        $this->callbackService->apply($payload);
        $duplicate = $this->callbackService->apply($payload);

        $wallet->refresh();

        $this->assertTrue($duplicate->wasDuplicate);
        $this->assertSame(400_00, $wallet->balance);
        $this->assertSame(0, $wallet->locked_balance);
        $this->assertSame(1, LedgerEntry::query()->where('type', LedgerEntryType::WithdrawalSettled)->count());
    }

    public function test_async_job_completes_successful_withdrawal(): void
    {
        config(['bank.simulate_success' => true]);

        $wallet = Wallet::factory()->create(['balance' => 600_00]);

        $withdrawal = $this->withdrawalService->initiate($wallet, 150_00, 'wd-async')->withdrawal;

        $withdrawal->refresh();

        $this->assertSame(BankWithdrawalStatus::Completed, $withdrawal->status);
        $this->assertNotNull($withdrawal->bank_reference);

        $wallet->refresh();
        $this->assertSame(450_00, $wallet->balance);
        $this->assertSame(0, $wallet->locked_balance);
    }

    public function test_async_job_releases_funds_on_simulated_failure(): void
    {
        config(['bank.simulate_success' => false]);

        $wallet = Wallet::factory()->create(['balance' => 600_00]);

        $this->withdrawalService->initiate($wallet, 150_00, 'wd-async-fail');

        $wallet->refresh();

        $this->assertSame(600_00, $wallet->balance);
        $this->assertSame(0, $wallet->locked_balance);
        $this->assertSame(BankWithdrawalStatus::Failed, BankWithdrawal::query()->first()->status);
    }

    private function createProcessingWithdrawal(Wallet $wallet, int $amount, string $idempotencyKey): BankWithdrawal
    {
        Queue::fake();

        $withdrawal = $this->withdrawalService->initiate($wallet, $amount, $idempotencyKey)->withdrawal;

        $withdrawal->update([
            'status' => BankWithdrawalStatus::Processing,
            'bank_reference' => 'bank-ref-'.uniqid(),
        ]);

        return $withdrawal->fresh();
    }
}
