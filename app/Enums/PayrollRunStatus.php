<?php

namespace App\Enums;

enum PayrollRunStatus: string
{
    case Received = 'received';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
}
