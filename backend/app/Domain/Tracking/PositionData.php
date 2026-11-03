<?php

declare(strict_types=1);

namespace App\Domain\Tracking;

use Illuminate\Support\Carbon;

/** One GPS fix from any source (PRD 8.4 DTO: device_id, lat, lng, speed, recorded_at). */
final class PositionData
{
    public function __construct(
        public readonly float $lat,
        public readonly float $lng,
        public readonly Carbon $recordedAt,
        public readonly ?int $speedKmh = null,
        public readonly ?int $accuracyM = null,
        public readonly ?int $heading = null,
        public readonly ?string $deviceId = null,
        public readonly ?string $clientEventId = null,
        public readonly ?string $placeLabel = null,
    ) {}

    public function isPlausible(): bool
    {
        // Rough box around West Africa; rejects 0,0 and swapped coordinates.
        return $this->lat > -2 && $this->lat < 25 && $this->lng > -20 && $this->lng < 20 && ! ($this->lat === 0.0 && $this->lng === 0.0);
    }
}
