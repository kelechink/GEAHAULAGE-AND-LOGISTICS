<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Driver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Runs after auth:sanctum. Only active drivers holding a "driver" token reach the driver API. */
class EnsureDriver
{
    public function handle(Request $request, Closure $next): Response
    {
        $driver = $request->user('sanctum');
        if (! $driver instanceof Driver || ! $driver->tokenCan('driver')) {
            return response()->json(['message' => 'Driver sign-in required.'], 403);
        }
        if (! $driver->is_active) {
            $driver->currentAccessToken()->delete();

            return response()->json(['message' => 'Your app access is switched off. Call Operations.'], 403);
        }

        $version = $request->header('X-App-Version');
        if (is_string($version) && $version !== '' && $version !== $driver->app_version) {
            $driver->forceFill(['app_version' => mb_substr($version, 0, 20)])->saveQuietly();
        }

        return $next($request);
    }
}
