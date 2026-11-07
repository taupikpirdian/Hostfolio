<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class InvoiceItemFactory extends Factory
{
    protected $model = InvoiceItem::class;

    public function definition(): array
    {
        return ['invoice_id' => Invoice::factory(), 'description' => 'Hosting', 'quantity' => '1.0000', 'unit_price' => '800000.00', 'discount' => '0.00', 'tax' => '0.00', 'total' => '800000.00'];
    }
}
