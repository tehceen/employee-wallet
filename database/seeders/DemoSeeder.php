<?php

namespace Database\Seeders;

use App\Enums\WalletType;
use App\Models\Employee;
use App\Models\Wallet;
use Illuminate\Database\Seeder;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $alice = Employee::query()->updateOrCreate(
            ['external_ref' => 'emp-alice'],
            ['name' => 'Alice Example'],
        );

        $bob = Employee::query()->updateOrCreate(
            ['external_ref' => 'emp-bob'],
            ['name' => 'Bob Example'],
        );

        foreach ([$alice, $bob] as $employee) {
            Wallet::query()->updateOrCreate(
                [
                    'employee_id' => $employee->id,
                    'type' => WalletType::Salary,
                ],
                [
                    'name' => sprintf('%s salary', $employee->name),
                    'balance' => 5_000_00,
                    'locked_balance' => 0,
                    'currency' => 'USD',
                ],
            );

            Wallet::query()->updateOrCreate(
                [
                    'employee_id' => $employee->id,
                    'type' => WalletType::Savings,
                ],
                [
                    'name' => sprintf('%s savings', $employee->name),
                    'balance' => 1_000_00,
                    'locked_balance' => 0,
                    'currency' => 'USD',
                ],
            );
        }
    }
}
