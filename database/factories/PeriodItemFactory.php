<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\PeriodItem> */
class PeriodItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'financial_period_id' => \App\Models\FinancialPeriod::factory(),
            'category' => 'sector_fixed',
            'description' => 'Servicio de vigilancia',
            'amount_usd_cents' => 90000,
            'is_provision' => false,
        ];
    }
}
