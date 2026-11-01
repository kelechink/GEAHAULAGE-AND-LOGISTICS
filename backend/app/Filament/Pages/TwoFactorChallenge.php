<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Domain\Auth\TwoFactor;
use App\Models\User;
use Filament\Pages\Page;
use Illuminate\Support\Facades\RateLimiter;

/** Asks for the authenticator code (or a recovery code) once per sign-in. */
class TwoFactorChallenge extends Page
{
    protected static string $view = 'filament.pages.two-factor-challenge';

    protected static ?string $slug = 'two-factor';

    protected static ?string $title = 'Enter your sign-in code';

    protected static bool $shouldRegisterNavigation = false;

    public string $code = '';

    public function mount(): void
    {
        $user = $this->user();
        if (! $user->hasTwoFactor()) {
            $this->redirect(TwoFactorSetup::getUrl());
        } elseif (session(TwoFactor::SESSION_KEY) === $user->id) {
            $this->redirect(filament()->getUrl());
        }
    }

    public function verify(TwoFactor $twoFactor): void
    {
        $this->validate(['code' => ['required', 'string', 'max:20']]);
        $key = '2fa-challenge:'.$this->user()->id;
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->addError('code', 'Too many tries. Wait '.RateLimiter::availableIn($key).' seconds.');

            return;
        }
        if (! $twoFactor->check($this->user(), $this->code)) {
            RateLimiter::hit($key, 60);
            $this->addError('code', 'That code is not right.');

            return;
        }
        RateLimiter::clear($key);
        session()->regenerate();
        session()->put(TwoFactor::SESSION_KEY, $this->user()->id);
        $this->redirect(session()->pull('url.intended', filament()->getUrl()));
    }

    private function user(): User
    {
        /** @var User */
        return auth()->user();
    }
}
