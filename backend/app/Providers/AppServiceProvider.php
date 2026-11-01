<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Ai\AiGateway;
use App\Domain\Ai\AiProvider;
use App\Domain\Finance\EInvoice\EInvoiceProvider;
use App\Domain\Finance\EInvoice\MockEInvoiceProvider;
use App\Models\MaintenanceRecord;
use App\Models\User;
use App\Observers\MaintenanceRecordObserver;
use App\Policies\ActivityPolicy;
use App\View\Composers\SiteComposer;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Spatie\Activitylog\Models\Activity;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // FIRS e-invoicing: the mock adapter until GEA's compliance phase and credentials are confirmed (PRD 10.3).
        $this->app->bind(EInvoiceProvider::class, MockEInvoiceProvider::class);
        $this->app->bind(AiProvider::class, AiGateway::class);
    }

    public function boot(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());

        // Super Admin (FigureWeb) passes every permission check.
        Gate::before(static fn (User $user): ?bool => $user->isSuperAdmin() ? true : null);

        Gate::policy(Activity::class, ActivityPolicy::class);

        MaintenanceRecord::observe(MaintenanceRecordObserver::class);

        View::composer('site.*', SiteComposer::class);

        // Website forms: no paid captcha, so a honeypot plus a per-IP limit (PRD 7).
        RateLimiter::for('forms', static fn (Request $request): Limit => Limit::perMinute(5)->by($request->ip()));
        RateLimiter::for('tracking', static fn (Request $request): Limit => Limit::perMinute(30)->by($request->ip()));
        // One open tracking page polls every 15 s (4/min); allow a few tabs per visitor.
        // Driver app: 5 PIN attempts a minute per phone and IP; generous limit for queued uploads.
        RateLimiter::for('driver-login', static fn (Request $request): Limit => Limit::perMinute(5)->by($request->ip().'|'.$request->input('phone')));
        RateLimiter::for('driver-api', static fn (Request $request): Limit => Limit::perMinute(120)->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip())));
        // Website assistant (PRD 12.1): per-IP limits on messages and on the reply poll.
        RateLimiter::for('assistant', static fn (Request $request): Limit => Limit::perMinute(10)->by($request->ip()));
        RateLimiter::for('assistant-poll', static fn (Request $request): Limit => Limit::perMinute(30)->by($request->ip()));
        RateLimiter::for('tracking-poll', static fn (Request $request): Limit => Limit::perMinute(20)->by($request->ip()));
    }
}
