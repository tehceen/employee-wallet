<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListWalletsRequest;
use App\Models\Wallet;
use Illuminate\Http\JsonResponse;

class WalletController extends Controller
{
    public function index(ListWalletsRequest $request): JsonResponse
    {
        $query = Wallet::query()
            ->with('employee')
            ->orderBy('id');

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->integer('employee_id'));
        }

        if ($request->filled('type')) {
            $query->where('type', $request->validated('type'));
        }

        $wallets = $query->paginate(
            perPage: $request->integer('per_page', 20),
            page: $request->integer('page', 1),
        );

        return response()->json([
            'data' => $wallets->getCollection()->map(fn (Wallet $wallet): array => [
                'id' => $wallet->id,
                'employee_id' => $wallet->employee_id,
                'employee_name' => $wallet->employee?->name,
                'type' => $wallet->type?->value,
                'name' => $wallet->name,
                'currency' => $wallet->currency,
                'available_balance' => $wallet->availableBalance(),
                'locked_balance' => $wallet->locked_balance,
                'total_balance' => $wallet->totalBalance(),
            ]),
            'meta' => [
                'current_page' => $wallets->currentPage(),
                'per_page' => $wallets->perPage(),
                'total' => $wallets->total(),
                'last_page' => $wallets->lastPage(),
            ],
        ]);
    }
}
