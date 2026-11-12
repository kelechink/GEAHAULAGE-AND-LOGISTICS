<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Ai\AiGateway;
use App\Domain\Ai\AiProvider;
use App\Domain\Finance\EInvoice\EInvoiceProvider;
use App\Domain\Finance\EInvoice\MockEInvoiceProvider;
use App\Enums\Role;
use App\Http\Responses\StaffLoginResponse;
use App\Models\MaintenanceRecord;
use App\Models\User;
use App\Observers\MaintenanceRecordObserver;
use App\Policies\ActivityPolicy;
use App\Support\Senders;
use App\View\Composers\SiteComposer;
use Filament\Facades\Filament;
use Filament\Http\Responses\Auth\Contracts\LoginResponse;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Notifications\Messages\MailMessage;
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
        // Staff sign-in returns only to back-office pages (never to a portal page saved in the session).
        $this->app->bind(LoginResponse::class, StaffLoginResponse::class);
    }

    private static function resetUrl(User $user, string $token): string
    {
        return $user->hasRole(Role::Customer->value)
            ? route('portal.password.reset', ['token' => $token, 'email' => $user->email])
            : Filament::getPanel('admin')->getResetPasswordUrl($token, $user);
    }

    public function boot(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());

        // Super Admin (FigureWeb) passes every permission check.
        Gate::before(static fn (User $user): ?bool => $user->isSuperAdmin() ? true : null);

        Gate::policy(Activity::class, ActivityPolicy::class);

        MaintenanceRecord::observe(MaintenanceRecordObserver::class);

        View::composer('site.*', SiteComposer::class);

        // "Forgot password" links: customers reset in the portal, staff in the back office. Both are GEA-branded.
        ResetPassword::createUrlUsing(fn (User $user, string $token): string => self::resetUrl($user, $token));
        ResetPassword::toMailUsing(function (User $user, string $token): MailMessage {
            $customer = $user->hasRole(Role::Customer->value);
            $from = Senders::from($customer ? 'support' : 'info', $customer ? 'Support' : null);

            return (new MailMessage)
                ->from($from->address, $from->name)
                ->subject($customer ? 'Choose a new password for the GEA customer portal' : 'Choose a new password for the GEA Logistics Platform')
                ->greeting('Hello '.$user->name.',')
                ->line($customer ? 'We received a request to reset the password for your GEA customer portal account.' : 'We received a request to reset the password for your GEA Logistics Platform account.')
                ->action('Choose a new password', self::resetUrl($user, $token))
                ->line('This link works for '.(int) ceil(config('auth.passwords.users.expire') / 60).' hours. If you did not ask for this, you can ignore this email: your password stays the same.')
                ->salutation('GEA Haulage and Logistics');
        });

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
