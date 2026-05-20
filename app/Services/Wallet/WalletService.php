<?php

namespace App\Services\Wallet;

use App\Enums\LedgerEntryType;
use App\Exceptions\InsufficientBalanceException;
use App\Exceptions\InsufficientLockedBalanceException;
use App\Models\LedgerEntry;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Single entry point for mutating wallet balances and the immutable ledger.
 */
class WalletService
{
    public function credit(
        Wallet $wallet,
        int $amount,
        string $reason,
        LedgerEntryType $type = LedgerEntryType::Credit,
        array $metadata = [],
    ): LedgerEntry {
        $this->assertPositiveAmount($amount);
        $this->assertNonEmptyReason($reason);

        return $this->transact(function () use ($wallet, $amount, $reason, $type, $metadata): LedgerEntry {
            return $this->applyAvailableChange(
                wallet: $this->lockWallet($wallet),
                type: $type,
                availableDelta: $amount,
                reason: $reason,
                metadata: $metadata,
            );
        });
    }

    public function debit(Wallet $wallet, int $amount, string $reason): LedgerEntry
    {
        $this->assertPositiveAmount($amount);
        $this->assertNonEmptyReason($reason);

        return $this->transact(function () use ($wallet, $amount, $reason): LedgerEntry {
            return $this->applyAvailableChange(
                wallet: $this->lockWallet($wallet),
                type: LedgerEntryType::Debit,
                availableDelta: -$amount,
                reason: $reason,
            );
        });
    }

    public function holdForWithdrawal(
        Wallet $wallet,
        int $amount,
        string $reason,
        array $metadata = [],
    ): LedgerEntry {
        $this->assertPositiveAmount($amount);
        $this->assertNonEmptyReason($reason);

        return $this->transact(function () use ($wallet, $amount, $reason, $metadata): LedgerEntry {
            return $this->applyHoldChange(
                wallet: $this->lockWallet($wallet),
                type: LedgerEntryType::WithdrawalHold,
                availableDelta: -$amount,
                lockedDelta: $amount,
                reason: $reason,
                metadata: $metadata,
            );
        });
    }

    public function releaseWithdrawalHold(
        Wallet $wallet,
        int $amount,
        string $reason,
        array $metadata = [],
    ): LedgerEntry {
        $this->assertPositiveAmount($amount);
        $this->assertNonEmptyReason($reason);

        return $this->transact(function () use ($wallet, $amount, $reason, $metadata): LedgerEntry {
            return $this->applyHoldChange(
                wallet: $this->lockWallet($wallet),
                type: LedgerEntryType::WithdrawalRelease,
                availableDelta: $amount,
                lockedDelta: -$amount,
                reason: $reason,
                metadata: $metadata,
            );
        });
    }

    public function finalizeWithdrawalHold(
        Wallet $wallet,
        int $amount,
        string $reason,
        array $metadata = [],
    ): LedgerEntry {
        $this->assertPositiveAmount($amount);
        $this->assertNonEmptyReason($reason);

        return $this->transact(function () use ($wallet, $amount, $reason, $metadata): LedgerEntry {
            return $this->applyHoldChange(
                wallet: $this->lockWallet($wallet),
                type: LedgerEntryType::WithdrawalSettled,
                availableDelta: 0,
                lockedDelta: -$amount,
                reason: $reason,
                metadata: $metadata,
            );
        });
    }

    /**
     * @return array{debit: LedgerEntry, credit: LedgerEntry}
     */
    public function transfer(Wallet $fromWallet, Wallet $toWallet, int $amount): array
    {
        $this->assertPositiveAmount($amount);

        if ($fromWallet->is($toWallet)) {
            throw new InvalidArgumentException('Cannot transfer to the same wallet.');
        }

        return $this->transact(function () use ($fromWallet, $toWallet, $amount): array {
            [$lockedFrom, $lockedTo] = $this->lockWalletsInOrder($fromWallet, $toWallet);

            $debitEntry = $this->applyAvailableChange(
                wallet: $lockedFrom,
                type: LedgerEntryType::TransferOut,
                availableDelta: -$amount,
                reason: sprintf('Transfer to wallet %d', $lockedTo->id),
                metadata: ['counterparty_wallet_id' => $lockedTo->id],
            );

            $creditEntry = $this->applyAvailableChange(
                wallet: $lockedTo,
                type: LedgerEntryType::TransferIn,
                availableDelta: $amount,
                reason: sprintf('Transfer from wallet %d', $lockedFrom->id),
                metadata: ['counterparty_wallet_id' => $lockedFrom->id],
            );

            return [
                'debit' => $debitEntry,
                'credit' => $creditEntry,
            ];
        });
    }

