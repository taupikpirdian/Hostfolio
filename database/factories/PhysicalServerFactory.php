<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class PhysicalServerFactory extends Factory
{
    public function definition(): array
    {
        return ['code' => fake()->unique()->bothify('HOST-####'), 'name' => fake()->domainWord(), 'status' => 'active', 'cpu_cores' => 8, 'ram_mb' => 16384, 'storage_gb' => 500];
    }
}
