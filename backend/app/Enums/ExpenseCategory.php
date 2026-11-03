<?php

declare(strict_types=1);

namespace App\Enums;

/** PRD 10.5 expense categories. */
enum ExpenseCategory: string
{
    case Fuel = 'fuel';
    case Maintenance = 'maintenance';
    case Tolls = 'tolls';
    case DriverAllowances = 'driver_allowances';
    case Salaries = 'salaries';
    case Rent = 'rent';
    case Insurance = 'insurance';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Fuel => 'Fuel', self::Maintenance => 'Maintenance', self::Tolls => 'Tolls and levies',
            self::DriverAllowances => 'Driver allowances', self::Salaries => 'Salaries', self::Rent => 'Rent',
            self::Insurance => 'Insurance', self::Other => 'Other',
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
