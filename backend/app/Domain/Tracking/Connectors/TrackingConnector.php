<?php

declare(strict_types=1);

namespace App\Domain\Tracking\Connectors;

use App\Domain\Tracking\PositionData;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Plug-in point for GPS sources (PRD 8.4). A tracker company's API or a Traccar server
 * can be added without changing the rest of the system.
 */
interface TrackingConnector
{
    public function name(): string;

    /** @return iterable<PositionData> */
    public function fetchPositions(Carbon $since): iterable;

    public function supportsWebhook(): bool;

    /** @return iterable<PositionData> */
    public function handleWebhook(Request $request): iterable;
}
