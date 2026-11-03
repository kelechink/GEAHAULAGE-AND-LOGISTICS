<?php

declare(strict_types=1);

namespace App\Domain\Tracking;

use App\Enums\ShipmentStage;
use App\Models\Position;
use App\Models\Shipment;
use App\Models\ShipmentEvent;
use Illuminate\Support\Carbon;

/**
 * The only shape of shipment data that leaves the building (PRD 8.5).
 * Never includes price, driver phone, full plate or private notes.
 */
final class PublicTracking
{
    public function __construct(private readonly EtaEstimator $eta) {}

    /** @return array<string, mixed> */
    public function snapshot(Shipment $shipment): array
    {
        $shipment->loadMissing(['truck', 'latestPosition']);
        $eta = $this->eta->estimate($shipment);

        return [
            'code' => $shipment->tracking_code,
            'origin' => $shipment->origin,
            'destination' => $shipment->destination,
            'stage' => $shipment->stage->value,
            'stage_label' => $shipment->stage->label(),
            'progress' => $shipment->stage->progress(),
            'truck' => $shipment->truck?->maskedRegistration(),
            'updated_at' => $shipment->last_update_at?->toIso8601String(),
            'updated_ago' => $shipment->last_update_at !== null ? 'Updated '.$shipment->last_update_at->diffForHumans() : null,
            'eta' => $eta !== null ? EtaEstimator::describe($eta) : null,
            'delay_reason' => $shipment->stage === ShipmentStage::Delayed ? $shipment->delay_reason : null,
            'planned' => $this->plannedRoute($shipment),
            'events' => $this->events($shipment),
            'path' => $this->path($shipment),
            'last' => $this->point($shipment->latestPosition),
            'server_time' => now()->toIso8601String(),
        ];
    }

    /**
     * Only what changed since the client's last poll, to keep the 15-second polling cheap (PRD 16).
     *
     * @return array<string, mixed>
     */
    public function since(Shipment $shipment, Carbon $since): array
    {
        $full = $this->snapshot($shipment);
        $full['events'] = $this->events($shipment, $since);
        $full['path'] = $this->path($shipment, $since);
        unset($full['planned']);

        return $full;
    }

    /** @return list<array{lat: float, lng: float}> */
    private function plannedRoute(Shipment $s): array
    {
        $pts = [];
        if ($s->origin_lat !== null) {
            $pts[] = ['lat' => (float) $s->origin_lat, 'lng' => (float) $s->origin_lng];
        }
        foreach ($s->waypoints ?? [] as $w) {
            $pts[] = ['lat' => (float) $w['lat'], 'lng' => (float) $w['lng']];
        }
        if ($s->destination_lat !== null) {
            $pts[] = ['lat' => (float) $s->destination_lat, 'lng' => (float) $s->destination_lng];
        }

        return $pts;
    }

    /** @return list<array<string, mixed>> */
    private function events(Shipment $s, ?Carbon $since = null): array
    {
        return $s->events()->where('is_public', true)->whereNotNull('to_stage')
            ->when($since !== null, fn ($q) => $q->where('created_at', '>', $since))
            ->get()
            ->map(fn (ShipmentEvent $e): array => [
                'id' => $e->id,
                'stage' => $e->to_stage?->value,
                'label' => $e->to_stage?->label(),
                'place' => $e->place_label,
                'note' => $e->note,
                'at' => $e->occurred_at->toIso8601String(),
                'at_label' => $e->occurred_at->timezone('Africa/Lagos')->format('D j M, g:ia'),
            ])->values()->all();
    }

    /** @return list<array{lat: float, lng: float}> */
    private function path(Shipment $s, ?Carbon $since = null): array
    {
        return $s->positions()->when($since !== null, fn ($q) => $q->where('received_at', '>', $since))
            ->get(['lat', 'lng'])
            ->map(fn (Position $p): array => ['lat' => (float) $p->lat, 'lng' => (float) $p->lng])
            ->values()->all();
    }

    /** @return array{lat: float, lng: float, place: string|null}|null */
    private function point(?Position $p): ?array
    {
        return $p === null ? null : ['lat' => (float) $p->lat, 'lng' => (float) $p->lng, 'place' => $p->place_label];
    }
}
