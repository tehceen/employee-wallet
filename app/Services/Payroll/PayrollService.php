<?php

namespace App\Services\Payroll;

use App\Data\Payroll\PayrollItemInput;
use App\Data\Payroll\PayrollRunInput;
use App\Data\Payroll\PayrollRunResult;
use App\Enums\LedgerEntryType;
use App\Enums\PayrollItemStatus;
use App\Enums\PayrollRunStatus;
use App\Enums\WalletType;
use App\Exceptions\EmployeeNotFoundException;
use App\Exceptions\PayrollAmountMismatchException;
use App\Exceptions\SalaryWalletNotFoundException;
use App\Models\Employee;
use App\Models\PayrollItem;
use App\Models\PayrollRun;
use App\Models\Wallet;
use App\Services\Wallet\WalletService;
use App\Support\LedgerReason;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class PayrollService
{
    public function __construct(
        private readonly WalletService $walletService,
    ) {}

    public function process(PayrollRunInput $input): PayrollRunResult
    {
        $this->assertHasItems($input);

        $run = $this->resolveOrCreateRun($input);

        if ($run->isCompleted()) {
            return PayrollRunResult::duplicate($run->load('items'));
        }

        try {
            $this->executeRun($run, $input);
        } catch (Throwable $exception) {
            $run->update([
                'status' => PayrollRunStatus::Failed,
                'error_message' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        return PayrollRunResult::processed($run->fresh(['items.ledgerEntry']));
    }

    private function executeRun(PayrollRun $run, PayrollRunInput $input): void
    {
        $shouldProcessItems = DB::transaction(function () use ($run): bool {
            $lockedRun = PayrollRun::query()
                ->whereKey($run->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedRun->isCompleted()) {
                return false;
            }

            if (! $lockedRun->isProcessing()) {
                $lockedRun->update(['status' => PayrollRunStatus::Processing]);
            }

            return true;
        });

        if (! $shouldProcessItems) {
            return;
        }

        foreach ($input->items as $itemInput) {
            $this->processItem($run, $itemInput);
        }

        DB::transaction(function () use ($run, $input): void {
            $lockedRun = PayrollRun::query()
                ->whereKey($run->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedRun->isCompleted()) {
                return;
            }

            $this->assertAllInputItemsCompleted($lockedRun, $input);

            $lockedRun->update([
                'status' => PayrollRunStatus::Completed,
                'processed_at' => now(),
                'error_message' => null,
            ]);
        });

        $run->refresh();
    }

    private function processItem(PayrollRun $run, PayrollItemInput $itemInput): void
    {
        DB::transaction(function () use ($run, $itemInput): void {
            $employee = Employee::query()
                ->where('external_ref', $itemInput->employeeExternalRef)
                ->first();

            if ($employee === null) {
                throw new EmployeeNotFoundException($itemInput->employeeExternalRef);
            }

            $payrollItem = $this->resolveOrCreateItem($run, $employee, $itemInput);

            $payrollItem = PayrollItem::query()
                ->whereKey($payrollItem->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($payrollItem->amount !== $itemInput->amount) {
                throw new PayrollAmountMismatchException(
                    payrollItemId: $payrollItem->id,
                    existingAmount: $payrollItem->amount,
                    requestedAmount: $itemInput->amount,
                );
            }

            if ($payrollItem->isCompleted()) {
                return;
            }

            $wallet = Wallet::query()
                ->where('employee_id', $employee->id)
                ->where('type', WalletType::Salary)
                ->first();

            if ($wallet === null) {
                throw new SalaryWalletNotFoundException($employee);
            }

            $ledgerEntry = $this->walletService->credit(
                wallet: $wallet,
                amount: $itemInput->amount,
                reason: LedgerReason::payrollCredit($run->idempotency_key, $itemInput->employeeExternalRef),
                type: LedgerEntryType::PayrollCredit,
                metadata: [
                    'payroll_run_id' => $run->id,
                    'payroll_item_id' => $payrollItem->id,
                    'employee_external_ref' => $itemInput->employeeExternalRef,
                ],
            );

            $payrollItem->update([
                'status' => PayrollItemStatus::Completed,
                'ledger_entry_id' => $ledgerEntry->id,
                'processed_at' => now(),
                'error_message' => null,
            ]);
        });
    }

    private function resolveOrCreateRun(PayrollRunInput $input): PayrollRun
    {
        try {
            return PayrollRun::query()->create([
                'idempotency_key' => $input->idempotencyKey,
                'external_event_id' => $input->externalEventId,
                'status' => PayrollRunStatus::Received,
                'payload' => $input->toPayload(),
            ]);
        } catch (UniqueConstraintViolationException) {
            return $this->findExistingRun($input);
        }
    }

    private function findExistingRun(PayrollRunInput $input): PayrollRun
    {
        $run = PayrollRun::query()
            ->where('idempotency_key', $input->idempotencyKey)
            ->first();

        if ($run !== null) {
            return $run;
        }

        if ($input->externalEventId !== null) {
            $run = PayrollRun::query()
                ->where('external_event_id', $input->externalEventId)
                ->first();

            if ($run !== null) {
                return $run;
            }
        }

        throw new RuntimeException('Payroll run idempotency conflict could not be resolved.');
    }

    private function resolveOrCreateItem(
        PayrollRun $run,
        Employee $employee,
        PayrollItemInput $itemInput,
    ): PayrollItem {
        try {
            return PayrollItem::query()->create([
                'payroll_run_id' => $run->id,
                'employee_id' => $employee->id,
                'external_item_id' => $itemInput->externalItemId,
                'amount' => $itemInput->amount,
                'status' => PayrollItemStatus::Pending,
            ]);
        } catch (UniqueConstraintViolationException) {
            return PayrollItem::query()
                ->where('payroll_run_id', $run->id)
                ->where('employee_id', $employee->id)
                ->firstOrFail();
        }
    }

    private function assertAllInputItemsCompleted(PayrollRun $run, PayrollRunInput $input): void
    {
        $employeeIds = Employee::query()
            ->whereIn('external_ref', array_map(
                fn (PayrollItemInput $item): string => $item->employeeExternalRef,
                $input->items,
            ))
            ->pluck('id');

        $completedCount = PayrollItem::query()
            ->where('payroll_run_id', $run->id)
            ->whereIn('employee_id', $employeeIds)
            ->where('status', PayrollItemStatus::Completed)
            ->count();

        if ($completedCount !== count($input->items)) {
            throw new RuntimeException('Payroll run cannot be completed until all items are credited.');
        }
    }

    private function assertHasItems(PayrollRunInput $input): void
    {
        if ($input->items === []) {
            throw new InvalidArgumentException('Payroll run must include at least one item.');
        }
    }
}
