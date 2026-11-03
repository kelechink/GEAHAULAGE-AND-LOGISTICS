<?php

declare(strict_types=1);

namespace App\Domain\Portal;

use App\Enums\Role;
use App\Mail\PortalInviteMail;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Customer portal accounts (PRD 6, optional customer portal). Staff invite a customer contact by email;
 * the invitation link lets them choose their own password. Portal users can only see their own customer's
 * shipments, invoices and quotes.
 */
final class PortalAccess
{
    public function invite(Customer $customer, string $name, string $email, ?User $by = null): User
    {
        $email = Str::lower(trim($email));
        $existing = User::query()->where('email', $email)->first();
        if ($existing !== null && ($existing->customer_id !== $customer->id || ! $existing->hasRole(Role::Customer->value))) {
            throw new InvalidArgumentException('That email already belongs to another account.');
        }

        $user = $existing ?? User::query()->create(['name' => trim($name), 'email' => $email, 'password' => Str::random(40)]);
        $user->forceFill(['customer_id' => $customer->id, 'is_active' => true])->save();
        $user->syncRoles([Role::Customer->value]);

        $token = Password::broker()->createToken($user);
        Mail::to($email)->queue(new PortalInviteMail($user, route('portal.password.reset', ['token' => $token, 'email' => $email]), $customer->name));
        activity()->performedOn($customer)->causedBy($by)->log("Invited {$email} to the customer portal");

        return $user;
    }

    public function revoke(User $user, ?User $by = null): void
    {
        $user->forceFill(['is_active' => false])->save();
        activity()->performedOn($user)->causedBy($by)->log('Customer portal access removed');
    }
}
