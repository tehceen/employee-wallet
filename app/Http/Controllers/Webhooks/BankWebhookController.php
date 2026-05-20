<?php

namespace App\Http\Controllers\Webhooks;

use App\Data\Bank\BankCallbackInput;
use App\Http\Controllers\Controller;
use App\Http\Requests\BankWithdrawalCallbackRequest;
use App\Services\Bank\BankCallbackService;
use Illuminate\Http\JsonResponse;

class BankWebhookController extends Controller
{
    public function __construct(
        private readonly BankCallbackService $bankCallbackService,
    ) {}

    public function withdrawalStatus(BankWithdrawalCallbackRequest $request): JsonResponse
    {
        $result = $this->bankCallbackService->apply(
            BankCallbackInput::fromArray($request->validated()),
        );

        return response()->json([
            'withdrawal_id' => $result->withdrawal->id,
            'status' => $result->withdrawal->status->value,
            'duplicate' => $result->wasDuplicate,
        ]);
    }
}
