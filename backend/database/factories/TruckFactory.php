<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Truck;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Truck> */
class TruckFactory extends Factory
{
    protected $model = Truck::class;

    public function definition(): array
    {
        return [
            'registration' => strtoupper(fake()->unique()->bothify('LAG-###-??')),
            'type' => fake()->randomElement(array_keys(Truck::TYPES)),
            'capacity_tonnes' => fake()->randomElement([5, 10, 15, 20, 25, 30]),
            'make' => fake()->randomElement(['MAN', 'Mercedes-Benz', 'DAF', 'Iveco', 'Sinotruk']),
            'year' => fake()->numberBetween(2012, 2024),
            'status' => 'available',
            'insurance_expires_on' => now()->addDays(fake()->numberBetween(10, 365)),
            'roadworthiness_expires_on' => now()->addDays(fake()->numberBetween(10, 365)),
            'licence_expires_on' => now()->addDays(fake()->numberBetween(10, 365)),
        ];
    }
}
