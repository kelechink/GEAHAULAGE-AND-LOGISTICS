<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Domain\Finance\InvoiceService;
use App\Enums\ShipmentStage;
use App\Events\ShipmentStageChanged;

/** DELIVERED automatically creates a draft invoice for Finance (PRD 8.2). */
class DraftInvoiceOnDelivery
{
    public function __construct(private readonly InvoiceService $invoices) {}

    public function handle(ShipmentStageChanged $e): void
    {
        if ($e->event->to_stage === ShipmentStage::Delivered) {
            $this->invoices->draftFromShipment($e->shipment);
        }
    }
}
