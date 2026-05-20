<?php

namespace App\Enums;

enum PayrollItemStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';
}
