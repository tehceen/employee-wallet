<?php

namespace Tests\Feature\Payroll;

use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_webhook_processes_payroll_run(): void
    {
        $employee = Employee::factory()->withSalaryWallet()->create(['external_ref' => 'emp-webhook']);

        $response = $this->postJson('/api/webhooks/payroll/runs', [
            'idempotency_key' => 'webhook-run-1',
            'external_event_id' => 'evt-webhook-1',
            'items' => [
                [
                    'employee_external_ref' => 'emp-webhook',
                    'amount' => 1_500_00,
                    'external_item_id' => 'wh-line-1',
                ],
            ],
        ]);

        $response->assertCreated()
            ->assertJson([
                'duplicate' => false,
                'status' => 'completed',
                'items_processed' => 1,
            ]);

        $this->assertSame(1_500_00, $employee->salaryWallet->fresh()->balance);
    }

    public function test_webhook_returns_duplicate_response_on_replay(): void
    {
        Employee::factory()->withSalaryWallet()->create(['external_ref' => 'emp-replay']);

        $payload = [
            'idempotency_key' => 'webhook-run-dup',
            'items' => [
                ['employee_external_ref' => 'emp-replay', 'amount' => 500_00],
            ],
        ];

        $this->postJson('/api/webhooks/payroll/runs', $payload)->assertCreated();
        $this->postJson('/api/webhooks/payroll/runs', $payload)
            ->assertOk()
            ->assertJson(['duplicate' => true]);
    }
}
