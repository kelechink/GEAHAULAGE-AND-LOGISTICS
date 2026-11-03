<?php

declare(strict_types=1);

use App\Domain\Shipping\ShipmentService;
use App\Domain\Shipping\ShippingException;
use App\Domain\Tracking\TrackingCode;
use App\Enums\Role;
use App\Enums\ShipmentStage;
use App\Enums\TrackingSource;
use App\Enums\TruckStatus;
use App\Mail\ShipmentUpdateMail;
use App\Models\Customer;
use App\Models\Driver;
use App\Models\Shipment;
use App\Models\Truck;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Http::fake(); // Never call Nominatim from tests.
    Mail::fake();
});

function bookShipment(array $overrides = []): Shipment
{
    return app(ShipmentService::class)->create(array_merge([
        'customer_id' => Customer::factory()->create()->id,
        'cargo_description' => 'Bagged cement',
        'tonnage' => 20,
        'origin' => 'Apapa',
        'destination' => 'Kano',
    ], $overrides));
}

/** Walks a shipment from Booked to In transit. */
function toInTransit(Shipment $s, $by): Shipment
{
    $svc = app(ShipmentService::class);
    $svc->assign($s, Truck::factory()->create(['capacity_tonnes' => 30]), null, $by);
    foreach ([ShipmentStage::Loading, ShipmentStage::Loaded, ShipmentStage::InTransit] as $st) {
        $svc->transition($s, $st, TrackingSource::Manual, $by);
    }

    return $s->refresh();
}

it('issues readable tracking codes and geocodes known towns', function () {
    $s = bookShipment();

    expect(TrackingCode::isValid($s->tracking_code))->toBeTrue()
        ->and($s->tracking_code)->toStartWith('GEA-'.now()->format('ym').'-')
        ->and(preg_match('/[01OIL]/', substr($s->tracking_code, -5)))->toBe(0)
        ->and((float) $s->origin_lat)->toEqualWithDelta(6.4474, 0.001)
        ->and($s->stage)->toBe(ShipmentStage::Booked)
        ->and($s->events()->count())->toBe(1);
});

it('accepts codes typed loosely', function () {
    expect(TrackingCode::normalise(' gea2611k7qxm '))->toBe('GEA-2611-K7QXM');
});

it('only offers available trucks big enough for the load', function () {
    $small = Truck::factory()->create(['capacity_tonnes' => 10]);
    $busy = Truck::factory()->create(['capacity_tonnes' => 30, 'status' => 'on_trip']);
    $ok = Truck::factory()->create(['capacity_tonnes' => 25]);
    $s = bookShipment(['tonnage' => 20]);

    expect(app(ShipmentService::class)->eligibleTrucks($s)->pluck('id')->all())->toBe([$ok->id]);
    expect(fn () => app(ShipmentService::class)->assign($s, $small))->toThrow(ShippingException::class, 'carries 10.0 t');
    expect(fn () => app(ShipmentService::class)->assign($s, $busy))->toThrow(ShippingException::class, 'not available');
});

it('assigns the truck and its usual driver, and marks the truck on a trip', function () {
    $truck = Truck::factory()->create(['capacity_tonnes' => 30]);
    $driver = Driver::factory()->create(['truck_id' => $truck->id]);
    $s = bookShipment();

    app(ShipmentService::class)->assign($s, $truck);

    expect($s->refresh()->stage)->toBe(ShipmentStage::Assigned)
        ->and($s->driver_id)->toBe($driver->id)
        ->and($truck->refresh()->status)->toBe(TruckStatus::OnTrip);
});

it('refuses stage jumps the lifecycle does not allow', function () {
    $s = bookShipment();

    expect(fn () => app(ShipmentService::class)->transition($s, ShipmentStage::InTransit, TrackingSource::Manual))
        ->toThrow(ShippingException::class, "can't go from Booked to In transit");
});

it('requires proof of delivery and frees the truck on delivery', function () {
    $ops = $this->userWithRole(Role::Operations);
    $s = toInTransit(bookShipment(), $ops);
    $svc = app(ShipmentService::class);
    $svc->transition($s, ShipmentStage::Arrived, TrackingSource::Manual, $ops);

    expect(fn () => $svc->transition($s, ShipmentStage::Delivered, TrackingSource::Manual, $ops))
        ->toThrow(ShippingException::class, 'Delivery needs proof');

    $svc->transition($s, ShipmentStage::Delivered, TrackingSource::Manual, $ops, null, [], true, ['receiver_name' => 'Store manager']);

    expect($s->refresh()->stage)->toBe(ShipmentStage::Delivered)
        ->and($s->delivered_at)->not->toBeNull()
        ->and($s->proofs()->count())->toBe(1)
        ->and($s->truck?->status)->toBe(TruckStatus::Available);
});

it('needs a reason to delay and lets the trip resume', function () {
    $ops = $this->userWithRole(Role::Operations);
    $s = toInTransit(bookShipment(), $ops);
    $svc = app(ShipmentService::class);

    expect(fn () => $svc->transition($s, ShipmentStage::Delayed, TrackingSource::Manual, $ops))->toThrow(ShippingException::class);
    $svc->transition($s, ShipmentStage::Delayed, TrackingSource::Manual, $ops, 'Bad road at Lokoja');
    $svc->transition($s, ShipmentStage::InTransit, TrackingSource::Manual, $ops);

    expect($s->refresh()->stage)->toBe(ShipmentStage::InTransit)->and($s->delay_reason)->toBe('Bad road at Lokoja');
});

it('lets only operations or management cancel, with a reason', function () {
    $s = bookShipment();
    $svc = app(ShipmentService::class);

    expect(fn () => $svc->transition($s, ShipmentStage::Cancelled, TrackingSource::Manual, $this->userWithRole(Role::Sales), 'No'))
        ->toThrow(ShippingException::class, 'Only Operations or Management');
    expect(fn () => $svc->transition($s, ShipmentStage::Cancelled, TrackingSource::Manual, $this->userWithRole(Role::Operations)))
        ->toThrow(ShippingException::class, 'reason');

    $svc->transition($s, ShipmentStage::Cancelled, TrackingSource::Manual, $this->userWithRole(Role::Management), 'Order withdrawn');
    expect($s->refresh()->stage)->toBe(ShipmentStage::Cancelled);
});

it('records the source of every change', function () {
    $ops = $this->userWithRole(Role::Operations);
    $s = toInTransit(bookShipment(), $ops);

    expect($s->events()->pluck('source')->map->value->unique()->values()->all())->toContain('system', 'manual');
});

it('emails customers who opted in, but not about private updates', function () {
    $ops = $this->userWithRole(Role::Operations);
    $s = bookShipment();
    $s->subscriptions()->create(['email' => 'ada@obifoods.ng']);
    $svc = app(ShipmentService::class);

    $svc->assign($s, Truck::factory()->create(['capacity_tonnes' => 30]), null, $ops);
    Mail::assertQueued(ShipmentUpdateMail::class, 1);

    $svc->transition($s, ShipmentStage::Loading, TrackingSource::Manual, $ops, 'Internal note', [], false);
    Mail::assertQueued(ShipmentUpdateMail::class, 1);
});
