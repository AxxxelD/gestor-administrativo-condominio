<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\Occupancy> */
class OccupancyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'property_id' => \App\Models\Property::factory(),
            'person_id' => \App\Models\Person::factory(),
            'relationship' => 'owner',
            'starts_on' => '2026-01-01',
        ];
    }
}
