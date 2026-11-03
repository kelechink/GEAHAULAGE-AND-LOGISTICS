<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Tracking\Connectors\ConnectorRegistry;
use App\Domain\Tracking\PositionRecorder;
use App\Http\Controllers\Controller;
use App\Models\TrackingConnector;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Signed webhook for tracker companies. Header: X-GEA-Signature: sha256=<hex HMAC of the raw body
 * with the connector secret>. Format: docs/connector-integration.md.
 */
class ConnectorWebhookController extends Controller
{
    public function __invoke(Request $request, string $name, ConnectorRegistry $registry, PositionRecorder $recorder): JsonResponse
    {
        $connector = $registry->make($name);
        $row = TrackingConnector::query()->where('name', $name)->first();
        if ($connector === null || $row === null || ! $connector->supportsWebhook() || blank($row->secret)) {
            return response()->json(['error' => 'Unknown or disabled connector.'], 404);
        }

        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), (string) $row->secret);
        if (! hash_equals($expected, (string) $request->header('X-GEA-Signature'))) {
            return response()->json(['error' => 'Bad signature.'], 401);
        }

        $accepted = 0;
        $unmatched = 0;
        foreach ($connector->handleWebhook($request) as $p) {
            if ($p->deviceId !== null && $recorder->recordForDevice($p->deviceId, $p) !== null) {
                $accepted++;
            } else {
                $unmatched++;
            }
        }

        return response()->json(['accepted' => $accepted, 'ignored' => $unmatched]);
    }
}
