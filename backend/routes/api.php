<?php

declare(strict_types=1);

use App\Http\Controllers\Api\ConnectorWebhookController;
use App\Http\Controllers\Site\TrackController;
use Illuminate\Support\Facades\Route;

// Public tracking poll (PRD 8.5). Rate-limited per IP.
Route::get('/track/{code}/updates', [TrackController::class, 'updates'])->middleware('throttle:tracking-poll')->name('api.track.updates');

// Tracker connectors push positions here (PRD 8.4). Each request must be signed with the connector's secret.
Route::post('/connectors/{name}/webhook', ConnectorWebhookController::class)->middleware('throttle:120,1')->name('api.connectors.webhook');
