<?php

declare(strict_types=1);

namespace App\Domain\Tracking;

use App\Enums\TrackingSource;
use App\Models\Position;
use App\Models\Shipment;
use App\Models\Truck;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Stores positions from every source into one timeline (PRD 8.3 merging rules):
 * the newest fix wins whatever its source, duplicates from offline batches are ignored.
 */
final class PositionRecorder
{
    public function __construct(private readonly Geocoder $geocoder) {}

    public function record(Shipment $shipment, PositionData $p, TrackingSource $source): ?Position
    {
        if (! $p->isPlausible()) {
            return null;
        }
        if ($p->clientEventId !== null && Position::query()->where('client_event_id', $p->clientEventId)->exists()) {
            return null; // Already received in an earlier batch.
        }

        try {
            /** @var Position $position */
            $position = Position::query()->create([
                'shipment_id' => $shipment->id,
                'truck_id' => $shipment->truck_id,
                'source' => $source->value,
                'lat' => $p->lat,
                'lng' => $p->lng,
                'accuracy_m' => $p->accuracyM,
                'speed_kmh' => $p->speedKmh,
                'heading' => $p->heading,
                'place_label' => $p->placeLabel ?? $this->geocoder->nearestPlace($p->lat, $p->lng),
                'client_event_id' => $p->clientEventId,
                'recorded_at' => $p->recordedAt,
                'received_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            return null;
        }

        if ($shipment->last_update_at === null || $p->recordedAt->gt($shipment->last_update_at)) {
            $shipment->forceFill(['last_update_at' => $p->recordedAt, 'stale_alerted_at' => null])->save();
        }

        return $position;
    }

    /** Routes a tracker fix to the active shipment of the truck that carries the device. */
    public function recordForDevice(string $deviceId, PositionData $p): ?Position
    {
        $truck = Truck::query()->where('tracker_device_id', $deviceId)->first();
        $shipment = $truck?->activeShipment();

        return $shipment !== null ? $this->record($shipment, $p, TrackingSource::Connector) : null;
    }
}
