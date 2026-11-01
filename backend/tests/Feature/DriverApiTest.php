<?php

declare(strict_types=1);

use App\Domain\Shipping\ShipmentService;
use App\Enums\Role;
use App\Enums\ShipmentStage;
use App\Enums\TrackingSource;
use App\Filament\Resources\DriverResource\Pages\ListDrivers;
use App\Models\Customer;
use App\Models\Driver;
use App\Models\DriverIssue;
use App\Models\Shipment;
use App\Models\Truck;
use App\Notifications\DriverIssueNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function () {
    Http::preventStrayRequests();
    Mail::fake();
    Storage::fake('local');
});

/** A driver with PIN 4321 and one assigned trip Apapa → Kano. */
function driverWithTrip(ShipmentStage $upTo = ShipmentStage::Assigned): array
{
    $ops = test()->userWithRole(Role::Operations);
    $svc = app(ShipmentService::class);
    $s = $svc->create(['customer_id' => Customer::factory()->create(['name' => 'Obi Foods Ltd'])->id, 'cargo_description' => 'Rice',
        'tonnage' => 20, 'origin' => 'Apapa', 'destination' => 'Kano', 'notes' => 'SECRET PRICE 950000']);
    $truck = Truck::factory()->create(['capacity_tonnes' => 30]);
    $driver = Driver::factory()->create(['truck_id' => $truck->id, 'phone' => '2348031234567']);
    $driver->forceFill(['pin_hash' => Hash::make('4321')])->save();
    $svc->assign($s, $truck, $driver, $ops);
    $path = [ShipmentStage::Loading, ShipmentStage::Loaded, ShipmentStage::InTransit, ShipmentStage::Arrived];
    foreach ($path as $st) {
        if ($s->refresh()->stage === $upTo) {
            break;
        }
        $svc->transition($s, $st, TrackingSource::Manual, $ops);
    }

    return [$driver->refresh(), $s->refresh()];
}

function driverToken(Driver $driver): string
{
    return $driver->createToken('test', ['driver'])->plainTextToken;
}

it('signs a driver in with phone and PIN, in local or international format', function () {
    [$driver] = driverWithTrip();

    $this->postJson('/api/driver/login', ['phone' => '0803 123 4567', 'pin' => '4321'])
        ->assertOk()->assertJsonPath('driver.id', $driver->id)->assertJsonStructure(['token']);
    $this->postJson('/api/driver/login', ['phone' => '+2348031234567', 'pin' => '0000'])->assertStatus(422);
});

it('locks out PIN guessing after five tries a minute', function () {
    driverWithTrip();
    foreach (range(1, 5) as $i) {
        $this->postJson('/api/driver/login', ['phone' => '2348031234567', 'pin' => '000'.$i])->assertStatus(422);
    }
    $this->postJson('/api/driver/login', ['phone' => '2348031234567', 'pin' => '4321'])->assertStatus(429);
});

it('refuses inactive drivers, staff tokens and anonymous calls', function () {
    [$driver] = driverWithTrip();
    $this->getJson('/api/driver/trips')->assertUnauthorized();

    $this->actingAs($this->userWithRole(Role::Operations)); // a staff web session is not a driver
    $this->getJson('/api/driver/trips')->assertForbidden();
    app('auth')->forgetGuards();

    $token = driverToken($driver);
    $driver->forceFill(['is_active' => false])->save();
    $this->withToken($token)->getJson('/api/driver/trips')->assertForbidden();
});

it('lists only the driver\'s own trips, without price or private notes', function () {
    [$driver, $s] = driverWithTrip();
    [, $other] = (function () {
        $s2 = Shipment::factory()->create(['driver_id' => Driver::factory()->create()->id, 'stage' => 'assigned']);

        return [null, $s2];
    })();

    $res = $this->withToken(driverToken($driver))->getJson('/api/driver/trips')->assertOk();

    expect($res->json('trips'))->toHaveCount(1)
        ->and($res->json('trips.0.tracking_code'))->toBe($s->tracking_code)
        ->and($res->json('trips.0.next_stage'))->toBe('loading')
        ->and($res->getContent())->not->toContain('SECRET')->not->toContain('950000');
    $this->withToken(driverToken($driver))->getJson('/api/driver/trips/'.$other->id)->assertStatus(422);
});

it('moves a trip forward one step at a time and ignores a resent stage event', function () {
    [$driver, $s] = driverWithTrip();
    $token = driverToken($driver);
    $id = (string) Str::uuid();

    $this->withToken($token)->postJson("/api/driver/trips/{$s->id}/stage", ['client_event_id' => $id, 'stage' => 'loading'])
        ->assertOk()->assertJsonPath('trip.stage', 'loading');
    $this->withToken($token)->postJson("/api/driver/trips/{$s->id}/stage", ['client_event_id' => $id, 'stage' => 'loading'])
        ->assertOk()->assertJsonPath('trip.stage', 'loading');
    $this->withToken($token)->postJson("/api/driver/trips/{$s->id}/stage", ['client_event_id' => (string) Str::uuid(), 'stage' => 'delivered'])
        ->assertStatus(422);

    expect($s->events()->where('source', 'driver_app')->count())->toBe(1);
});

