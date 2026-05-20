<?php

namespace Tests\Feature\Wallet;

use App\Enums\LedgerEntryType;
use App\Exceptions\InsufficientBalanceException;
use App\Models\LedgerEntry;
use App\Models\Wallet;
use App\Services\Wallet\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class WalletServiceTest extends TestCase
{
    use RefreshDatabase;

    private WalletService $walletService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->walletService = app(WalletService::class);
    }

    public function test_credit_increases_balance_and_creates_ledger_entry(): void
    {
        $wallet = Wallet::factory()->create(['balance' => 100]);

        $entry = $this->walletService->credit($wallet, 50, 'Payroll top-up');

        $wallet->refresh();

        $this->assertSame(150, $wallet->balance);
        $this->assertDatabaseHas('ledger_entries', [
            'id' => $entry->id,
            'wallet_id' => $wallet->id,
            'type' => LedgerEntryType::Credit->value,
            'amount' => 50,
            'balance_after' => 150,
            'reason' => 'Payroll top-up',
        ]);
    }

    public function test_debit_decreases_balance_and_creates_ledger_entry(): void
    {
        $wallet = Wallet::factory()->create(['balance' => 200]);

        $entry = $this->walletService->debit($wallet, 75, 'Purchase');

        $wallet->refresh();

        $this->assertSame(125, $wallet->balance);
        $this->assertSame(LedgerEntryType::Debit, $entry->type);
        $this->assertSame(-75, $entry->amount);
        $this->assertSame(125, $entry->balance_after);
    }

    public function test_debit_throws_when_balance_would_go_negative(): void
    {
        $wallet = Wallet::factory()->create(['balance' => 30]);

        $this->expectException(InsufficientBalanceException::class);

        try {
            $this->walletService->debit($wallet, 31, 'Overdraft attempt');
        } finally {
            $wallet->refresh();
            $this->assertSame(30, $wallet->balance);
            $this->assertSame(0, LedgerEntry::query()->count());
        }
    }

    public function test_transfer_moves_funds_between_wallets(): void
    {
        $from = Wallet::factory()->create(['balance' => 500]);
        $to = Wallet::factory()->create(['balance' => 100]);

        $result = $this->walletService->transfer($from, $to, 200);

        $from->refresh();
        $to->refresh();

        $this->assertSame(300, $from->balance);
        $this->assertSame(300, $to->balance);
        $this->assertSame(LedgerEntryType::TransferOut, $result['debit']->type);
        $this->assertSame(LedgerEntryType::TransferIn, $result['credit']->type);
        $this->assertSame(-200, $result['debit']->amount);
        $this->assertSame(200, $result['credit']->amount);
        $this->assertSame($to->id, $result['debit']->metadata['counterparty_wallet_id']);
        $this->assertSame($from->id, $result['credit']->metadata['counterparty_wallet_id']);
        $this->assertSame(2, LedgerEntry::query()->count());
    }

    public function test_transfer_throws_when_source_has_insufficient_balance(): void
    {
        $from = Wallet::factory()->create(['balance' => 50]);
        $to = Wallet::factory()->create(['balance' => 0]);

        $this->expectException(InsufficientBalanceException::class);

        $this->walletService->transfer($from, $to, 51);

        $from->refresh();
        $to->refresh();

        $this->assertSame(50, $from->balance);
        $this->assertSame(0, $to->balance);
        $this->assertSame(0, LedgerEntry::query()->count());
    }

    public function test_transfer_to_same_wallet_is_rejected(): void
    {
        $wallet = Wallet::factory()->create(['balance' => 100]);

        $this->expectException(InvalidArgumentException::class);

        $this->walletService->transfer($wallet, $wallet, 10);
    }

    public function test_zero_or_negative_amounts_are_rejected(): void
    {
        $wallet = Wallet::factory()->create(['balance' => 100]);

        $this->expectException(InvalidArgumentException::class);
        $this->walletService->credit($wallet, 0, 'Invalid');

        $this->expectException(InvalidArgumentException::class);
        $this->walletService->debit($wallet, -5, 'Invalid');
    }

    public function test_sequential_debits_respect_running_balance(): void
    {
        $wallet = Wallet::factory()->create(['balance' => 100]);

        $this->walletService->debit($wallet, 40, 'First');
        $this->walletService->debit($wallet, 40, 'Second');

        $this->expectException(InsufficientBalanceException::class);
        $this->walletService->debit($wallet, 30, 'Third');

        $wallet->refresh();
        $this->assertSame(20, $wallet->balance);
        $this->assertSame(2, $wallet->ledgerEntries()->count());
    }
}
