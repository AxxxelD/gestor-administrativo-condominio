<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\Property> */
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
