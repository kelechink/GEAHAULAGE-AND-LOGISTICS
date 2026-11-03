<?php

declare(strict_types=1);

use App\Domain\Shipping\ShipmentService;
use App\Domain\Tracking\EtaEstimator;
use App\Domain\Tracking\Geocoder;
use App\Domain\Tracking\ManualUpdate;
use App\Domain\Tracking\PositionData;
use App\Domain\Tracking\PositionRecorder;
use App\Enums\Role;
use App\Enums\ShipmentStage;
use App\Enums\TrackingSource;
use App\Filament\Pages\Integrations;
use App\Models\Customer;
use App\Models\Driver;
use App\Models\Place;
use App\Models\Position;
use App\Models\Shipment;
use App\Models\TrackingConnector;
use App\Models\Truck;
use App\Notifications\StaleTripNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Http::preventStrayRequests(); // Each test fakes the hosts it needs.
    Mail::fake();
});

function trip(array $truck = []): Shipment
{
    $ops = test()->userWithRole(Role::Operations);
    $svc = app(ShipmentService::class);
    $s = $svc->create(['customer_id' => Customer::factory()->create(['name' => 'Obi Foods Ltd'])->id, 'cargo_description' => 'Rice', 'tonnage' => 20,
        'origin' => 'Apapa', 'destination' => 'Kano', 'notes' => 'SECRET PRICE 950000']);
    $t = Truck::factory()->create(['capacity_tonnes' => 30, 'registration' => 'LAG-123-XY'] + $truck);
    Driver::factory()->create(['truck_id' => $t->id, 'phone' => '2348099887766']);
    $svc->assign($s, $t, null, $ops);
    foreach ([ShipmentStage::Loading, ShipmentStage::Loaded, ShipmentStage::InTransit] as $st) {
        $svc->transition($s, $st, TrackingSource::Manual, $ops);
    }

    return $s->refresh();
}

it('merges positions from any source, newest wins, and ignores replayed batches', function () {
    $s = trip();
    $rec = app(PositionRecorder::class);

    $rec->record($s, new PositionData(7.0, 4.0, now()->subMinutes(5), clientEventId: 'evt-1'), TrackingSource::DriverApp);
    $rec->record($s, new PositionData(7.0, 4.0, now()->subMinutes(5), clientEventId: 'evt-1'), TrackingSource::DriverApp);
    $rec->record($s, new PositionData(6.9, 3.9, now()->subMinutes(30)), TrackingSource::Manual);

    expect($s->positions()->count())->toBe(2)
        ->and($s->refresh()->last_update_at?->diffInMinutes(now()))->toBeLessThan(6)
        ->and((float) $s->latestPosition()->first()?->lat)->toBe(7.0);
});

it('rejects implausible coordinates', function () {
    expect(app(PositionRecorder::class)->record(trip(), new PositionData(0.0, 0.0, now()), TrackingSource::DriverApp))->toBeNull();
});

it('labels GPS points with the nearest known town', function () {
    $s = trip();
    $ore = Place::query()->where('name', 'Ore')->firstOrFail();
    $p = app(PositionRecorder::class)->record($s, new PositionData((float) $ore->lat + 0.02, (float) $ore->lng, now()), TrackingSource::DriverApp);

    expect($p?->place_label)->toBe('Ore, Ondo');
});

it('applies a two-click console update with a checkpoint', function () {
    $s = trip();
    $ore = Place::query()->where('name', 'Ore')->firstOrFail();

    app(ManualUpdate::class)->apply($s, $this->userWithRole(Role::Operations), null, $ore->id, null, null, 'Driver called from Ore, 2:15pm');

    expect($s->positions()->latest('recorded_at')->first()?->place_label)->toBe('Ore, Ondo')
        ->and($s->events()->get()->last()?->note)->toBe('Driver called from Ore, 2:15pm');
});

