<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<InvoiceItem> */
class InvoiceItemFactory extends Factory
{
    private const SERVICES = [
        'Consulting hours',
        'UI design sprint',
        'API integration work',
        'Monthly hosting plan',
        'Support & maintenance retainer',
        'Data migration',
        'Performance audit',
        'Technical workshop (1 day)',
        'Landing page build',
        'Automated test suite setup',
    ];

    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'description' => fake()->randomElement(self::SERVICES),
            'quantity' => fake()->numberBetween(1, 12),
            'unit_price_cents' => fake()->numberBetween(10, 2500) * 100,
        ];
    }
}
