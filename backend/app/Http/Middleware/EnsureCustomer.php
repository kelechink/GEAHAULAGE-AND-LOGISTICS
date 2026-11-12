<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\Role;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Customer portal gate: an active user with the Customer role, linked to a customer account. */
class EnsureCustomer
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return redirect()->guest(route('portal.login'));
        }
        if (! $user->is_active || $user->customer_id === null || ! $user->hasRole(Role::Customer->value)) {
            if ($user->hasRole(Role::Customer->value)) {
                Auth::logout();
                $request->session()->invalidate();

                return redirect()->route('portal.login')->withErrors(['email' => 'Your portal access is switched off. Contact GEA.']);
            }

            // Staff signed in to the back office in this browser see the portal sign-in page, not the admin dashboard.
            return redirect()->route('portal.login');
        }

        return $next($request);
    }
}
