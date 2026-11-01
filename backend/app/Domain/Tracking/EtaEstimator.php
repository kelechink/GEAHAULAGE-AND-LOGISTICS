<?php

declare(strict_types=1);

namespace App\Domain\Tracking;

use App\Enums\ShipmentStage;
use App\Models\Shipment;
use App\Settings\OperationsSettings;
use Illuminate\Support\Carbon;

/**
 * ETA = remaining road distance / average speed, shown as a window (PRD 8.3).
 * Road distance is straight-line distance × a road factor. DELAYED widens the window.
 */
final class EtaEstimator
{
    public const ROAD_FACTOR = 1.3;

    public function __construct(private readonly OperationsSettings $settings) {}

    /** @return array{from: Carbon, to: Carbon, km: int}|null */
    public function estimate(Shipment $shipment): ?array
    {
        if (! $shipment->stage->isMoving() && $shipment->stage !== ShipmentStage::Loaded) {
            return null;
        }
        if ($shipment->destination_lat === null || $shipment->destination_lng === null) {
            return null;
        }

        $here = $shipment->latestPosition;
        $lat = $here !== null ? (float) $here->lat : (float) $shipment->origin_lat;
        $lng = $here !== null ? (float) $here->lng : (float) $shipment->origin_lng;
        if ($here === null && $shipment->origin_lat === null) {
            return null;
        }

        $km = Geo::km($lat, $lng, (float) $shipment->destination_lat, (float) $shipment->destination_lng) * self::ROAD_FACTOR;
        $speed = max(10, $this->settings->average_speed_kmh);
        $minutes = (int) round($km / $speed * 60);

        $base = now()->addMinutes($minutes);
        $spread = max(60, (int) round($minutes * 0.15));
        if ($shipment->stage === ShipmentStage::Delayed) {
            $spread *= 2;
        }

        return [
            'from' => $base->copy()->subMinutes(intdiv($spread, 2))->startOfHour(),
            'to' => $base->copy()->addMinutes(intdiv($spread, 2))->addHour()->startOfHour(),
            'km' => (int) round($km),
        ];
    }

    /**
     * "Expected 4–6pm", "Expected Tue 9am–1pm".
     *
     * @param  array{from: Carbon, to: Carbon, km: int}|null  $eta
     */
    public static function describe(?array $eta): ?string
    {
        if ($eta === null) {
            return null;
        }
        $from = $eta['from']->copy()->timezone('Africa/Lagos');
        $to = $eta['to']->copy()->timezone('Africa/Lagos');
        $day = $from->isToday() ? '' : ($from->isTomorrow() ? 'tomorrow ' : $from->format('D j M').' ');
        $fmt = fn (Carbon $t): string => $t->format($t->minute === 0 ? 'ga' : 'g:ia');

        if ($from->isSameDay($to) && $from->format('a') === $to->format('a')) {
            return 'Expected '.$day.str_replace(['am', 'pm'], '', $fmt($from)).'–'.$fmt($to);
        }

        return 'Expected '.$day.$fmt($from).'–'.($from->isSameDay($to) ? '' : $to->format('D ')).$fmt($to);
    }
}
