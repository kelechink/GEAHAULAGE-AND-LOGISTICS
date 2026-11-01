<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * GET /health for UptimeRobot (PRD 6). 200 with "ok" when the database, cache, cron scheduler and backups
 * look healthy; 503 otherwise. Shows no data, only check names and states.
 */
class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $checks = [];

        try {
            DB::select('select 1');
            $checks['database'] = 'ok';
        } catch (Throwable) {
            $checks['database'] = 'down';
        }

        try {
            Cache::put('health:ping', 1, 60);
            $checks['cache'] = Cache::get('health:ping') === 1 ? 'ok' : 'down';
        } catch (Throwable) {
            $checks['cache'] = 'down';
        }

        // The scheduler writes a heartbeat every minute (routes/console.php). Older than 5 minutes = cron is not running.
        $beat = Cache::get('schedule:heartbeat');
        $checks['scheduler'] = is_string($beat) && Carbon::parse($beat)->gt(now()->subMinutes(5)) ? 'ok' : 'stale';

        try {
            $pending = DB::table('jobs')->where('created_at', '<', now()->subMinutes(15)->getTimestamp())->count();
            $checks['queue'] = $pending === 0 ? 'ok' : 'backlog';
        } catch (Throwable) {
            $checks['queue'] = 'unknown';
        }

        $checks['backup'] = $this->backupState();

        $healthy = $checks['database'] === 'ok' && $checks['cache'] === 'ok' && $checks['scheduler'] === 'ok';

        return response()->json(['status' => $healthy ? 'ok' : 'degraded', 'checks' => $checks, 'time' => now()->toIso8601String()], $healthy ? 200 : 503);
    }

    private function backupState(): string
    {
        try {
            $dir = (string) config('backup.backup.name');
            $files = Storage::disk('local')->files($dir);
            if ($files === []) {
                return 'none yet';
            }
            $latest = max(array_map(fn (string $f): int => Storage::disk('local')->lastModified($f), $files));

            return $latest > now()->subDays(2)->getTimestamp() ? 'ok' : 'old';
        } catch (Throwable) {
            return 'unknown';
        }
    }
}
