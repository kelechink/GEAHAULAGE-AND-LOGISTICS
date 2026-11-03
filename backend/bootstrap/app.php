<?php

declare(strict_types=1);

use App\Support\ErrorAlerter;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Guests asking for a portal page sign in to the portal; signed-in customers skip the sign-in page.
        $middleware->redirectGuestsTo(fn (): string => route('portal.login'));
        $middleware->redirectUsersTo(fn (): string => route('portal.home'));
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // The API (driver app, tracking poll, webhooks) always answers in JSON, including 401s.
        $exceptions->shouldRenderJsonWhen(fn (Request $request): bool => $request->is('api/*') || $request->expectsJson());
        // Email the admin about real errors (throttled). Validation, 404 and auth errors are not "reported", so they never alert.
        $exceptions->report(fn (Throwable $e) => ErrorAlerter::report($e));
    })->create();
