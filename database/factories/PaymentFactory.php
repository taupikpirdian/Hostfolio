<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return ['invoice_id' => Invoice::factory()->state(['status' => 'issued', 'total' => '800000.00']), 'paid_at' => today(), 'amount' => '300000.00', 'method' => 'transfer'];
    }
}
