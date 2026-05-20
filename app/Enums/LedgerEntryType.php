<?php

namespace App\Enums;

enum LedgerEntryType: string
{
    case Credit = 'credit';
    case Debit = 'debit';
    case TransferIn = 'transfer_in';
    case TransferOut = 'transfer_out';
    case PayrollCredit = 'payroll_credit';
    case WithdrawalHold = 'withdrawal_hold';
    case WithdrawalRelease = 'withdrawal_release';
    case WithdrawalSettled = 'withdrawal_settled';
}
