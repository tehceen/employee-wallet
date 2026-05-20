<?php

namespace Tests\Feature\Api;

use App\Enums\WalletType;
use App\Models\Employee;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeWalletDashboardApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_employees_index_supports_search_and_pagination(): void
    {
        Employee::factory()->create(['name' => 'Alice Example', 'external_ref' => 'emp-alice']);
        Employee::factory()->create(['name' => 'Bob Example', 'external_ref' => 'emp-bob']);

        $this->getJson('/api/employees?search=alice&per_page=10')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.external_ref', 'emp-alice');
    }

    public function test_employee_show_includes_wallets(): void
    {
        $employee = Employee::factory()->create(['external_ref' => 'emp-show']);

        Wallet::factory()->create([
            'employee_id' => $employee->id,
            'type' => WalletType::Salary,
            'balance' => 1_000_00,
        ]);

        $this->getJson("/api/employees/{$employee->id}")
            ->assertOk()
            ->assertJsonPath('external_ref', 'emp-show')
            ->assertJsonCount(1, 'wallets')
            ->assertJsonPath('wallets.0.available_balance', 1_000_00);
    }

    public function test_wallets_index_supports_filters(): void
    {
        $employee = Employee::factory()->create();

        Wallet::factory()->create([
            'employee_id' => $employee->id,
            'type' => WalletType::Salary,
        ]);

        Wallet::factory()->create([
            'employee_id' => $employee->id,
            'type' => WalletType::Savings,
        ]);

        $this->getJson("/api/wallets?employee_id={$employee->id}&type=salary")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'salary');
    }
}
