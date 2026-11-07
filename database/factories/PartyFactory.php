<?php

namespace Database\Factories;

use App\Models\Party;
use Illuminate\Database\Eloquent\Factories\Factory;

class PartyFactory extends Factory
{
    protected $model = Party::class;

    public function definition(): array
    {
        return ['name' => fake()->company(), 'type' => 'client', 'status' => 'active'];
    }
}
