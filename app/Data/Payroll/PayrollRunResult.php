<?php

namespace App\Data\Payroll;

use App\Models\PayrollRun;

readonly class PayrollRunResult
{
    public function __construct(
        public PayrollRun $payrollRun,
        public bool $wasDuplicate,
    ) {}

    public static function processed(PayrollRun $payrollRun): self
    {
        return new self(payrollRun: $payrollRun, wasDuplicate: false);
    }

    public static function duplicate(PayrollRun $payrollRun): self
    {
        return new self(payrollRun: $payrollRun, wasDuplicate: true);
    }
}
