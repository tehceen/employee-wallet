<?php

namespace App\Exceptions;

use App\Models\Wallet;
use Exception;

class InsufficientLockedBalanceException extends Exception
{
    public function __construct(
        public readonly Wallet $wallet,
        public readonly int $requestedAmount,
        public readonly int $lockedBalance,
    ) {
        parent::__construct(sprintf(
            'Insufficient locked balance on wallet %d: requested %d, locked %d.',
            $wallet->id,
            $requestedAmount,
            $lockedBalance,
        ));
    }
}
