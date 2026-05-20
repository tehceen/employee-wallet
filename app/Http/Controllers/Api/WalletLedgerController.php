<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListWalletLedgerRequest;
use App\Models\Wallet;
use Illuminate\Http\JsonResponse;

class WalletLedgerController extends Controller
{
    public function index(ListWalletLedgerRequest $request, Wallet $wallet): JsonResponse
    {
        $query = $wallet->ledgerEntries()->orderByDesc('id');

        if ($request->filled('type')) {
            $query->where('type', $request->validated('type'));
        }

        $entries = $query->paginate(
            perPage: $request->integer('per_page', 20),
            page: $request->integer('page', 1),
        );

        return response()->json([
            'wallet_id' => $wallet->id,
            'data' => $entries->getCollection()->map(fn ($entry): array => [
                'id' => $entry->id,
                'type' => $entry->type->value,
                'amount' => $entry->amount,
                'available_balance_after' => $entry->balance_after,
                'reason' => $entry->reason,
                'metadata' => $entry->metadata,
                'created_at' => $entry->created_at,
            ]),
            'meta' => [
                'current_page' => $entries->currentPage(),
                'per_page' => $entries->perPage(),
                'total' => $entries->total(),
                'last_page' => $entries->lastPage(),
            ],
        ]);
    }
}
