<?php

namespace Tests\Feature\Api;

use App\Enums\WalletType;
use App\Models\Employee;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransferApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_transfer_moves_funds_between_same_employee_wallets(): void
    {
        $employee = Employee::factory()->create();

        $salary = Wallet::factory()->create([
            'employee_id' => $employee->id,
            'type' => WalletType::Salary,
            'balance' => 1_000_00,
        ]);

        $savings = Wallet::factory()->create([
            'employee_id' => $employee->id,
            'type' => WalletType::Savings,
            'balance' => 200_00,
        ]);

        $this->postJson('/api/wallets/transfers', [
            'from_wallet_id' => $salary->id,
            'to_wallet_id' => $savings->id,
            'amount' => 300_00,
        ])
            ->assertCreated()
            ->assertJsonPath('from_wallet.available_balance', 700_00)
            ->assertJsonPath('to_wallet.available_balance', 500_00);

        $this->assertDatabaseHas('ledger_entries', [
            'wallet_id' => $salary->id,
            'type' => 'transfer_out',
            'amount' => -300_00,
        ]);
    }

    public function test_transfer_rejects_different_employees(): void
    {
        $from = Wallet::factory()->create([
            'employee_id' => Employee::factory(),
            'balance' => 500_00,
        ]);

        $to = Wallet::factory()->create([
            'employee_id' => Employee::factory(),
            'balance' => 0,
        ]);

        $this->postJson('/api/wallets/transfers', [
            'from_wallet_id' => $from->id,
            'to_wallet_id' => $to->id,
            'amount' => 100_00,
        ])->assertUnprocessable();
    }
}
