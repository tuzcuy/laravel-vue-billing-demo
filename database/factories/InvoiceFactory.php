<?php

namespace Database\Factories;

use App\Domain\Billing\InvoiceStatus;
use App\Models\Customer;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Invoice> */
class InvoiceFactory extends Factory
{
    public function definition(): array
    {
        $issue = fake()->dateTimeBetween('-3 months', 'now');

        return [
            'customer_id' => Customer::factory(),
            'number' => sprintf(
                'INV-%s-%04d',
                $issue->format('Y'),
                fake()->unique()->numberBetween(1, 9999),
            ),
            'status' => InvoiceStatus::Draft,
            'currency' => 'EUR',
            'issue_date' => $issue,
            'due_date' => \DateTime::createFromInterface($issue)->modify('+30 days'),
            'notes' => fake()->optional(0.25)->sentence(),
        ];
    }
}