it('accepts a batched offline position upload once and rejects other trips', function () {
    [$driver, $s] = driverWithTrip(ShipmentStage::InTransit);
    $token = driverToken($driver);
    $foreign = Shipment::factory()->create(['stage' => 'in_transit']);
    $batch = ['positions' => [
        ['client_event_id' => 'p-1', 'shipment_id' => $s->id, 'lat' => 6.75, 'lng' => 4.88, 'recorded_at' => now()->subMinutes(10)->toIso8601String(), 'speed_kmh' => 62],
        ['client_event_id' => 'p-2', 'shipment_id' => $s->id, 'lat' => 6.80, 'lng' => 4.95, 'recorded_at' => now()->subMinutes(9)->toIso8601String()],
        ['client_event_id' => 'p-3', 'shipment_id' => $foreign->id, 'lat' => 6.80, 'lng' => 4.95, 'recorded_at' => now()->toIso8601String()],
    ]];

    $this->withToken($token)->postJson('/api/driver/positions', $batch)->assertOk()
        ->assertJson(['accepted' => 2, 'duplicates' => 0, 'rejected' => 1]);
    $this->withToken($token)->postJson('/api/driver/positions', $batch)->assertOk()
        ->assertJson(['accepted' => 0, 'duplicates' => 2, 'rejected' => 1]);

    expect($s->positions()->count())->toBe(2)
        ->and($foreign->positions()->count())->toBe(0)
        ->and($driver->refresh()->app_last_seen_at)->not->toBeNull();
});

it('needs a photo or signature to confirm delivery, and stores the proof privately', function () {
    [$driver, $s] = driverWithTrip(ShipmentStage::Arrived);
    $token = driverToken($driver);

    $this->withToken($token)->postJson("/api/driver/trips/{$s->id}/deliver", ['client_event_id' => 'd-1', 'receiver_name' => 'Musa'])
        ->assertStatus(422);

    $this->withToken($token)->post("/api/driver/trips/{$s->id}/deliver", [
        'client_event_id' => 'd-2', 'receiver_name' => 'Musa Bello',
        'photo' => UploadedFile::fake()->image('pod.jpg'), 'signature' => UploadedFile::fake()->image('sig.png'),
    ], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('trip.stage', 'delivered');

    $proof = $s->proofs()->firstOrFail();
    expect($proof->receiver_name)->toBe('Musa Bello');
    Storage::disk('local')->assertExists((string) $proof->photo_path);
    Storage::disk('local')->assertExists((string) $proof->signature_path);
});

it('alerts Operations on a breakdown without telling the customer, and marks a delay', function () {
    Notification::fake();
    [$driver, $s] = driverWithTrip(ShipmentStage::InTransit);
    $token = driverToken($driver);

    $this->withToken($token)->postJson("/api/driver/trips/{$s->id}/issues", ['client_event_id' => 'i-1', 'type' => 'breakdown', 'note' => 'Gearbox at Ore'])
        ->assertOk()->assertJsonPath('trip.stage', 'in_transit');
    $this->withToken($token)->postJson("/api/driver/trips/{$s->id}/issues", ['client_event_id' => 'i-1', 'type' => 'breakdown', 'note' => 'Gearbox at Ore'])
        ->assertOk();
    $this->withToken($token)->postJson("/api/driver/trips/{$s->id}/issues", ['client_event_id' => 'i-2', 'type' => 'delay'])
        ->assertStatus(422);
    $this->withToken($token)->postJson("/api/driver/trips/{$s->id}/issues", ['client_event_id' => 'i-3', 'type' => 'delay', 'note' => 'Traffic at Lokoja'])
        ->assertOk()->assertJsonPath('trip.stage', 'delayed');

    expect(DriverIssue::query()->count())->toBe(2)
        ->and($s->events()->where('note', 'like', 'Breakdown%')->value('is_public'))->toBeFalse();
    Notification::assertSentTimes(DriverIssueNotification::class, 2);
});

it('lets Operations set a PIN, which signs out the old phone', function () {
    [$driver] = driverWithTrip();
    $old = driverToken($driver);

    Livewire::actingAs($this->userWithRole(Role::Operations))
        ->test(ListDrivers::class)
        ->callTableAction('setPin', $driver, ['pin' => '998877'])
        ->assertHasNoTableActionErrors();

    expect(Hash::check('998877', (string) $driver->refresh()->pin_hash))->toBeTrue()
        ->and($driver->tokens()->count())->toBe(0);
    app('auth')->forgetGuards(); // drop the admin session used above
    $this->withToken($old)->getJson('/api/driver/me')->assertUnauthorized();
});
