<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Payment> */
class PaymentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'amount_cents' => fake()->numberBetween(10, 1000) * 100,
            'method' => fake()->randomElement(Payment::METHODS),
            'paid_at' => fake()->dateTimeBetween('-2 months', 'now'),
            'reference' => fake()->optional(0.6)->bothify('TRX-########'),
        ];
    }
}
