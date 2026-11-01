<?php

declare(strict_types=1);

namespace App\Domain\Tracking;

use App\Domain\Shipping\ShipmentService;
use App\Domain\Shipping\ShippingException;
use App\Enums\Role;
use App\Enums\ShipmentStage;
use App\Enums\TrackingSource;
use App\Models\Driver;
use App\Models\DriverIssue;
use App\Models\Position;
use App\Models\Shipment;
use App\Models\ShipmentEvent;
use App\Models\User;
use App\Notifications\DriverIssueNotification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Everything the Android driver app can do (PRD 13). Every write is idempotent on the
 * app's client_event_id, so an offline batch can be resent safely.
 */
final class DriverApp
{
    /** Stages a driver may set, and the stage each one follows. Cancelling stays with the office. */
    public const DRIVER_STEPS = [
        'assigned' => ShipmentStage::Loading,
        'loading' => ShipmentStage::Loaded,
        'loaded' => ShipmentStage::InTransit,
        'in_transit' => ShipmentStage::Arrived,
        'delayed' => ShipmentStage::InTransit,
        'arrived' => ShipmentStage::Delivered,
    ];

    public function __construct(
        private readonly ShipmentService $shipments,
        private readonly PositionRecorder $positions,
    ) {}

    /** @return Collection<int, Shipment> */
    public function trips(Driver $driver): Collection
    {
        return Shipment::query()
            ->where('driver_id', $driver->id)
            ->where(fn ($q) => $q->whereIn('stage', ['assigned', 'loading', 'loaded', 'in_transit', 'delayed', 'arrived'])
                ->orWhere('delivered_at', '>=', now()->subDay()))
            ->with(['customer', 'truck'])
            ->orderByRaw("CASE WHEN stage IN ('in_transit','delayed') THEN 0 WHEN stage = 'arrived' THEN 1 ELSE 2 END")
            ->orderBy('pickup_date')
            ->get();
    }

    public function trip(Driver $driver, int $id): Shipment
    {
        $s = Shipment::query()->with(['customer', 'truck'])->where('driver_id', $driver->id)->find($id);
        if ($s === null) {
            throw new ShippingException('This trip is not assigned to you.');
        }

        return $s;
    }

