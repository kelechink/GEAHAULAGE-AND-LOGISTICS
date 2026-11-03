<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * "Laravel log alerts by email" (PRD 6): emails ALERT_EMAIL when an error is reported, at most once per
 * error type and place every 30 minutes, so a broken page does not flood the inbox. Never throws.
 */
final class ErrorAlerter
{
    public static function report(Throwable $e): void
    {
        $to = config('gea.alert_email');
        if (! is_string($to) || $to === '' || app()->runningUnitTests()) {
            return;
        }
        try {
            $key = 'error-alert:'.md5($e::class.$e->getFile().$e->getLine());
            if (! Cache::add($key, true, now()->addMinutes(30))) {
                return;
            }
            $url = app()->runningInConsole() ? 'console' : request()->fullUrl();
            Mail::raw(
                'An error happened on '.config('app.url')."\n\n".$e::class.': '.$e->getMessage()."\n".$e->getFile().':'.$e->getLine()
                ."\nWhere: {$url}\nTime: ".now('Africa/Lagos')->toDayDateTimeString()."\n\nFull details are in storage/logs/laravel.log.",
                fn ($m) => $m->to($to)->subject('[GEA] Error: '.mb_strimwidth($e->getMessage() ?: $e::class, 0, 80, '…')),
            );
        } catch (Throwable) {
            // Alerting must never break the request.
        }
    }
}
