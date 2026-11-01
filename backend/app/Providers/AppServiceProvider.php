<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
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
        //
    }

    public function boot(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());

        // Super Admin (FigureWeb) passes every permission check.
        Gate::before(static fn (User $user): ?bool => $user->isSuperAdmin() ? true : null);

        Gate::policy(Activity::class, ActivityPolicy::class);

        View::composer('site.*', SiteComposer::class);

        // Website forms: no paid captcha, so a honeypot plus a per-IP limit (PRD 7).
        RateLimiter::for('forms', static fn (Request $request): Limit => Limit::perMinute(5)->by($request->ip()));
        RateLimiter::for('tracking', static fn (Request $request): Limit => Limit::perMinute(30)->by($request->ip()));
        // One open tracking page polls every 15 s (4/min); allow a few tabs per visitor.
        RateLimiter::for('tracking-poll', static fn (Request $request): Limit => Limit::perMinute(20)->by($request->ip()));
    }
}
