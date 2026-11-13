<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\FundUse> */
class FundUseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'financial_period_id' => \App\Models\FinancialPeriod::factory(),
            'period_item_id' => \App\Models\PeriodItem::factory(),
            'type' => 'provision',
            'amount_usd_cents' => 1000,
            'used_on' => '2026-11-01',
            'actor_id' => \App\Models\User::factory(),
        ];
    }
}
