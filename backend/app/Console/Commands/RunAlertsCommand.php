<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Ai\Alerts;
use Illuminate\Console\Command;

class RunAlertsCommand extends Command
{
    protected $signature = 'alerts:sweep';

    protected $description = 'Rule-based alerts: newly overdue invoices and leads not contacted within one working day (PRD 12.5).';

    public function handle(Alerts $alerts): int
    {
        $r = $alerts->sweep();
        $this->info("{$r['overdue']} overdue and {$r['uncontacted']} uncontacted alerts sent.");

        return self::SUCCESS;
    }
}
