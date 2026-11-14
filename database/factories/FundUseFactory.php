<?php

namespace Database\Factories;

use App\Models\FinancialPeriod;
use App\Models\FundUse;
use App\Models\PeriodItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<FundUse> */
class FundUseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'financial_period_id' => FinancialPeriod::factory(),
            'period_item_id' => PeriodItem::factory(),
            'type' => 'provision',
            'amount_usd_cents' => 1000,
            'used_on' => '2026-11-01',
            'actor_id' => User::factory(),
        ];
    }
}
