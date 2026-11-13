<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\Person> */
class PersonFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'identity_number' => fake()->unique()->numerify('V-########'),
            'email' => fake()->safeEmail(),
            'phone' => '04140000000',
        ];
    }
}
