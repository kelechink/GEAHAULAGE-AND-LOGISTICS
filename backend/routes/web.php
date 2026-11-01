<?php

declare(strict_types=1);

use App\Http\Controllers\Site\CareersController;
use App\Http\Controllers\Site\PageController;
use App\Http\Controllers\Site\QuoteController;
use App\Http\Controllers\Site\TrackController;
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
