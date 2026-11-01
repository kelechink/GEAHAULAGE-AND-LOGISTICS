<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Tracking\TrackingCode;
use App\Models\Customer;
use App\Models\Shipment;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Shipment> */
class ShipmentFactory extends Factory
{
    protected $model = Shipment::class;

    public function definition(): array
    {
        return [
            'tracking_code' => TrackingCode::generate(),
            'customer_id' => Customer::factory(),
            'cargo_description' => 'Bagged cement',
            'tonnage' => 20,
            'origin' => 'Apapa, Lagos',
            'origin_lat' => 6.4474,
            'origin_lng' => 3.3640,
            'destination' => 'Kano',
            'destination_lat' => 12.0022,
            'destination_lng' => 8.5920,
            'pickup_date' => now()->toDateString(),
            'stage' => 'booked',
        ];
    }
}