    public function nextStage(Shipment $s): ?ShipmentStage
    {
        return self::DRIVER_STEPS[$s->stage->value] ?? null;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array{accepted: int, duplicates: int, rejected: int}
     */
    public function uploadPositions(Driver $driver, array $items): array
    {
        $out = ['accepted' => 0, 'duplicates' => 0, 'rejected' => 0];
        $trips = Shipment::query()->where('driver_id', $driver->id)->whereIn('stage', ShipmentStage::activeValues())->get()->keyBy('id');

        foreach ($items as $i) {
            $trip = $trips->get((int) ($i['shipment_id'] ?? 0));
            if ($trip === null) {
                $out['rejected']++;

                continue;
            }
            if (Position::query()->where('client_event_id', (string) $i['client_event_id'])->exists()) {
                $out['duplicates']++;

                continue;
            }
            $p = $this->positions->record($trip, new PositionData(
                lat: (float) $i['lat'], lng: (float) $i['lng'], recordedAt: Carbon::parse((string) $i['recorded_at']),
                speedKmh: isset($i['speed_kmh']) ? (int) $i['speed_kmh'] : null,
                accuracyM: isset($i['accuracy_m']) ? (int) $i['accuracy_m'] : null,
                heading: isset($i['heading']) ? (int) $i['heading'] : null,
                clientEventId: (string) $i['client_event_id'],
            ), TrackingSource::DriverApp);
            $p !== null ? $out['accepted']++ : $out['rejected']++;
        }

        $driver->forceFill(['app_last_seen_at' => now()])->save();

        return $out;
    }

    /**
     * @param  array{lat?: float|null, lng?: float|null}  $location
     */
    public function setStage(Driver $driver, Shipment $trip, ShipmentStage $to, string $clientEventId, array $location = [], ?string $note = null): ShipmentEvent
    {
        $existing = ShipmentEvent::query()->where('client_event_id', $clientEventId)->first();
        if ($existing !== null) {
            return $existing; // already applied from an earlier upload
        }

        if ($this->nextStage($trip) !== $to) {
            throw new ShippingException("You can't move this trip from {$trip->stage->label()} to {$to->label()} in the app. Call Operations.");
        }

        $loc = [];
        if (isset($location['lat'], $location['lng'])) {
            $loc = ['lat' => (float) $location['lat'], 'lng' => (float) $location['lng'],
                'label' => app(Geocoder::class)->nearestPlace((float) $location['lat'], (float) $location['lng'])];
        }

        return $this->shipments->transition($trip, $to, TrackingSource::DriverApp, $driver->user, $note, $loc, true, null, $clientEventId);
    }

    /**
     * Proof of delivery (PRD 8.2, 13): photo and/or signature plus the receiver's name, then DELIVERED.
     *
     * @param  array{receiver_name: string, photo_path?: string|null, signature_path?: string|null}  $proof
     * @param  array{lat?: float|null, lng?: float|null}  $location
     */
    public function deliver(Driver $driver, Shipment $trip, array $proof, string $clientEventId, array $location = []): ShipmentEvent
    {
        $existing = ShipmentEvent::query()->where('client_event_id', $clientEventId)->first();
        if ($existing !== null) {
            return $existing;
        }
        if (blank($proof['photo_path'] ?? null) && blank($proof['signature_path'] ?? null)) {
            throw new ShippingException('Take a photo or get a signature to confirm delivery.');
        }
        if ($trip->stage !== ShipmentStage::Arrived) {
            throw new ShippingException('Mark the trip as arrived before confirming delivery.');
        }

        $loc = isset($location['lat'], $location['lng']) ? ['lat' => (float) $location['lat'], 'lng' => (float) $location['lng']] : [];

        return $this->shipments->transition($trip, ShipmentStage::Delivered, TrackingSource::DriverApp, $driver->user,
            'Delivered to '.$proof['receiver_name'].'.', $loc, true, $proof, $clientEventId);
    }

    /**
     * Issue report: alerts Operations at once. A delay while moving also sets the trip to DELAYED.
     *
     * @param  array{type: string, note?: string|null, photo_path?: string|null, lat?: float|null, lng?: float|null}  $data
     */
    public function reportIssue(Driver $driver, Shipment $trip, array $data, string $clientEventId): DriverIssue
    {
        $existing = DriverIssue::query()->where('client_event_id', $clientEventId)->first();
        if ($existing !== null) {
            return $existing;
        }

        return DB::transaction(function () use ($driver, $trip, $data, $clientEventId): DriverIssue {
            /** @var DriverIssue $issue */
            $issue = DriverIssue::query()->create([
                'shipment_id' => $trip->id, 'driver_id' => $driver->id, 'type' => $data['type'], 'note' => $data['note'] ?? null,
                'photo_path' => $data['photo_path'] ?? null, 'lat' => $data['lat'] ?? null, 'lng' => $data['lng'] ?? null,
                'client_event_id' => $clientEventId, 'occurred_at' => now(),
            ]);

            $label = DriverIssue::TYPES[$data['type']] ?? $data['type'];
            $note = trim($label.': '.($data['note'] ?? ''), ': ');
            if ($data['type'] === 'delay' && $trip->stage === ShipmentStage::InTransit) {
                $this->shipments->transition($trip, ShipmentStage::Delayed, TrackingSource::DriverApp, $driver->user, ($data['note'] ?? null) ?: 'Delayed on the road', [], true);
            } else {
                // Breakdowns and accidents stay private until Operations decides what to tell the customer.
                $this->shipments->logEvent($trip, null, null, TrackingSource::DriverApp, $driver->user, $note,
                    isset($data['lat'], $data['lng']) ? ['lat' => (float) $data['lat'], 'lng' => (float) $data['lng']] : [], $data['type'] === 'checkpoint');
            }

            $ops = User::query()->role([Role::Operations->value, Role::Management->value])->where('is_active', true)->get();
            Notification::send($ops, new DriverIssueNotification($issue->load(['shipment', 'driver'])));

            return $issue;
        });
    }
}
