<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

/*
| Shared hosting has no long-running workers. cPanel cron runs `schedule:run`
| every minute, and the scheduler drains the database queue each time.
*/
Schedule::command('queue:work --stop-when-empty --tries=3 --max-time=50')
    ->everyMinute()
    ->withoutOverlapping();

Schedule::command('activitylog:clean')->daily();
