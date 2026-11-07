<?php

namespace Database\Factories;

use App\Models\ComputeInstance;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

class ServiceDeploymentFactory extends Factory
{
    public function definition(): array
    {
        return ['service_id' => Service::factory(), 'compute_instance_id' => ComputeInstance::factory(), 'environment' => 'production', 'deployed_at' => now()];
    }
}
