<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\PropertyCredit> */
class PropertyCreditFactory extends Factory
{
    public function definition(): array
    {
        return [
            'property_id' => \App\Models\Property::factory(),
            'amount_usd_cents' => 1000,
            'credited_on' => '2026-01-01',
            'kind' => 'opening',
            'status' => 'active',
        ];
    }
}
