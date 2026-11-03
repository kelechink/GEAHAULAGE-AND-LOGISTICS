<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\Role;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Portal sign-in pages: a signed-in customer goes straight to their portal.
 * Anyone else (a guest, or staff signed in to the back office in the same browser) sees the portal sign-in page,
 * instead of being bounced to the admin dashboard.
 */
class PortalGuest
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user instanceof User && $user->is_active && $user->customer_id !== null && $user->hasRole(Role::Customer->value)) {
            return redirect()->route('portal.home');
        }

        return $next($request);
    }
}
