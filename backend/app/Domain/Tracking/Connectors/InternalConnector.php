<?php

declare(strict_types=1);

namespace App\Domain\Tracking\Connectors;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Phone (driver app) and Manual (staff console) write positions directly, so they have nothing to poll.
 */
final class InternalConnector implements TrackingConnector
{
    public function __construct(private readonly string $name) {}

    public function name(): string
    {
        return $this->name;
    }

    public function fetchPositions(Carbon $since): iterable
    {
        return [];
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
