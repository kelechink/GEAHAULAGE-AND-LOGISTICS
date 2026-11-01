<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

/*
| Shared hosting has no long-running workers. cPanel cron runs `schedule:run`
| every minute, and the scheduler drains the database queue each time.
*/
Schedule::command('queue:work --stop-when-empty --tries=3 --max-time=50')
    ->everyMinute()
    ->withoutOverlapping();

Schedule::command('activitylog:clean')->daily();

Schedule::command('crm:mark-missed-follow-ups')->hourly();

// Hybrid tracking (PRD 8)
Schedule::command('tracking:poll-connectors')->everyMinute()->withoutOverlapping();
Schedule::command('tracking:flag-stale')->everyFiveMinutes();
Schedule::command('tracking:downsample')->dailyAt('02:30');

// Finance (PRD 10.3)
Schedule::command('finance:daily')->dailyAt('07:30')->timezone('Africa/Lagos');

// AI and alerts (PRD 12.5)
Schedule::command('ai:daily-digest')->dailyAt('07:00')->timezone('Africa/Lagos');
Schedule::command('alerts:sweep')->hourly();

// Monitoring: heartbeat for /health, so UptimeRobot notices if cron stops (PRD 6).
Schedule::call(fn () => Cache::forever('schedule:heartbeat', now()->toIso8601String()))->everyMinute()->name('heartbeat');

// Backups (PRD 16): database + uploads every night, 30 days kept, failures emailed.
Schedule::command('backup:clean')->dailyAt('01:00')->timezone('Africa/Lagos');
Schedule::command('backup:run')->dailyAt('01:30')->timezone('Africa/Lagos');
Schedule::command('backup:monitor')->dailyAt('09:00')->timezone('Africa/Lagos');
