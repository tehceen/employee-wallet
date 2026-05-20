<?php

namespace App\Services\Bank;

use App\Data\Bank\WithdrawalRequestResult;
use App\Enums\BankWithdrawalStatus;
use App\Jobs\SubmitBankWithdrawalJob;
use App\Models\BankWithdrawal;
use App\Models\Wallet;
use App\Services\Wallet\WalletService;
use App\Support\LedgerReason;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class BankWithdrawalService
{
    public function __construct(
        private readonly WalletService $walletService,
    ) {}

    public function initiate(Wallet $wallet, int $amount, string $idempotencyKey): WithdrawalRequestResult
    {
        if (trim($idempotencyKey) === '') {
            throw new InvalidArgumentException('Idempotency key must not be empty.');
        }

        try {
            $withdrawal = DB::transaction(function () use ($wallet, $amount, $idempotencyKey): BankWithdrawal {
                $withdrawal = BankWithdrawal::query()->create([
                    'wallet_id' => $wallet->id,
                    'employee_id' => $wallet->employee_id,
                    'amount' => $amount,
                    'status' => BankWithdrawalStatus::Pending,
                    'idempotency_key' => $idempotencyKey,
                    'requested_at' => now(),
                ]);

                $holdEntry = $this->walletService->holdForWithdrawal(
                    wallet: $wallet,
                    amount: $amount,
                    reason: LedgerReason::withdrawalHold($withdrawal->id),
                    metadata: ['bank_withdrawal_id' => $withdrawal->id],
                );

                $withdrawal->update(['hold_ledger_entry_id' => $holdEntry->id]);

                SubmitBankWithdrawalJob::dispatch($withdrawal->id)->afterCommit();

                return $withdrawal;
            });

            return WithdrawalRequestResult::created($withdrawal->fresh());
        } catch (UniqueConstraintViolationException) {
            $withdrawal = BankWithdrawal::query()
                ->where('idempotency_key', $idempotencyKey)
                ->firstOrFail();

            $this->ensureSubmissionJobQueued($withdrawal);

            return WithdrawalRequestResult::duplicate($withdrawal);
        }
    }

    private function ensureSubmissionJobQueued(BankWithdrawal $withdrawal): void
    {
        if ($withdrawal->status !== BankWithdrawalStatus::Pending) {
            return;
        }

        SubmitBankWithdrawalJob::dispatch($withdrawal->id);
    }
}
