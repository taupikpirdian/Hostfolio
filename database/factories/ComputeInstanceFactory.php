<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ComputeInstanceFactory extends Factory
{
    public function definition(): array
    {
        return ['code' => fake()->unique()->bothify('VM-####'), 'name' => fake()->domainWord(), 'type' => 'vm', 'status' => 'active'];
    }
}
