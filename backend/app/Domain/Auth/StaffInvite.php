<?php

declare(strict_types=1);

namespace App\Domain\Auth;

use App\Enums\Role;
use App\Mail\StaffInviteMail;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Back-office accounts are created without a password anyone else knows: the new staff member gets a
 * GEA-branded email with a link to choose their own, then sets up two-factor at first sign-in.
 */
final class StaffInvite
{
    /** @param  list<Role>  $roles */
    public function create(string $name, string $email, array $roles, ?string $phone = null, ?User $by = null): User
    {
        $email = Str::lower(trim($email));
        if ($roles === [] || array_filter($roles, static fn (Role $r): bool => ! $r->canUseAdmin()) !== []) {
            throw new InvalidArgumentException('Give at least one back-office role (not Driver or Customer).');
        }
        if (User::query()->where('email', $email)->exists()) {
            throw new InvalidArgumentException("{$email} already has an account.");
        }

        $user = User::query()->create(['name' => trim($name), 'email' => $email, 'phone' => $phone, 'password' => Str::random(48)]);
        $user->forceFill(['is_active' => true])->save();
        $user->syncRoles(array_map(static fn (Role $r): string => $r->value, $roles));
        activity()->performedOn($user)->causedBy($by)->log("Created back-office account for {$email}");

        $this->send($user, $by);

        return $user;
    }

    /** Sends (or re-sends) the invitation. A new link replaces any earlier one. */
    public function send(User $user, ?User $by = null): void
    {
        if (! $user->is_active || ! $user->canAccessPanel(Filament::getPanel('admin'))) {
            throw new InvalidArgumentException('Only active back-office users can be invited.');
        }

        $token = Password::broker()->createToken($user);
        $link = Filament::getPanel('admin')->getResetPasswordUrl($token, $user);
        Mail::to($user->email)->queue(new StaffInviteMail($user, $link));
        activity()->performedOn($user)->causedBy($by)->log("Sent back-office invitation to {$user->email}");
    }
}
