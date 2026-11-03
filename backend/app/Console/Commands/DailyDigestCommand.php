<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Ai\DailyDigest;
use App\Enums\Permission;
use App\Enums\Role;
use App\Mail\DailyDigestMail;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class DailyDigestCommand extends Command
{
    protected $signature = 'ai:daily-digest';

    protected $description = 'Builds the 7am briefing and emails it to Management and Operations (PRD 12.5).';

    public function handle(DailyDigest $digest): int
    {
        $d = $digest->build();
        $to = User::query()->role([Role::Management->value, Role::Operations->value])->where('is_active', true)->whereNotNull('email')->get();
        foreach ($to as $u) {
            Mail::to($u->email)->queue(new DailyDigestMail($d, $u->can(Permission::FinanceView->value)));
        }
        $this->info("Digest for {$d->day->toDateString()} sent to {$to->count()} people.");

        return self::SUCCESS;
    }
}
