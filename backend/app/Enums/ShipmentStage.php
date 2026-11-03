<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Shipment lifecycle (PRD 8.2).
 *
 * BOOKED → ASSIGNED → LOADING → LOADED → IN_TRANSIT → ARRIVED → DELIVERED → CLOSED,
 * with DELAYED as a detour from LOADED or IN_TRANSIT, and CANCELLED from any open stage.
 */
enum ShipmentStage: string
{
    case Booked = 'booked';
    case Assigned = 'assigned';
    case Loading = 'loading';
    case Loaded = 'loaded';
    case InTransit = 'in_transit';
    case Delayed = 'delayed';
    case Arrived = 'arrived';
    case Delivered = 'delivered';
    case Closed = 'closed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Booked => 'Booked',
            self::Assigned => 'Truck assigned',
            self::Loading => 'Loading',
            self::Loaded => 'Loaded',
            self::InTransit => 'In transit',
            self::Delayed => 'Delayed',
            self::Arrived => 'Arrived',
            self::Delivered => 'Delivered',
            self::Closed => 'Closed',
            self::Cancelled => 'Cancelled',
        };
    }

    /** @return list<ShipmentStage> */
    public function next(): array
    {
        return match ($this) {
            self::Booked => [self::Assigned, self::Cancelled],
            self::Assigned => [self::Loading, self::Booked, self::Cancelled],
            self::Loading => [self::Loaded, self::Cancelled],
            self::Loaded => [self::InTransit, self::Delayed, self::Cancelled],
            self::InTransit => [self::Arrived, self::Delayed, self::Cancelled],
            self::Delayed => [self::InTransit, self::Arrived, self::Cancelled],
            self::Arrived => [self::Delivered, self::Cancelled],
            self::Delivered => [self::Closed],
            self::Closed, self::Cancelled => [],
        };
    }

    public function canMoveTo(self $to): bool
    {
        return in_array($to, $this->next(), true);
    }

    /** Stages where the truck is committed to the trip. */
    public function occupiesTruck(): bool
    {
        return in_array($this, [self::Assigned, self::Loading, self::Loaded, self::InTransit, self::Delayed, self::Arrived], true);
    }

    /** Stages where the truck is on the road and should be sending positions. */
    public function isMoving(): bool
    {
        return in_array($this, [self::InTransit, self::Delayed], true);
    }

    public function isActive(): bool
    {
        return ! in_array($this, [self::Delivered, self::Closed, self::Cancelled], true);
    }

    /** Progress for the public timeline, 0–100. */
    public function progress(): int
    {
        return match ($this) {
            self::Booked => 5, self::Assigned => 15, self::Loading => 25, self::Loaded => 35,
            self::InTransit, self::Delayed => 60, self::Arrived => 85, self::Delivered, self::Closed => 100, self::Cancelled => 0,
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

    /** @return list<string> */
    public static function activeValues(): array
    {
        return array_values(array_map(fn (self $s) => $s->value, array_filter(self::cases(), fn (self $s) => $s->isActive())));
    }
}
