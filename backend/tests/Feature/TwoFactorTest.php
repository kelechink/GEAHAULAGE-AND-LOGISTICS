<?php

declare(strict_types=1);

use App\Domain\Auth\TwoFactor;
use App\Enums\Role;
use App\Filament\Pages\TwoFactorChallenge;
use App\Filament\Pages\TwoFactorSetup;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Models\User;
use Livewire\Livewire;

beforeEach(fn () => config(['gea.two_factor_enforced' => true]));

function withTwoFactor(User $user): string
{
    $secret = TwoFactor::newSecret();
    $user->forceFill(['two_factor_secret' => $secret, 'two_factor_recovery_codes' => [bcrypt('ABCDE-12345')], 'two_factor_confirmed_at' => now()])->save();

    return $secret;
}

it('produces the RFC 6238 reference codes', function () {
    // RFC 6238 appendix B, SHA-1 secret "12345678901234567890" (base32 below).
    $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';
    expect(TwoFactor::code($secret, 59, 8))->toBe('94287082')
        ->and(TwoFactor::code($secret, 1111111109, 8))->toBe('07081804')
        ->and(TwoFactor::code($secret, 2000000000, 8))->toBe('69279037')
        ->and(strlen(TwoFactor::newSecret()))->toBe(32);
});

it('sends staff without two-factor to set it up, and staff with it to the code page', function () {
    $ops = $this->userWithRole(Role::Operations);
    $this->actingAs($ops)->get('/admin')->assertRedirect('/admin/two-factor/setup');
    $this->actingAs($ops)->get('/admin/two-factor/setup')->assertOk()->assertSee('Protect your account');

    $md = $this->userWithRole(Role::Management);
    withTwoFactor($md);
    $this->actingAs($md)->get('/admin/shipments')->assertRedirect('/admin/two-factor');
});

it('turns two-factor on only with a correct code and shows recovery codes once', function () {
    $user = $this->userWithRole(Role::Finance);
    $page = Livewire::actingAs($user)->test(TwoFactorSetup::class);
    $secret = session('two_factor.pending_secret');

    $page->set('code', '000000')->call('confirm')->assertHasErrors('code');
    expect($user->refresh()->hasTwoFactor())->toBeFalse();

    $page->set('code', TwoFactor::code($secret))->call('confirm')->assertHasNoErrors();
    expect($page->get('recoveryCodes'))->toHaveCount(8)
        ->and($user->refresh()->hasTwoFactor())->toBeTrue()
        ->and($user->two_factor_secret)->toBe($secret)
        ->and(DB::table('users')->where('id', $user->id)->value('two_factor_secret'))->not->toBe($secret); // encrypted at rest
    $this->get('/admin')->assertOk();
});

it('lets staff in with an app code, once per code, and with a single-use recovery code', function () {
    $user = $this->userWithRole(Role::Sales);
    $secret = withTwoFactor($user);
    $code = TwoFactor::code($secret);

    Livewire::actingAs($user)->test(TwoFactorChallenge::class)->set('code', $code)->call('verify')->assertHasNoErrors()->assertRedirect();
    $this->get('/admin')->assertOk();

    session()->forget(TwoFactor::SESSION_KEY);
    Livewire::actingAs($user)->test(TwoFactorChallenge::class)->set('code', $code)->call('verify')->assertHasErrors('code'); // replay refused

    Livewire::actingAs($user)->test(TwoFactorChallenge::class)->set('code', 'abcde-12345')->call('verify')->assertHasNoErrors();
    expect($user->refresh()->two_factor_recovery_codes)->toBe([]);
});

it('stops guessing after five wrong codes', function () {
    $user = $this->userWithRole(Role::Sales);
    withTwoFactor($user);
    $page = Livewire::actingAs($user)->test(TwoFactorChallenge::class);
    foreach (range(1, 5) as $i) {
        $page->set('code', '11111'.$i)->call('verify');
    }
    $page->set('code', '222222')->call('verify')->assertHasErrors(['code']);
    expect(collect($page->errors()->get('code'))->implode(' '))->toContain('Too many tries');
});

it('lets the administrator reset a lost two-factor device', function () {
    $admin = $this->userWithRole(Role::SuperAdmin);
    withTwoFactor($admin);
    session([TwoFactor::SESSION_KEY => $admin->id]);
    $staff = $this->userWithRole(Role::Operations);
    withTwoFactor($staff);

    Livewire::actingAs($admin)->test(ListUsers::class)->callTableAction('resetTwoFactor', $staff);

    expect($staff->refresh()->hasTwoFactor())->toBeFalse();
});

it('does not apply to the driver API or the public site', function () {
    $this->get('/')->assertOk();
    $this->getJson('/api/driver/trips')->assertUnauthorized();
});