    private function lockWallet(Wallet $wallet): Wallet
    {
        return Wallet::query()
            ->whereKey($wallet->getKey())
            ->lockForUpdate()
            ->firstOrFail();
    }

    /**
     * @return array{0: Wallet, 1: Wallet}
     */
    private function lockWalletsInOrder(Wallet $first, Wallet $second): array
    {
        $orderedIds = [$first->getKey(), $second->getKey()];
        sort($orderedIds);

        $locked = Wallet::query()
            ->whereIn('id', $orderedIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        return [
            $locked[$first->getKey()],
            $locked[$second->getKey()],
        ];
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function transact(callable $callback): mixed
    {
        if (DB::transactionLevel() > 0) {
            return $callback();
        }

        return DB::transaction($callback);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function applyAvailableChange(
        Wallet $wallet,
        LedgerEntryType $type,
        int $availableDelta,
        string $reason,
        array $metadata = [],
    ): LedgerEntry {
        $newAvailable = $wallet->balance + $availableDelta;

        if ($newAvailable < 0) {
            throw new InsufficientBalanceException(
                wallet: $wallet,
                requestedAmount: abs($availableDelta),
                availableBalance: $wallet->balance,
            );
        }

        return $this->persistLedgerEntry(
            wallet: $wallet,
            type: $type,
            availableDelta: $availableDelta,
            lockedDelta: 0,
            availableAfter: $newAvailable,
            lockedAfter: $wallet->locked_balance,
            reason: $reason,
            metadata: $metadata,
        );
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function applyHoldChange(
        Wallet $wallet,
        LedgerEntryType $type,
        int $availableDelta,
        int $lockedDelta,
        string $reason,
        array $metadata = [],
    ): LedgerEntry {
        $newAvailable = $wallet->balance + $availableDelta;
        $newLocked = $wallet->locked_balance + $lockedDelta;

        if ($newAvailable < 0) {
            throw new InsufficientBalanceException(
                wallet: $wallet,
                requestedAmount: abs($availableDelta),
                availableBalance: $wallet->balance,
            );
        }

        if ($newLocked < 0) {
            throw new InsufficientLockedBalanceException(
                wallet: $wallet,
                requestedAmount: abs($lockedDelta),
                lockedBalance: $wallet->locked_balance,
            );
        }

        return $this->persistLedgerEntry(
            wallet: $wallet,
            type: $type,
            availableDelta: $availableDelta,
            lockedDelta: $lockedDelta,
            availableAfter: $newAvailable,
            lockedAfter: $newLocked,
            reason: $reason,
            metadata: $metadata,
        );
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function persistLedgerEntry(
        Wallet $wallet,
        LedgerEntryType $type,
        int $availableDelta,
        int $lockedDelta,
        int $availableAfter,
        int $lockedAfter,
        string $reason,
        array $metadata = [],
    ): LedgerEntry {
        $entry = $wallet->ledgerEntries()->create([
            'type' => $type,
            'amount' => $availableDelta,
            'balance_after' => $availableAfter,
            'reason' => $reason,
            'metadata' => array_merge($metadata, [
                'available_delta' => $availableDelta,
                'locked_delta' => $lockedDelta,
                'available_balance_after' => $availableAfter,
                'locked_balance_after' => $lockedAfter,
                'total_balance_after' => $availableAfter + $lockedAfter,
            ]),
        ]);

        $wallet->update([
            'balance' => $availableAfter,
            'locked_balance' => $lockedAfter,
        ]);

        $wallet->balance = $availableAfter;
        $wallet->locked_balance = $lockedAfter;

        return $entry;
    }

    private function assertPositiveAmount(int $amount): void
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Amount must be a positive integer.');
        }
    }

    private function assertNonEmptyReason(string $reason): void
    {
        if (trim($reason) === '') {
            throw new InvalidArgumentException('Reason must not be empty.');
        }
    }
}
