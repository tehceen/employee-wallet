<?php

namespace App\Exceptions;

use Exception;

class PayrollAmountMismatchException extends Exception
{
    public function __construct(
        public readonly int $payrollItemId,
        public readonly int $existingAmount,
        public readonly int $requestedAmount,
    ) {
        parent::__construct(sprintf(
            'Payroll item %d amount mismatch: stored %d, requested %d.',
            $payrollItemId,
            $existingAmount,
            $requestedAmount,
        ));
    }
}
