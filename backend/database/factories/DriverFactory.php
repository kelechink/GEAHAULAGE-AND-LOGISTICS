<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Driver;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Driver> */
class DriverFactory extends Factory
{
    protected $model = Driver::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'phone' => '23480'.fake()->unique()->numerify('########'),
            'licence_number' => strtoupper(fake()->bothify('???#####??##')),
            'licence_expires_on' => now()->addDays(fake()->numberBetween(30, 900)),
            'has_smartphone' => true,
            'is_active' => true,
        ];
    }
}
