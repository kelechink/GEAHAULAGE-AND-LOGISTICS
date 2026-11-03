<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Domain\Ai\Alerts;
use App\Enums\ShipmentStage;
use App\Events\ShipmentStageChanged;

/** PRD 12.5: alert Operations and Management when a shipment is marked DELAYED. */
class AlertOnDelay
{
    public function __construct(private readonly Alerts $alerts) {}

    public function handle(ShipmentStageChanged $e): void
    {
        if ($e->shipment->stage === ShipmentStage::Delayed) {
            $this->alerts->shipmentDelayed($e->shipment);
        }
    }
}
