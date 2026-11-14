<?php

namespace Database\Factories;

use App\Models\Charge;
use App\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Charge> */
class ChargeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'property_id' => Property::factory(),
            'kind' => 'opening',
            'description' => 'Deuda inicial',
            'charged_on' => '2026-01-01',
            'due_on' => '2026-01-01',
            'principal_usd_cents' => 10000,
            'reserve_usd_cents' => 0,
            'provision_usd_cents' => 0,
        ];
    }
}
