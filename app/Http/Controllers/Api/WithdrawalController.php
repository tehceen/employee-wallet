<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWithdrawalRequest;
use App\Models\Wallet;
use App\Services\Bank\BankWithdrawalService;
use Illuminate\Http\JsonResponse;

class WithdrawalController extends Controller
{
    public function __construct(
        private readonly BankWithdrawalService $bankWithdrawalService,
    ) {}

    public function store(StoreWithdrawalRequest $request, Wallet $wallet): JsonResponse
    {
        $result = $this->bankWithdrawalService->initiate(
            wallet: $wallet,
            amount: (int) $request->validated('amount'),
            idempotencyKey: $request->validated('idempotency_key'),
        );

        $wallet->refresh();

        return response()->json([
            'withdrawal_id' => $result->withdrawal->id,
            'status' => $result->withdrawal->status->value,
            'bank_reference' => $result->withdrawal->bank_reference,
            'duplicate' => $result->wasDuplicate,
            'wallet' => [
                'available_balance' => $wallet->availableBalance(),
                'locked_balance' => $wallet->locked_balance,
                'total_balance' => $wallet->totalBalance(),
            ],
        ], $result->wasDuplicate ? 200 : 202);
    }

    public function show(Wallet $wallet, int $withdrawalId): JsonResponse
    {
        $withdrawal = $wallet->bankWithdrawals()->findOrFail($withdrawalId);

        return response()->json([
            'withdrawal_id' => $withdrawal->id,
            'status' => $withdrawal->status->value,
            'amount' => $withdrawal->amount,
            'bank_reference' => $withdrawal->bank_reference,
            'failure_reason' => $withdrawal->failure_reason,
            'completed_at' => $withdrawal->completed_at,
        ]);
    }
}
