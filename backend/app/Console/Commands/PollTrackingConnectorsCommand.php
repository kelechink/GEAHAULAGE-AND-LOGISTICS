<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Tracking\Connectors\ConnectorRegistry;
use App\Domain\Tracking\PositionRecorder;
use Illuminate\Console\Command;
use Throwable;

/**
 * Polls enabled tracker connectors (Traccar today) every minute (PRD 8.4).
 */
class PollTrackingConnectorsCommand extends Command
{
    protected $signature = 'tracking:poll-connectors';

    protected $description = 'Pulls new GPS positions from enabled tracker connectors.';

    public function handle(ConnectorRegistry $registry, PositionRecorder $recorder): int
    {
        foreach ($registry->pollable() as ['row' => $row, 'connector' => $connector]) {
            $since = $row->last_polled_at ?? now()->subMinutes(10);
            $count = 0;
            try {
                foreach ($connector->fetchPositions($since) as $p) {
                    if ($p->deviceId !== null && $recorder->recordForDevice($p->deviceId, $p) !== null) {
                        $count++;
                    }
                }
                $row->forceFill(['last_polled_at' => now()])->save();
                $this->info("{$connector->name()}: {$count} positions.");
            } catch (Throwable $e) {
                report($e);
                $this->error("{$connector->name()}: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
