<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Shipment;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class StaleTripNotification extends Notification
{
    use Queueable;

    public function __construct(public Shipment $shipment) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        $since = $this->shipment->last_update_at?->diffForHumans() ?? 'ever';

        return FilamentNotification::make()
            ->title("No update on {$this->shipment->tracking_code}")
            ->body("{$this->shipment->origin} → {$this->shipment->destination}. Last update {$since}. Call the driver and post a manual update.")
            ->warning()
            ->getDatabaseMessage();
    }
}
