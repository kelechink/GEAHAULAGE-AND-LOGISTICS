<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Crm\FollowUp;
use Illuminate\Console\Command;

class MarkMissedFollowUpsCommand extends Command
{
    protected $signature = 'crm:mark-missed-follow-ups';

    protected $description = 'Marks open follow-ups as missed once they are more than a day overdue.';

    public function handle(): int
    {
        $count = FollowUp::query()
            ->where('status', 'open')
            ->where('due_at', '<', now()->subDay())
            ->update(['status' => 'missed']);

        $this->info("{$count} follow-ups marked as missed.");

        return self::SUCCESS;
    }
}
