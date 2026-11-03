<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\Shipment;
use App\Models\User;
use App\Notifications\StaleTripNotification;
use App\Settings\OperationsSettings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/**
 * Flags in-transit trips with no update for 60 minutes and alerts Operations once per silence (PRD 8.3).
 */
class FlagStaleTripsCommand extends Command
{
    protected $signature = 'tracking:flag-stale';

    protected $description = 'Alerts Operations about moving trips that have gone quiet.';

    public function handle(OperationsSettings $settings): int
    {
        $cutoff = now()->subMinutes($settings->stale_after_minutes);

        $stale = Shipment::query()
            ->whereIn('stage', ['in_transit', 'delayed'])
            ->whereNull('stale_alerted_at')
            ->where(fn ($q) => $q->whereNull('last_update_at')->orWhere('last_update_at', '<', $cutoff))
            ->get();

        if ($stale->isEmpty()) {
            return self::SUCCESS;
        }

        $recipients = User::query()->role([Role::Operations->value, Role::Management->value])->where('is_active', true)->get();
        foreach ($stale as $shipment) {
            Notification::send($recipients, new StaleTripNotification($shipment));
            $shipment->forceFill(['stale_alerted_at' => now()])->save();
        }

        $this->info($stale->count().' stale trips flagged.');

        return self::SUCCESS;
    }
}
