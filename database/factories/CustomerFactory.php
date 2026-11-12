<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Customer> */
class CustomerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'company' => fake()->optional(0.8)->company(),
            'country' => fake()->randomElement(['DE', 'TR', 'GB', 'NL', 'FR', 'ES', 'IT', 'US', 'SE', 'PL']),
        ];
    }
}
