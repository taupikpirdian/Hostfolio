<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ServiceFactory extends Factory
{
    public function definition(): array
    {
        return ['code' => fake()->unique()->bothify('SVC-####'), 'name' => fake()->words(2, true), 'slug' => fake()->unique()->slug(), 'type' => 'website', 'status' => 'running'];
    }
}
