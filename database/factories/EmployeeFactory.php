<?php

namespace Database\Factories;

use App\Enums\WalletType;
use App\Models\Employee;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    protected $model = Employee::class;

    public function definition(): array
    {
        return [
            'external_ref' => fake()->unique()->uuid(),
            'name' => fake()->name(),
        ];
    }

    public function withSalaryWallet(int $balance = 0): static
    {
        return $this->afterCreating(function (Employee $employee) use ($balance): void {
            Wallet::factory()->create([
                'employee_id' => $employee->id,
                'name' => sprintf('%s salary', $employee->name),
                'type' => WalletType::Salary,
                'balance' => $balance,
            ]);
        });
    }
}
