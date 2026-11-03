<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View
    {
        return view('site.portal.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        $key = 'portal-login:'.mb_strtolower($data['email']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->withErrors(['email' => 'Too many tries. Wait '.RateLimiter::availableIn($key).' seconds.'])->onlyInput('email');
        }

        $user = User::query()->where('email', mb_strtolower($data['email']))->first();
        $ok = $user !== null && $user->hasRole(Role::Customer->value) && $user->is_active && Hash::check($data['password'], $user->password);
        if (! $ok) {
            RateLimiter::hit($key, 60);

            return back()->withErrors(['email' => 'Wrong email or password.'])->onlyInput('email');
        }

        RateLimiter::clear($key);
        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        return redirect()->intended(route('portal.home'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('portal.login');
    }

    public function showForgot(): View
    {
        return view('site.portal.forgot');
    }

    public function sendReset(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);
        $user = User::query()->where('email', mb_strtolower((string) $request->input('email')))->first();
        // Only customer accounts reset here; staff reset through an administrator. Same answer either way.
        if ($user !== null && $user->hasRole(Role::Customer->value) && $user->is_active) {
            Password::broker()->sendResetLink(['email' => $user->email]);
        }

        return back()->with('status', 'If that email has a portal account, we have sent a link to choose a new password.');
    }

    public function showReset(Request $request, string $token): View
    {
        return view('site.portal.reset', ['token' => $token, 'email' => (string) $request->query('email')]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required'], 'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(10)->letters()->numbers()],
        ]);

        $status = Password::broker()->reset($data, function (User $user, string $password): void {
            if (! $user->hasRole(Role::Customer->value)) {
                return;
            }
            $user->forceFill(['password' => $password, 'email_verified_at' => $user->email_verified_at ?? now()])->save();
        });

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('portal.login')->with('status', 'Password saved. Sign in with your new password.')
            : back()->withErrors(['email' => 'This link has expired or is not valid. Ask for a new one.'])->onlyInput('email');
    }
}
