<?php

namespace Database\Factories;

use App\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Property> */
class PropertyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->numerify('TH ##-###'),
            'occupancy_status' => 'occupied',
            'active' => true,
        ];
    }
}
