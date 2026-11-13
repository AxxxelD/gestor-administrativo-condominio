<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\FinancialPeriod> */
class FinancialPeriodFactory extends Factory
{
    public function definition(): array
    {
        return [
            'month' => '2026-11',
            'status' => 'draft',
            'reserve_basis_points' => 1000,
            'units_count' => 90,
        ];
    }
}
