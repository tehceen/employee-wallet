<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BankWithdrawalNotFoundException extends Exception
{
    public function __construct(public readonly string $bankReference)
    {
        parent::__construct(sprintf(
            'No withdrawal found for bank_reference [%s]. Create a withdrawal first and use the bank_reference returned after submission.',
            $bankReference,
        ));
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'bank_reference' => $this->bankReference,
        ], 404);
    }
}
