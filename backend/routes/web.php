<?php

declare(strict_types=1);

use App\Http\Controllers\DocumentController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\Portal\AuthController as PortalAuth;
use App\Http\Controllers\Portal\PortalController;
use App\Http\Controllers\Site\CareersController;
use App\Http\Controllers\Site\PageController;
use App\Http\Controllers\Site\QuoteController;
use App\Http\Controllers\Site\TrackController;
use App\Http\Middleware\EnsureCustomer;
use Illuminate\Support\Facades\Route;

Route::get('/', [PageController::class, 'home'])->name('home');
Route::get('/services/{service}', [PageController::class, 'service'])->name('services.show');
Route::get('/privacy', [PageController::class, 'privacy'])->name('privacy');
Route::get('/terms', [PageController::class, 'terms'])->name('terms');
Route::get('/sitemap.xml', [PageController::class, 'sitemap'])->name('sitemap');

Route::get('/quote', [QuoteController::class, 'show'])->name('quote');
Route::post('/quote', [QuoteController::class, 'store'])->middleware('throttle:forms')->name('quote.store');

Route::get('/careers', [CareersController::class, 'show'])->name('careers');
Route::post('/careers', [CareersController::class, 'store'])->middleware('throttle:forms')->name('careers.store');

Route::get('/track', [TrackController::class, 'search'])->middleware('throttle:tracking')->name('track');
Route::get('/track/{code}', [TrackController::class, 'show'])->middleware('throttle:tracking')->name('track.show');
Route::post('/track/{code}/subscribe', [TrackController::class, 'subscribe'])->middleware('throttle:forms')->name('track.subscribe');

// Uptime monitoring (PRD 6): UptimeRobot checks this for "ok".
Route::get('/health', HealthController::class)->middleware('throttle:60,1')->name('health');

// Documents behind signed, expiring links (PRD 16). Sent to customers by email and used by staff.
Route::middleware(['signed', 'throttle:60,1'])->prefix('documents')->name('documents.')->group(function (): void {
    Route::get('/invoices/{invoice}', [DocumentController::class, 'invoice'])->name('invoice');
    Route::get('/proof/{proof}/{kind}', [DocumentController::class, 'proof'])->whereIn('kind', ['photo', 'signature', 'document'])->name('proof');
});

// Customer portal (PRD 6): shipments, proof of delivery, invoices and quotes for the customer's own account.
Route::prefix('portal')->name('portal.')->group(function (): void {
    Route::middleware('guest')->group(function (): void {
        Route::get('/login', [PortalAuth::class, 'showLogin'])->name('login');
        Route::post('/login', [PortalAuth::class, 'login'])->middleware('throttle:forms')->name('login.attempt');
        Route::get('/forgot-password', [PortalAuth::class, 'showForgot'])->name('password.request');
        Route::post('/forgot-password', [PortalAuth::class, 'sendReset'])->middleware('throttle:forms')->name('password.email');
        Route::get('/reset-password/{token}', [PortalAuth::class, 'showReset'])->name('password.reset');
        Route::post('/reset-password', [PortalAuth::class, 'reset'])->middleware('throttle:forms')->name('password.update');
    });
    Route::middleware(EnsureCustomer::class)->group(function (): void {
        Route::get('/', [PortalController::class, 'home'])->name('home');
        Route::get('/invoices/{invoice}', [PortalController::class, 'invoice'])->name('invoice');
        Route::get('/quotes/{quote}', [PortalController::class, 'quote'])->name('quote');
        Route::post('/logout', [PortalAuth::class, 'logout'])->name('logout');
    });
});
