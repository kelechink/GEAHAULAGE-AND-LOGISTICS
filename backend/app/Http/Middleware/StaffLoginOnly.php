<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Staff sign-in page: someone signed in who cannot use the back office (a customer or driver in the same browser)
 * is signed out and shown the staff sign-in form, instead of being sent to /admin and refused.
 */
class StaffLoginOnly
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $panel = Filament::getCurrentPanel();
        if ($user instanceof User && $panel !== null && $request->routeIs($panel->generateRouteName('auth.login')) && ! $user->canAccessPanel($panel)) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->to($panel->getLoginUrl() ?? '/admin/login');
        }

        return $next($request);
    }
}
