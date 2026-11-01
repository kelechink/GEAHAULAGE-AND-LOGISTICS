<?php

declare(strict_types=1);

namespace App\Enums;

enum TrackingSource: string
{
    case DriverApp = 'driver_app';
    case Manual = 'manual';
    case Connector = 'connector';
    case System = 'system';

    public function label(): string
    {
        return match ($this) {
            self::DriverApp => 'Driver app',
            self::Manual => 'Staff update',
            self::Connector => 'GPS tracker',
            self::System => 'System',
        };
    }
}
