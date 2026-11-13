<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\Expense> */
class ExpenseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'bank_account_id' => \App\Models\BankAccount::factory(),
            'description' => 'Pago de vigilancia',
            'paid_on' => '2026-11-01',
            'amount_bs_cents' => 100000,
            'rate_micros' => 100000000,
            'amount_usd_cents' => 1000,
            'fund' => 'operating',
            'idempotency_key' => fake()->unique()->uuid(),
            'request_hash' => str_repeat('a', 64),
            'status' => 'posted',
            'created_by' => \App\Models\User::factory(),
        ];
    }
}
