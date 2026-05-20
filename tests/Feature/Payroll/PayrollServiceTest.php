<?php

namespace Tests\Feature\Payroll;

use App\Data\Payroll\PayrollItemInput;
use App\Data\Payroll\PayrollRunInput;
use App\Enums\LedgerEntryType;
use App\Enums\PayrollItemStatus;
use App\Enums\PayrollRunStatus;
use App\Exceptions\EmployeeNotFoundException;
use App\Models\Employee;
use App\Models\LedgerEntry;
use App\Models\PayrollItem;
use App\Models\PayrollRun;
use App\Services\Payroll\PayrollService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollServiceTest extends TestCase
{
    use RefreshDatabase;

    private PayrollService $payrollService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->payrollService = app(PayrollService::class);
    }

    public function test_processes_payroll_run_and_credits_salary_wallets(): void
    {
        $alice = Employee::factory()->withSalaryWallet()->create(['external_ref' => 'emp-alice']);
        $bob = Employee::factory()->withSalaryWallet()->create(['external_ref' => 'emp-bob']);

        $input = new PayrollRunInput(
            idempotencyKey: 'run-2025-05-01',
            items: [
                new PayrollItemInput('emp-alice', 500_00, 'line-1'),
                new PayrollItemInput('emp-bob', 750_00, 'line-2'),
            ],
            externalEventId: 'evt-payroll-001',
        );

        $result = $this->payrollService->process($input);

        $this->assertFalse($result->wasDuplicate);
        $this->assertSame(PayrollRunStatus::Completed, $result->payrollRun->status);
        $this->assertSame(500_00, $alice->salaryWallet->fresh()->balance);
        $this->assertSame(750_00, $bob->salaryWallet->fresh()->balance);
        $this->assertSame(2, PayrollItem::query()->count());
        $this->assertSame(2, LedgerEntry::query()->where('type', LedgerEntryType::PayrollCredit)->count());

        $aliceItem = PayrollItem::query()->where('employee_id', $alice->id)->first();
        $this->assertSame(PayrollItemStatus::Completed, $aliceItem->status);
        $this->assertNotNull($aliceItem->ledger_entry_id);
    }

    public function test_duplicate_idempotency_key_does_not_double_credit(): void
    {
        $employee = Employee::factory()->withSalaryWallet()->create(['external_ref' => 'emp-1']);

        $input = new PayrollRunInput(
            idempotencyKey: 'run-dup-test',
            items: [new PayrollItemInput('emp-1', 1_000_00)],
            externalEventId: 'evt-dup-1',
        );

        $this->payrollService->process($input);
        $duplicate = $this->payrollService->process($input);

        $this->assertTrue($duplicate->wasDuplicate);
        $this->assertSame(1_000_00, $employee->salaryWallet->fresh()->balance);
        $this->assertSame(1, PayrollRun::query()->count());
        $this->assertSame(1, PayrollItem::query()->count());
        $this->assertSame(1, LedgerEntry::query()->count());
    }

    public function test_duplicate_external_event_id_is_treated_as_same_run(): void
    {
        $employee = Employee::factory()->withSalaryWallet()->create(['external_ref' => 'emp-2']);

        $this->payrollService->process(new PayrollRunInput(
            idempotencyKey: 'run-key-a',
            items: [new PayrollItemInput('emp-2', 300_00)],
            externalEventId: 'evt-same',
        ));

        $duplicate = $this->payrollService->process(new PayrollRunInput(
            idempotencyKey: 'run-key-b',
            items: [new PayrollItemInput('emp-2', 300_00)],
            externalEventId: 'evt-same',
        ));

        $this->assertTrue($duplicate->wasDuplicate);
        $this->assertSame(300_00, $employee->salaryWallet->fresh()->balance);
        $this->assertSame(1, LedgerEntry::query()->count());
    }

    public function test_retry_after_partial_failure_only_credits_remaining_items(): void
    {
        $alice = Employee::factory()->withSalaryWallet()->create(['external_ref' => 'emp-alice']);

        $input = new PayrollRunInput(
            idempotencyKey: 'run-partial',
            items: [
                new PayrollItemInput('emp-alice', 200_00, 'line-a'),
                new PayrollItemInput('emp-unknown', 100_00, 'line-b'),
            ],
        );

        try {
            $this->payrollService->process($input);
            $this->fail('Expected EmployeeNotFoundException');
        } catch (EmployeeNotFoundException) {
        }

        $run = PayrollRun::query()->where('idempotency_key', 'run-partial')->first();
        $this->assertSame(PayrollRunStatus::Failed, $run->status);
        $this->assertSame(200_00, $alice->salaryWallet->fresh()->balance);
        $this->assertSame(1, LedgerEntry::query()->count());

        $bob = Employee::factory()->withSalaryWallet()->create(['external_ref' => 'emp-unknown']);

        $retryInput = new PayrollRunInput(
            idempotencyKey: 'run-partial',
            items: [
                new PayrollItemInput('emp-alice', 200_00, 'line-a'),
                new PayrollItemInput('emp-unknown', 100_00, 'line-b'),
            ],
        );

        $result = $this->payrollService->process($retryInput);

        $this->assertFalse($result->wasDuplicate);
        $this->assertSame(PayrollRunStatus::Completed, $result->payrollRun->status);
        $this->assertSame(200_00, $alice->salaryWallet->fresh()->balance);
        $this->assertSame(100_00, $bob->salaryWallet->fresh()->balance);
        $this->assertSame(2, LedgerEntry::query()->count());
    }

    public function test_ledger_entries_include_payroll_metadata(): void
    {
        $employee = Employee::factory()->withSalaryWallet()->create(['external_ref' => 'emp-meta']);

        $this->payrollService->process(new PayrollRunInput(
            idempotencyKey: 'run-meta',
            items: [new PayrollItemInput('emp-meta', 42_00)],
        ));

        $entry = LedgerEntry::query()->first();

        $this->assertSame(LedgerEntryType::PayrollCredit, $entry->type);
        $this->assertArrayHasKey('payroll_run_id', $entry->metadata);
        $this->assertArrayHasKey('payroll_item_id', $entry->metadata);
    }
}
