<?php

declare(strict_types=1);

namespace App\Domain\Tracking;

use App\Domain\Shipping\ShipmentService;
use App\Enums\ShipmentStage;
use App\Enums\TrackingSource;
use App\Models\Place;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * One staff update from the console (PRD 10.1): optional stage change, optional location, a note.
 * "Driver called from Ore, 2:15pm."
 */
final class ManualUpdate
{
    public function __construct(
        private readonly ShipmentService $shipments,
        private readonly PositionRecorder $positions,
        private readonly Geocoder $geocoder,
    ) {}

    /**
     * @param  array{lat: float, lng: float}|null  $point  a spot picked on the map
     */
    public function apply(
        Shipment $shipment,
        User $by,
        ?ShipmentStage $stage = null,
        ?int $placeId = null,
        ?string $town = null,
        ?array $point = null,
        ?string $note = null,
        bool $public = true,
    ): void {
        $location = null;
        if ($placeId !== null && ($place = Place::query()->find($placeId)) !== null) {
            $location = ['lat' => (float) $place->lat, 'lng' => (float) $place->lng, 'label' => $place->label()];
        } elseif (filled($town) && ($hit = $this->geocoder->find((string) $town)) !== null) {
            $location = $hit;
        } elseif ($point !== null) {
            $location = $point + ['label' => $this->geocoder->nearestPlace($point['lat'], $point['lng'])];
        }

        if ($location !== null) {
            $this->positions->record($shipment, new PositionData(
                lat: $location['lat'], lng: $location['lng'], recordedAt: Carbon::now(), placeLabel: $location['label'],
            ), TrackingSource::Manual);
        }

        if ($stage !== null && $stage !== $shipment->stage) {
            $this->shipments->transition($shipment, $stage, TrackingSource::Manual, $by, $note, $location ?? [], $public);

            return;
        }

        $this->shipments->logEvent($shipment, null, null, TrackingSource::Manual, $by,
            $note ?? ($location !== null ? 'Location updated.' : null), $location ?? [], $public);
        $shipment->forceFill(['last_update_at' => now(), 'stale_alerted_at' => null])->save();
    }
}