it('shows a public tracking page without price, driver phone, full plate or private notes', function () {
    $s = trip();
    app(PositionRecorder::class)->record($s, new PositionData(7.38, 3.95, now()), TrackingSource::DriverApp);

    $this->get('/track/'.$s->tracking_code)
        ->assertOk()
        ->assertSee('In transit')
        ->assertSee('LAG-***-XY')
        ->assertDontSee('LAG-123-XY')
        ->assertDontSee('SECRET PRICE')
        ->assertDontSee('2348099887766')
        ->assertDontSee('0809 988 7766');
});

it('finds a shipment from the search box and says when a code is unknown', function () {
    $s = trip();

    $this->get('/track?code='.strtolower(str_replace('-', '', $s->tracking_code)))->assertRedirect('/track/'.$s->tracking_code);
    $this->get('/track?code=GEA-0000-ZZZZZ')->assertOk()->assertSee("couldn't find", false);
    $this->get('/track/GEA-0000-ZZZZZ')->assertNotFound();
});

it('returns only what changed since the last poll', function () {
    $s = trip();
    $first = $this->getJson("/api/track/{$s->tracking_code}/updates")->assertOk()->json();
    expect($first['stage'])->toBe('in_transit')->and($first)->toHaveKeys(['events', 'path', 'eta', 'server_time']);

    $this->travel(5)->seconds();
    app(PositionRecorder::class)->record($s, new PositionData(8.0, 4.5, now()), TrackingSource::DriverApp);
    app(ShipmentService::class)->transition($s, ShipmentStage::Arrived, TrackingSource::DriverApp);

    $next = $this->getJson("/api/track/{$s->tracking_code}/updates?since=".urlencode($first['server_time']))->json();
    expect($next['stage'])->toBe('arrived')
        ->and($next['path'])->toHaveCount(1)
        ->and(collect($next['events'])->pluck('stage')->all())->toBe(['arrived']);
});

it('lets customers opt in to email updates', function () {
    $s = trip();
    $this->post("/track/{$s->tracking_code}/subscribe", ['email' => 'Ada@ObiFoods.ng'])->assertRedirect();

    expect($s->subscriptions()->pluck('email')->all())->toBe(['ada@obifoods.ng']);
});

it('estimates an arrival window and widens it when delayed', function () {
    $s = trip();
    $eta = app(EtaEstimator::class)->estimate($s);
    expect($eta)->not->toBeNull()->and($eta['km'])->toBeGreaterThan(900);

    app(ShipmentService::class)->transition($s, ShipmentStage::Delayed, TrackingSource::Manual, null, 'Breakdown');
    $delayed = app(EtaEstimator::class)->estimate($s->refresh());
    $width = fn (array $e): float => abs($e['from']->diffInMinutes($e['to']));
    expect($width($delayed))->toBeGreaterThan($width($eta));
});

