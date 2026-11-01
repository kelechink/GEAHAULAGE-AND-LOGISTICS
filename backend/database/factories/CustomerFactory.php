<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'phone' => '23480'.fake()->numerify('########'),
            'email' => fake()->unique()->companyEmail(),
            'billing_address' => fake()->streetAddress().', Lagos',
            'payment_terms_days' => 30,
        ];
    }
}
