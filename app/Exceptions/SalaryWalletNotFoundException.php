<?php

namespace App\Exceptions;

use App\Models\Employee;
use Exception;

class SalaryWalletNotFoundException extends Exception
{
    public function __construct(public readonly Employee $employee)
    {
        parent::__construct(sprintf('Salary wallet not found for employee %d.', $employee->id));
    }
}
