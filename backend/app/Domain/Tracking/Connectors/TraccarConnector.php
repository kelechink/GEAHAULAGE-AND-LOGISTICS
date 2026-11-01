<?php

declare(strict_types=1);

namespace App\Domain\Tracking\Connectors;

use App\Domain\Tracking\PositionData;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

/**
 * Polls a Traccar server's REST API. Disabled until a server URL and token are added in
 * Settings → Integrations (PRD 8.4).
 */
final class TraccarConnector implements TrackingConnector
{
    public function __construct(private readonly string $baseUrl, private readonly string $token) {}

    public function name(): string
    {
        return 'traccar';
    }

    public function fetchPositions(Carbon $since): iterable
    {
        $http = Http::withToken($this->token)->acceptJson()->timeout(15)->baseUrl(rtrim($this->baseUrl, '/'));

        /** @var array<int, array<string, mixed>> $deviceList */
        $deviceList = (array) $http->get('/api/devices')->throw()->json();
        $devices = [];
        foreach ($deviceList as $d) {
            $devices[$d['id'] ?? ''] = $d;
        }
        $positions = $http->get('/api/positions')->throw()->json();

        foreach (is_array($positions) ? $positions : [] as $p) {
            $fixTime = Carbon::parse((string) ($p['fixTime'] ?? $p['deviceTime'] ?? 'now'));
            if ($fixTime->lte($since)) {
                continue;
            }
            $device = $devices[$p['deviceId'] ?? ''] ?? null;
            yield new PositionData(
                lat: (float) $p['latitude'],
                lng: (float) $p['longitude'],
                recordedAt: $fixTime,
                speedKmh: isset($p['speed']) ? (int) round((float) $p['speed'] * 1.852) : null, // knots → km/h
                heading: isset($p['course']) ? (int) $p['course'] : null,
                deviceId: (string) ($device['uniqueId'] ?? $p['deviceId']),
            );
        }
    }

    public function supportsWebhook(): bool
    {
        return false;
    }

    public function handleWebhook(Request $request): iterable
    {
        return [];
    }
}