it('accepts signed webhook positions and maps them to the truck on a trip', function () {
    $s = trip(['tracker_device_id' => 'DEV-77']);
    TrackingConnector::query()->create(['name' => 'webhook', 'enabled' => true, 'secret' => 's3cret']);
    $body = json_encode(['positions' => [
        ['device_id' => 'DEV-77', 'lat' => 7.8, 'lng' => 6.7, 'speed' => 60, 'recorded_at' => now()->toIso8601String()],
        ['device_id' => 'UNKNOWN', 'lat' => 7.8, 'lng' => 6.7],
    ]]);
    $sig = 'sha256='.hash_hmac('sha256', $body, 's3cret');

    $this->call('POST', '/api/connectors/webhook/webhook', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_GEA_SIGNATURE' => $sig], $body)
        ->assertOk()->assertJson(['accepted' => 1, 'ignored' => 1]);

    expect($s->positions()->where('source', 'connector')->count())->toBe(1);
});

it('refuses webhooks with a bad signature or when disabled', function () {
    TrackingConnector::query()->create(['name' => 'webhook', 'enabled' => true, 'secret' => 's3cret']);
    $body = '{"device_id":"X","lat":7,"lng":4}';

    $this->call('POST', '/api/connectors/webhook/webhook', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_GEA_SIGNATURE' => 'sha256=nope'], $body)->assertStatus(401);

    TrackingConnector::query()->where('name', 'webhook')->update(['enabled' => false]);
    $this->postJson('/api/connectors/webhook/webhook', ['device_id' => 'X'])->assertNotFound();
});

it('polls Traccar only when it is enabled', function () {
    $s = trip(['tracker_device_id' => 'TRC-1']);
    Http::fake([
        'traccar.test/api/devices' => Http::response([['id' => 9, 'uniqueId' => 'TRC-1']]),
        'traccar.test/api/positions' => Http::response([['deviceId' => 9, 'latitude' => 8.1, 'longitude' => 4.2, 'speed' => 30, 'fixTime' => now()->toIso8601String()]]),
    ]);

    $this->artisan('tracking:poll-connectors')->assertSuccessful();
    expect($s->positions()->where('source', 'connector')->count())->toBe(0);

    TrackingConnector::query()->create(['name' => 'traccar', 'enabled' => true, 'settings' => ['url' => 'https://traccar.test', 'token' => 't']]);
    $this->artisan('tracking:poll-connectors')->assertSuccessful();
    expect($s->positions()->where('source', 'connector')->first()?->speed_kmh)->toBe(56); // 30 knots
});

it('flags trips with no update for an hour, once', function () {
    Notification::fake();
    $this->userWithRole(Role::Operations);
    $s = trip();
    $s->forceFill(['last_update_at' => now()->subMinutes(90)])->save();

    $this->artisan('tracking:flag-stale')->assertSuccessful();
    $this->artisan('tracking:flag-stale')->assertSuccessful();

    Notification::assertSentTimes(StaleTripNotification::class, 2); // two ops users exist: trip() made one, this test made one
    expect($s->refresh()->stale_alerted_at)->not->toBeNull();
});

it('thins positions older than 90 days to one per 10 minutes', function () {
    $s = trip();
    $base = Carbon::parse('2026-01-01 10:00');
    foreach (range(0, 11) as $i) {
        Position::query()->create(['shipment_id' => $s->id, 'source' => 'driver_app', 'lat' => 7, 'lng' => 4,
            'recorded_at' => $base->copy()->addMinutes($i), 'received_at' => $base]);
    }

    $this->artisan('tracking:downsample')->assertSuccessful();

    expect(Position::query()->where('shipment_id', $s->id)->where('recorded_at', '<', now()->subDays(90))->count())->toBe(2);
});

it('caches geocoding lookups', function () {
    Http::fake(['nominatim.openstreetmap.org/*' => Http::response([['lat' => '7.1', 'lon' => '5.1', 'display_name' => 'Ifon, Ondo']])]);
    $geo = app(Geocoder::class);

    expect($geo->find('Ifon')['lat'])->toBe(7.1);
    $geo->find('Ifon');
    Http::assertSentCount(1);
});

it('opens operations screens for operations and keeps sales out', function (string $path) {
    $this->actingAs($this->userWithRole(Role::Operations))->get($path)->assertOk();
    $this->actingAs($this->userWithRole(Role::Sales))->get($path)->assertForbidden();
})->with(['/admin/shipments', '/admin/shipments/create', '/admin/dispatch-board', '/admin/update-console', '/admin/live-map', '/admin/trucks', '/admin/drivers']);

it('shows a shipment with its map and timeline', function () {
    $s = trip();
    $this->actingAs($this->userWithRole(Role::Operations))->get("/admin/shipments/{$s->id}")->assertOk()->assertSee($s->tracking_code)->assertSee('Grey dashes: planned route');
});

it('keeps integrations to the super admin and creates a webhook secret on enable', function () {
    $this->actingAs($this->userWithRole(Role::Operations))->get('/admin/integrations')->assertForbidden();

    $this->actingAs($this->userWithRole(Role::SuperAdmin));
    Livewire\Livewire::test(Integrations::class)->set('data.webhook_enabled', true)->call('save')->assertSet('newSecret', fn ($v) => strlen((string) $v) === 48);

    expect(TrackingConnector::query()->where('name', 'webhook')->first()?->secret)->toHaveLength(48);
});
