<?php

namespace Database\Factories;

use App\Enums\WalletType;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Wallet>
 */
class WalletFactory extends Factory
{
    protected $model = Wallet::class;

    public function definition(): array
    {
        return [
            'employee_id' => null,
            'name' => fake()->unique()->words(2, true),
            'type' => null,
            'balance' => 0,
            'locked_balance' => 0,
            'currency' => 'USD',
        ];
    }

    public function withBalance(int $balance): static
    {
        return $this->state(fn (): array => [
            'balance' => $balance,
        ]);
    }
}
