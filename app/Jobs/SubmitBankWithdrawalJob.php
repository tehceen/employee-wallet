<?php

namespace App\Jobs;

use App\Data\Bank\BankCallbackInput;
use App\Enums\BankWithdrawalStatus;
use App\Models\BankWithdrawal;
use App\Services\Bank\BankCallbackService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SubmitBankWithdrawalJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $bankWithdrawalId,
    ) {}

    public function handle(BankCallbackService $bankCallbackService): void
    {
        $bankReference = DB::transaction(function (): ?string {
            $withdrawal = BankWithdrawal::query()
                ->whereKey($this->bankWithdrawalId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($withdrawal->status !== BankWithdrawalStatus::Pending) {
                return null;
            }

            $bankReference = (string) Str::uuid();

            $withdrawal->update([
                'status' => BankWithdrawalStatus::Processing,
                'bank_reference' => $bankReference,
            ]);

            return $bankReference;
        });

        if ($bankReference === null) {
            return;
        }

        if (! (bool) config('bank.auto_callback', true)) {
            return;
        }

        usleep(50_000);

        $shouldSucceed = (bool) config('bank.simulate_success', true);

        $bankCallbackService->apply(new BankCallbackInput(
            bankReference: $bankReference,
            status: $shouldSucceed ? 'completed' : 'failed',
            idempotencyKey: sprintf('bank-callback-%s-%s', $bankReference, $shouldSucceed ? 'completed' : 'failed'),
            externalEventId: sprintf('evt-%s', $bankReference),
            failureReason: $shouldSucceed ? null : 'Simulated bank rejection',
        ));
    }
}
