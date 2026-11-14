<?php

namespace Database\Factories;

use App\Models\FundOpening;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<FundOpening> */
class FundOpeningFactory extends Factory
{
    public function definition(): array
    {
        return [
            'type' => 'reserve',
            'amount_usd_cents' => 0,
            'date' => '2026-01-01',
            'actor_id' => User::factory(),
        ];
    }
}
