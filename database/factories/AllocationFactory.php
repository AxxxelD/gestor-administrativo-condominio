<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\Allocation> */
class AllocationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'property_credit_id' => \App\Models\PropertyCredit::factory(),
            'charge_id' => \App\Models\Charge::factory(),
            'principal_usd_cents' => 1000,
            'reserve_usd_cents' => 0,
            'provision_usd_cents' => 0,
            'applied_on' => '2026-11-01',
        ];
    }
}
