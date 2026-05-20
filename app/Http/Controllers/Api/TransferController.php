<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTransferRequest;
use App\Models\Wallet;
use App\Services\Wallet\WalletTransferService;
use Illuminate\Http\JsonResponse;

class TransferController extends Controller
{
    public function __construct(
        private readonly WalletTransferService $walletTransferService,
    ) {}

    public function store(StoreTransferRequest $request): JsonResponse
    {
        $fromWallet = Wallet::query()->findOrFail($request->integer('from_wallet_id'));
        $toWallet = Wallet::query()->findOrFail($request->integer('to_wallet_id'));

        $result = $this->walletTransferService->transfer(
            fromWallet: $fromWallet,
            toWallet: $toWallet,
            amount: $request->integer('amount'),
        );

        $fromWallet->refresh();
        $toWallet->refresh();

        return response()->json([
            'from_wallet' => [
                'id' => $fromWallet->id,
                'available_balance' => $fromWallet->availableBalance(),
                'locked_balance' => $fromWallet->locked_balance,
            ],
            'to_wallet' => [
                'id' => $toWallet->id,
                'available_balance' => $toWallet->availableBalance(),
                'locked_balance' => $toWallet->locked_balance,
            ],
            'transfer_out_ledger_id' => $result['debit']->id,
            'transfer_in_ledger_id' => $result['credit']->id,
        ], 201);
    }
}
