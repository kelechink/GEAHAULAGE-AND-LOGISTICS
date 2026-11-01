<?php

declare(strict_types=1);

namespace App\Domain\Tracking\Connectors;

use App\Domain\Tracking\PositionData;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * A local tracker company pushes positions to POST /api/connectors/webhook/webhook in a documented
 * JSON format (docs/connector-integration.md). Signature checking happens in the controller.
 */
final class GenericWebhookConnector implements TrackingConnector
{
    public function name(): string
    {
        return 'webhook';
    }

    public function fetchPositions(Carbon $since): iterable
    {
        return [];
    }

    public function supportsWebhook(): bool
    {
        return true;
    }

    public function handleWebhook(Request $request): iterable
    {
        $items = $request->input('positions', [$request->all()]);
        foreach (is_array($items) ? $items : [] as $item) {
            if (! is_array($item) || ! isset($item['device_id'], $item['lat'], $item['lng'])) {
                continue;
            }
            yield new PositionData(
                lat: (float) $item['lat'],
                lng: (float) $item['lng'],
                recordedAt: isset($item['recorded_at']) ? Carbon::parse((string) $item['recorded_at']) : now(),
                speedKmh: isset($item['speed']) ? (int) round((float) $item['speed']) : null,
                deviceId: (string) $item['device_id'],
            );
        }
    }
}
