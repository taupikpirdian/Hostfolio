<?php

namespace Database\Factories;

use App\Models\BillingContract;
use App\Models\ContractItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class ContractItemFactory extends Factory
{
    protected $model = ContractItem::class;

    public function definition(): array
    {
        return ['billing_contract_id' => BillingContract::factory(), 'description' => 'Hosting', 'quantity' => '1.0000', 'unit_price' => '800000.00', 'effective_from' => today()];
    }
}
