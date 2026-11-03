<?php

declare(strict_types=1);

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class OperationsSettings extends Settings
{
    /** Used for ETA estimates (PRD 8.3). */
    public int $average_speed_kmh;

    /** Minutes without an update before an in-transit trip is flagged stale (PRD 8.3). */
    public int $stale_after_minutes;

    public static function group(): string
    {
        return 'operations';
    }
}
