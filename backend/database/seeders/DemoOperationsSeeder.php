<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Shipping\ShipmentService;
use App\Domain\Tracking\PositionData;
use App\Domain\Tracking\PositionRecorder;
use App\Enums\Role;
use App\Enums\ShipmentStage;
use App\Enums\TrackingSource;
use App\Models\Customer;
use App\Models\Driver;
use App\Models\Place;
use App\Models\Shipment;
use App\Models\Truck;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;

/**
 * Demo fleet and trips (PRD 18.2): 35 trucks (5–30 t), 12 drivers (8 with smartphones), 40 shipments.
 */
class DemoOperationsSeeder extends Seeder
{
    public function run(): void
    {
        if (Shipment::query()->exists()) {
            return;
        }

        Event::fake(); // No customer emails from demo data.
        $ops = User::query()->role(Role::Operations->value)->first() ?? User::query()->first();

        $types = ['box_body', 'flatbed', 'lowbed', 'tanker', 'tipper', 'curtainsider', 'container'];
        $caps = [5, 10, 15, 20, 25, 30, 30];
        $trucks = collect();
        for ($i = 0; $i < 35; $i++) {
            $trucks->push(Truck::query()->create([
                'registration' => sprintf('%s-%03d-%s', ['LAG', 'KJA', 'APP', 'EKY', 'KTU'][$i % 5], 101 + $i * 7, ['XA', 'GE', 'KD', 'LT', 'BN', 'AP'][$i % 6]),
                'type' => $types[$i % 7],
                'capacity_tonnes' => $caps[$i % 7],
                'make' => ['MAN', 'Mercedes-Benz', 'DAF', 'Iveco', 'Sinotruk'][$i % 5],
                'year' => 2014 + ($i % 10),
                'tracker_device_id' => $i < 5 ? 'DEMO-TRK-'.(1000 + $i) : null,
                'status' => $i >= 33 ? 'maintenance' : 'available',
                'insurance_expires_on' => now()->addDays($i === 3 ? 6 : 40 + $i * 9),
                'roadworthiness_expires_on' => now()->addDays($i === 8 ? 12 : 60 + $i * 7),
                'licence_expires_on' => now()->addDays(90 + $i * 5),
            ]));
        }

        $names = ['Musa Abdullahi', 'Chukwuma Obi', 'Babatunde Ade', 'Ibrahim Yusuf', 'Emeka Nnaji', 'Sani Garba',
            'Olumide Bello', 'Peter Okon', 'Usman Lawal', 'Segun Ajayi', 'Godspower Eze', 'Bala Mohammed'];
        foreach ($names as $i => $name) {
            $driver = Driver::query()->create([
                'name' => $name,
                'phone' => '2348'.str_pad((string) (31100000 + $i * 104729), 9, '0', STR_PAD_LEFT),
                'licence_number' => sprintf('LAG%05dAA%02d', 10234 + $i * 17, 20 + $i),
                'licence_expires_on' => now()->addDays($i === 2 ? 9 : 200 + $i * 30),
                'truck_id' => $trucks[$i]->id,
                'has_smartphone' => $i < 8,
                'guarantor_name' => 'Guarantor '.($i + 1),
            ]);
            if ($i < 8) {
                $driver->forceFill(['pin_hash' => Hash::make('1234')])->save(); // demo app PIN, local only
            }
        }

        $routes = [['Apapa', 'Kano'], ['Ikeja', 'Abuja'], ['Apapa', 'Port Harcourt'], ['Lagos', 'Onitsha'], ['Ikeja', 'Ibadan'],
            ['Apapa', 'Kaduna'], ['Lagos', 'Benin City'], ['Alagbado', 'Enugu'], ['Apapa', 'Jos'], ['Lagos', 'Ilorin']];
        $cargo = ['Bagged cement', '40ft container', 'Diesel (AGO)', 'Bagged rice', 'Excavator', 'Fertiliser', 'Steel rods', 'Beverages'];
        $plan = array_merge(
            array_fill(0, 4, ShipmentStage::Booked), array_fill(0, 3, ShipmentStage::Assigned), array_fill(0, 2, ShipmentStage::Loading),
            array_fill(0, 2, ShipmentStage::Loaded), array_fill(0, 9, ShipmentStage::InTransit), array_fill(0, 2, ShipmentStage::Delayed),
            array_fill(0, 3, ShipmentStage::Arrived), array_fill(0, 10, ShipmentStage::Delivered), array_fill(0, 4, ShipmentStage::Closed),
            [ShipmentStage::Cancelled],
        );

        $service = app(ShipmentService::class);
        $recorder = app(PositionRecorder::class);
        $customers = Customer::query()->pluck('id')->all();
        $places = Place::query()->get()->keyBy('name');
        $truckIdx = 0;

        foreach ($plan as $i => $target) {
            [$from, $to] = $routes[$i % count($routes)];
            $o = $places[$from];
            $d = $places[$to];
            $tonnage = [5, 10, 15, 20, 25, 30][$i % 6];

            $shipment = $service->create([
                'customer_id' => $customers[$i % count($customers)],
                'cargo_description' => $cargo[$i % count($cargo)],
                'tonnage' => $tonnage,
                'origin' => $o->label(), 'origin_lat' => $o->lat, 'origin_lng' => $o->lng,
                'destination' => $d->label(), 'destination_lat' => $d->lat, 'destination_lng' => $d->lng,
                'pickup_date' => now()->subDays(max(0, 20 - $i))->toDateString(),
                'waybill_number' => 'WB-'.(52000 + $i),
            ], $ops);

            if ($target === ShipmentStage::Booked) {
                continue;
            }
            if ($target === ShipmentStage::Cancelled) {
                $service->transition($shipment, ShipmentStage::Cancelled, TrackingSource::Manual, $ops, 'Customer postponed the order.');

                continue;
            }

            $truck = $service->eligibleTrucks($shipment)->first();
            if ($truck === null) {
                continue;
            }
            $service->assign($shipment, $truck, null, $ops);

            $path = [ShipmentStage::Loading, ShipmentStage::Loaded, ShipmentStage::InTransit, ShipmentStage::Arrived, ShipmentStage::Delivered, ShipmentStage::Closed];
            $finalIndex = $target === ShipmentStage::Delayed ? 2 : array_search($target, $path, true);
            if ($finalIndex === false) {
                continue;
            }
            for ($k = 0; $k <= $finalIndex; $k++) {
                $stage = $path[$k];
                if ($stage === ShipmentStage::InTransit || ($stage === ShipmentStage::Arrived)) {
                    // Positions along the straight line, ending further along for later stages.
                    $steps = $stage === ShipmentStage::Arrived ? 10 : 3 + ($i % 6);
                    for ($s = 1; $s <= $steps; $s++) {
                        $f = $s / 10;
                        $recorder->record($shipment, new PositionData(
                            lat: (float) $o->lat + ((float) $d->lat - (float) $o->lat) * $f,
                            lng: (float) $o->lng + ((float) $d->lng - (float) $o->lng) * $f,
                            recordedAt: now()->subMinutes(($steps - $s) * 45 + ($i === 12 ? 120 : 5)),
                            speedKmh: 52,
                        ), $i % 3 === 0 ? TrackingSource::Manual : TrackingSource::DriverApp);
                    }
                }
                $proof = $stage === ShipmentStage::Delivered ? ['receiver_name' => 'Store manager'] : null;
                $service->transition($shipment, $stage, TrackingSource::Manual, $ops, $stage === ShipmentStage::Delivered ? 'Delivered and signed for.' : null, [], true, $proof);
            }
            if ($target === ShipmentStage::Delayed) {
                $service->transition($shipment, ShipmentStage::Delayed, TrackingSource::Manual, $ops, 'Heavy traffic at Ore.');
            }
            $truckIdx++;
        }
    }
}
