<?php

namespace App\Enums;

enum BankWithdrawalStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
}
