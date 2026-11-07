<?php

namespace Database\Factories;

use App\Models\BillingContract;
use App\Models\Party;
use Illuminate\Database\Eloquent\Factories\Factory;

class BillingContractFactory extends Factory
{
    protected $model = BillingContract::class;

    public function definition(): array
    {
        return ['number' => fake()->unique()->bothify('CTR-######'), 'party_id' => Party::factory(), 'direction' => 'income', 'start_date' => today(), 'billing_cycle' => 'yearly', 'status' => 'active'];
    }
}
