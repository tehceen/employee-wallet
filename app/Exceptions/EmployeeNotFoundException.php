<?php

namespace App\Exceptions;

use Exception;

class EmployeeNotFoundException extends Exception
{
    public function __construct(public readonly string $externalRef)
    {
        parent::__construct(sprintf('Employee not found for external ref [%s].', $externalRef));
    }
}
