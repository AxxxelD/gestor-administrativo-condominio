<?php

namespace Database\Factories;

use App\Models\Occupancy;
use App\Models\Person;
use App\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Occupancy> */
class OccupancyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'property_id' => Property::factory(),
            'person_id' => Person::factory(),
            'relationship' => 'owner',
            'starts_on' => '2026-01-01',
        ];
    }
}
