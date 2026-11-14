<?php

namespace Database\Factories;

use App\Models\BankAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BankAccount> */
class BankAccountFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'opening_balance_bs_cents' => 0,
            'active' => true,
        ];
    }
}
