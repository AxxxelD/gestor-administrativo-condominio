<?php

namespace Database\Factories;

use App\Models\Property;
use App\Models\PropertyCredit;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PropertyCredit> */
class PropertyCreditFactory extends Factory
{
    public function definition(): array
    {
        return [
            'property_id' => Property::factory(),
            'amount_usd_cents' => 1000,
            'credited_on' => '2026-01-01',
            'kind' => 'opening',
            'status' => 'active',
        ];
    }
}
