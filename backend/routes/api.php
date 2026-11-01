<?php

declare(strict_types=1);

use App\Http\Controllers\Api\ConnectorWebhookController;
use App\Http\Controllers\Api\Driver\AuthController as DriverAuth;
use App\Http\Controllers\Api\Driver\TripController;
use App\Http\Controllers\Site\TrackController;
use App\Http\Middleware\EnsureDriver;
use Illuminate\Support\Facades\Route;

// Public tracking poll (PRD 8.5). Rate-limited per IP.
Route::get('/track/{code}/updates', [TrackController::class, 'updates'])->middleware('throttle:tracking-poll')->name('api.track.updates');

// Tracker connectors push positions here (PRD 8.4). Each request must be signed with the connector's secret.
Route::post('/connectors/{name}/webhook', ConnectorWebhookController::class)->middleware('throttle:120,1')->name('api.connectors.webhook');

// Android driver app (PRD 13). Phone + PIN sign-in issues a Sanctum token with the "driver" ability.
Route::prefix('driver')->name('api.driver.')->group(function (): void {
    Route::post('/login', [DriverAuth::class, 'login'])->middleware('throttle:driver-login')->name('login');

    Route::middleware(['auth:sanctum', EnsureDriver::class, 'throttle:driver-api'])->group(function (): void {
        Route::get('/me', [DriverAuth::class, 'me'])->name('me');
        Route::post('/logout', [DriverAuth::class, 'logout'])->name('logout');
        Route::get('/trips', [TripController::class, 'index'])->name('trips');
        Route::get('/trips/{trip}', [TripController::class, 'show'])->whereNumber('trip')->name('trips.show');
        Route::post('/positions', [TripController::class, 'positions'])->name('positions');
        Route::post('/trips/{trip}/stage', [TripController::class, 'stage'])->whereNumber('trip')->name('trips.stage');
        Route::post('/trips/{trip}/deliver', [TripController::class, 'deliver'])->whereNumber('trip')->name('trips.deliver');
        Route::post('/trips/{trip}/issues', [TripController::class, 'issue'])->whereNumber('trip')->name('trips.issues');
    });
});
