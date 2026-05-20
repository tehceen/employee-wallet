<?php

namespace App\Data\Bank;

use App\Models\BankWithdrawal;

readonly class BankCallbackResult
{
    public function __construct(
        public BankWithdrawal $withdrawal,
        public bool $wasDuplicate,
    ) {}

    public static function processed(BankWithdrawal $withdrawal): self
    {
        return new self(withdrawal: $withdrawal, wasDuplicate: false);
    }

    public static function duplicate(BankWithdrawal $withdrawal): self
    {
        return new self(withdrawal: $withdrawal, wasDuplicate: true);
    }
}
