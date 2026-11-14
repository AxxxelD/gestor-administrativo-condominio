<?php

namespace Database\Factories;

use App\Models\BankAccount;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Payment> */
class PaymentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'bank_account_id' => BankAccount::factory(),
            'paid_on' => '2026-11-01',
            'amount_bs_cents' => 100000,
            'rate_micros' => 100000000,
            'amount_usd_cents' => 1000,
            'reference' => fake()->unique()->uuid(),
            'idempotency_key' => fake()->unique()->uuid(),
            'request_hash' => str_repeat('a', 64),
            'status' => 'posted',
            'created_by' => User::factory(),
        ];
    }
}
