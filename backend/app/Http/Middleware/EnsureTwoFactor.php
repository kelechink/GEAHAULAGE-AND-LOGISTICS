<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Auth\TwoFactor;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Admin panel gate (PRD 6, 16): staff must set up an authenticator app, then enter a code once per sign-in.
 * Runs after Filament's Authenticate, also on Livewire requests (Filament makes auth middleware persistent).
 */
class EnsureTwoFactor
{
    private const OPEN_ROUTES = ['filament.admin.pages.two-factor.setup', 'filament.admin.pages.two-factor', 'filament.admin.auth.logout'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! TwoFactor::enforced() || ! $user instanceof User || $request->routeIs(...self::OPEN_ROUTES)) {
            return $next($request);
        }

        if (! $user->hasTwoFactor()) {
            return redirect()->route('filament.admin.pages.two-factor.setup');
        }
        if ($request->session()->get(TwoFactor::SESSION_KEY) !== $user->id) {
            $request->session()->put('url.intended', $request->fullUrl());

            return redirect()->route('filament.admin.pages.two-factor');
        }

        return $next($request);
    }
}
