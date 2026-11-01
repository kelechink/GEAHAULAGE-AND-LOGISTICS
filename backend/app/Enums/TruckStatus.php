<?php

declare(strict_types=1);

namespace App\Enums;

enum TruckStatus: string
{
    case Available = 'available';
    case OnTrip = 'on_trip';
    case Maintenance = 'maintenance';
    case Inactive = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Available',
            self::OnTrip => 'On a trip',
            self::Maintenance => 'In maintenance',
            self::Inactive => 'Inactive',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        $o = [];
        foreach (self::cases() as $c) {
            $o[$c->value] = $c->label();
        }

        return $o;
    }
}
