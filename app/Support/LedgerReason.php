<?php

namespace App\Support;

final class LedgerReason
{
    public static function payrollCredit(string $runKey, string $employeeRef): string
    {
        return sprintf('Payroll credit (run %s, employee %s)', $runKey, $employeeRef);
    }

    public static function withdrawalHold(int $withdrawalId): string
    {
        return sprintf('Withdrawal hold #%d', $withdrawalId);
    }

    public static function withdrawalRelease(int $withdrawalId): string
    {
        return sprintf('Withdrawal released #%d', $withdrawalId);
    }

    public static function withdrawalSettled(int $withdrawalId): string
    {
        return sprintf('Withdrawal sent to bank #%d', $withdrawalId);
    }
}
