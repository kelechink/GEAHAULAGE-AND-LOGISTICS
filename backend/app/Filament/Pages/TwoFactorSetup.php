<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Domain\Auth\TwoFactor;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Hash;

/** Set up (or renew recovery codes for) two-factor sign-in. Reachable before 2FA is complete. */
class TwoFactorSetup extends Page
{
    protected static string $view = 'filament.pages.two-factor-setup';

    protected static ?string $slug = 'two-factor/setup';

    protected static ?string $title = 'Two-factor sign-in';

    protected static bool $shouldRegisterNavigation = false;

    public string $code = '';

    /** @var list<string> */
    public array $recoveryCodes = [];

    public function mount(): void
    {
        if (! $this->user()->hasTwoFactor() && ! session()->has('two_factor.pending_secret')) {
            session()->put('two_factor.pending_secret', TwoFactor::newSecret());
        }
    }

    public function secret(): string
    {
        return (string) session('two_factor.pending_secret');
    }

    public function qr(): string
    {
        return TwoFactor::qrSvg(TwoFactor::uri($this->user(), $this->secret()));
    }

    public function enabled(): bool
    {
        return $this->user()->hasTwoFactor();
    }

    public function confirm(TwoFactor $twoFactor): void
    {
        $this->validate(['code' => ['required', 'string', 'max:10']]);
        $codes = $twoFactor->confirm($this->user(), $this->secret(), $this->code);
        if ($codes === null) {
            $this->addError('code', 'That code is not right. Check the time on your phone and try the newest code.');

            return;
        }
        session()->forget('two_factor.pending_secret');
        session()->put(TwoFactor::SESSION_KEY, $this->user()->id);
        $this->recoveryCodes = $codes;
        $this->code = '';
        Notification::make()->success()->title('Two-factor sign-in is on.')->send();
    }

    public function newRecoveryCodes(): void
    {
        if (! $this->enabled() || session(TwoFactor::SESSION_KEY) !== $this->user()->id) {
            return;
        }
        $codes = TwoFactor::newRecoveryCodes();
        $this->user()->forceFill(['two_factor_recovery_codes' => array_map(fn (string $c): string => Hash::make($c), $codes)])->save();
        $this->recoveryCodes = $codes;
        activity()->performedOn($this->user())->causedBy($this->user())->log('New two-factor recovery codes made');
    }

    private function user(): User
    {
        /** @var User */
        return auth()->user();
    }
}
