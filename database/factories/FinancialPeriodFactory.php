<?php

namespace Database\Factories;

use App\Models\FinancialPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<FinancialPeriod> */
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
