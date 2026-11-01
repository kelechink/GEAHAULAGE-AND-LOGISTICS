<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\ShipmentStageChanged;
use App\Mail\ShipmentUpdateMail;
use Illuminate\Support\Facades\Mail;

/**
 * Emails customers who chose "Get updates" on the tracking page (PRD 8.5). Private events are never sent.
 */
class NotifyTrackingSubscribers
{
    public function handle(ShipmentStageChanged $e): void
    {
        if (! $e->event->is_public || $e->event->to_stage === null) {
            return;
        }

        foreach ($e->shipment->subscriptions()->pluck('email') as $email) {
            Mail::to($email)->queue(new ShipmentUpdateMail($e->shipment, $e->event));
        }
    }
}
