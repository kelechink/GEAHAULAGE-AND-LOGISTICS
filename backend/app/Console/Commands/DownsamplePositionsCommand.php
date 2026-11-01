<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Position;
use Illuminate\Console\Command;

/**
 * Keeps raw positions for 90 days, then thins them to one point per 10 minutes per shipment (PRD 8.3).
 */
class DownsamplePositionsCommand extends Command
{
    protected $signature = 'tracking:downsample {--days=90}';

    protected $description = 'Thins out old GPS positions to one per 10 minutes.';

    public function handle(): int
    {
        $cutoff = now()->subDays((int) $this->option('days'));
        $deleted = 0;

        Position::query()->where('recorded_at', '<', $cutoff)->orderBy('shipment_id')->orderBy('recorded_at')
            ->select(['id', 'shipment_id', 'recorded_at'])
            ->chunk(2000, function ($chunk) use (&$deleted): void {
                $keep = [];
                $drop = [];
                foreach ($chunk as $p) {
                    $bucket = $p->shipment_id.'|'.intdiv($p->recorded_at->getTimestamp(), 600);
                    if (isset($keep[$bucket])) {
                        $drop[] = $p->id;
                    } else {
                        $keep[$bucket] = true;
                    }
                }
                if ($drop !== []) {
                    $deleted += Position::query()->whereIn('id', $drop)->delete();
                }
            });

        $this->info("{$deleted} old positions removed.");

        return self::SUCCESS;
    }
}
