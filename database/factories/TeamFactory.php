<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class TeamFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'power' => fake()->numberBetween(60, 95),
            'pot' => fake()->numberBetween(1, 4),
        ];
    }
}
