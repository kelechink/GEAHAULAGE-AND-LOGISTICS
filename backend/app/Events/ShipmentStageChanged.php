<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Shipment;
use App\Models\ShipmentEvent;
use Illuminate\Foundation\Events\Dispatchable;

class ShipmentStageChanged
{
    use Dispatchable;

    public function __construct(public Shipment $shipment, public ShipmentEvent $event) {}
}
