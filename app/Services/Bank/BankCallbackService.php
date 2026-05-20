<?php

namespace App\Services\Bank;

use App\Data\Bank\BankCallbackInput;
use App\Data\Bank\BankCallbackResult;
use App\Exceptions\BankWithdrawalNotFoundException;
use App\Enums\BankWithdrawalStatus;
use App\Models\BankCallback;
use App\Models\BankWithdrawal;
use App\Services\Wallet\WalletService;
use App\Support\LedgerReason;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class BankCallbackService
{
    public function __construct(
        private readonly WalletService $walletService,
    ) {}

    public function apply(BankCallbackInput $input): BankCallbackResult
    {
        $this->assertValidStatus($input);

        return DB::transaction(function () use ($input): BankCallbackResult {
            $withdrawal = BankWithdrawal::query()
                ->where('bank_reference', $input->bankReference)
                ->lockForUpdate()
                ->first();

            if ($withdrawal === null) {
                throw new BankWithdrawalNotFoundException($input->bankReference);
            }

            if ($withdrawal->isTerminal()) {
                return BankCallbackResult::duplicate($withdrawal);
            }

            try {
                BankCallback::query()->create([
                    'bank_withdrawal_id' => $withdrawal->id,
                    'idempotency_key' => $input->idempotencyKey,
                    'external_event_id' => $input->externalEventId,
                    'bank_reference' => $input->bankReference,
                    'status' => $input->status,
                    'payload' => $input->toPayload(),
                    'processed_at' => now(),
                ]);
            } catch (UniqueConstraintViolationException) {
                return BankCallbackResult::duplicate($withdrawal->fresh());
            }

            $wallet = $withdrawal->wallet;

            if ($input->isSuccess()) {
                $settleEntry = $this->walletService->finalizeWithdrawalHold(
                    wallet: $wallet,
                    amount: $withdrawal->amount,
                    reason: LedgerReason::withdrawalSettled($withdrawal->id),
                    metadata: ['bank_withdrawal_id' => $withdrawal->id],
                );

                $withdrawal->update([
                    'status' => BankWithdrawalStatus::Completed,
                    'settle_ledger_entry_id' => $settleEntry->id,
                    'completed_at' => now(),
                    'failure_reason' => null,
                ]);
            } else {
                $releaseEntry = $this->walletService->releaseWithdrawalHold(
                    wallet: $wallet,
                    amount: $withdrawal->amount,
                    reason: LedgerReason::withdrawalRelease($withdrawal->id),
                    metadata: ['bank_withdrawal_id' => $withdrawal->id],
                );

                $withdrawal->update([
                    'status' => BankWithdrawalStatus::Failed,
                    'release_ledger_entry_id' => $releaseEntry->id,
                    'completed_at' => now(),
                    'failure_reason' => $input->failureReason ?? 'Bank rejected withdrawal',
                ]);
            }

            return BankCallbackResult::processed($withdrawal->fresh());
        });
    }

    private function assertValidStatus(BankCallbackInput $input): void
    {
        if (! $input->isSuccess() && ! $input->isFailure()) {
            throw new InvalidArgumentException('Callback status must be completed or failed.');
        }
    }
}
