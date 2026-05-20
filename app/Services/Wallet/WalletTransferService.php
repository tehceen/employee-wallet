<?php

namespace App\Services\Wallet;

use App\Models\LedgerEntry;
use App\Models\Wallet;
use InvalidArgumentException;

class WalletTransferService
{
    public function __construct(
        private readonly WalletService $walletService,
    ) {}

    /**
     * @return array{debit: LedgerEntry, credit: LedgerEntry}
     */
    public function transfer(Wallet $fromWallet, Wallet $toWallet, int $amount): array
    {
        $this->assertTransferAllowed($fromWallet, $toWallet);

        return $this->walletService->transfer($fromWallet, $toWallet, $amount);
    }

    private function assertTransferAllowed(Wallet $fromWallet, Wallet $toWallet): void
    {
        if ($fromWallet->currency !== $toWallet->currency) {
            throw new InvalidArgumentException('Transfers must be between wallets with the same currency.');
        }

        if ($fromWallet->employee_id === null || $toWallet->employee_id === null) {
            throw new InvalidArgumentException('Both wallets must belong to an employee.');
        }

        if ($fromWallet->employee_id !== $toWallet->employee_id) {
            throw new InvalidArgumentException('Transfers are limited to wallets owned by the same employee.');
        }
    }
}
