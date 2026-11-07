<?php

namespace Database\Factories;

use App\Models\ComputeInstance;
use Illuminate\Database\Eloquent\Factories\Factory;

class ResourceAllocationFactory extends Factory
{
    public function definition(): array
    {
        return ['compute_instance_id' => ComputeInstance::factory(), 'effective_from' => now(), 'vcpu' => 2, 'ram_mb' => 2048];
    }
}
