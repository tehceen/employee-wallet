<?php

namespace App\Exceptions;

use App\Models\Wallet;
use Exception;

class InsufficientBalanceException extends Exception
{
    public function __construct(
        public readonly Wallet $wallet,
        public readonly int $requestedAmount,
        public readonly int $availableBalance,
    ) {
        parent::__construct(sprintf(
            'Insufficient balance on wallet %d: requested %d, available %d.',
            $wallet->id,
            $requestedAmount,
            $availableBalance,
        ));
    }
}
