<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\Party;
use Illuminate\Database\Eloquent\Factories\Factory;

class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    public function definition(): array
    {
        return ['number' => fake()->unique()->bothify('INV-######'), 'party_id' => Party::factory(), 'type' => 'receivable', 'issue_date' => today(), 'due_date' => today()->addDays(14), 'status' => 'draft'];
    }
}
