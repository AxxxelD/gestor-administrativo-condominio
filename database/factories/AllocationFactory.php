<?php

namespace Database\Factories;

use App\Models\Allocation;
use App\Models\Charge;
use App\Models\PropertyCredit;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Allocation> */
class AllocationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'property_credit_id' => PropertyCredit::factory(),
            'charge_id' => Charge::factory(),
            'principal_usd_cents' => 1000,
            'reserve_usd_cents' => 0,
            'provision_usd_cents' => 0,
            'applied_on' => '2026-11-01',
        ];
    }
}
