<?php

declare(strict_types=1);

use App\Domain\Auth\StaffInvite;
use App\Enums\Role;
use App\Filament\Resources\UserResource\Pages\CreateUser;
use App\Mail\StaffInviteMail;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Livewire\Livewire;

beforeEach(fn () => Mail::fake());

it('creates a staff account nobody knows the password of and emails a branded set-password link', function () {
    $user = app(StaffInvite::class)->create('Faith Emmanuel', 'E.Faith@Example.test', [Role::Sales], '07030215421');

    expect($user->email)->toBe('e.faith@example.test')->and($user->hasRole('sales'))->toBeTrue()->and($user->phone)->toBe('07030215421');
    Mail::assertQueued(StaffInviteMail::class, function (StaffInviteMail $mail) use ($user): bool {
        $html = $mail->render();

        return $mail->hasTo($user->email) && str_contains($mail->link, '/admin/password-reset/reset')
            && str_contains($html, 'Choose your password') && str_contains($html, 'Sales / Customer Service')
            && str_contains($html, 'logo-blue.png');
    });
});

it('lets the invited person choose a password through the link', function () {
    $user = app(StaffInvite::class)->create('Ikenna Atueyi', 'a.ikenna@example.test', [Role::Operations]);
    $token = Password::broker()->createToken($user);

    expect(Password::broker()->tokenExists($user, $token))->toBeTrue()
        ->and(config('auth.passwords.users.expire'))->toBe(1440);
});

it('refuses driver and customer roles, empty roles and existing emails', function () {
    $invite = app(StaffInvite::class);
    expect(fn () => $invite->create('X', 'x@example.test', [Role::Driver]))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $invite->create('X', 'x@example.test', []))->toThrow(InvalidArgumentException::class);
    $this->userWithRole(Role::Finance, ['email' => 'taken@example.test']);
    expect(fn () => $invite->create('X', 'taken@example.test', [Role::Finance]))->toThrow(InvalidArgumentException::class);
});

it('emails an invitation when an admin creates a user without typing a password', function () {
    Livewire::actingAs($this->userWithRole(Role::SuperAdmin))->test(CreateUser::class)
        ->fillForm(['name' => 'Hairat Hairat', 'email' => 'h.hairat@example.test', 'roles' => [Spatie\Permission\Models\Role::findByName('finance')->id], 'is_active' => true])
        ->call('create')->assertHasNoFormErrors();

    Mail::assertQueued(StaffInviteMail::class, fn (StaffInviteMail $m): bool => $m->hasTo('h.hairat@example.test'));
});

it('sends staff password resets to the back office and customer resets to the portal', function () {
    Notification::fake();
    $staff = $this->userWithRole(Role::Operations, ['email' => 'ops@example.test']);
    Password::broker()->sendResetLink(['email' => 'ops@example.test']);
    Notification::assertSentTo($staff, ResetPassword::class, function (ResetPassword $n) use ($staff): bool {
        return str_contains((string) $n->toMail($staff)->actionUrl, '/admin/password-reset/reset');
    });

    $this->get('/admin/password-reset/request')->assertOk();
});

it('offers the invite command', function () {
    $this->artisan('gea:invite-staff', ['email' => 's.kelechi@example.test', 'name' => 'Samuel Kelechi', '--role' => ['operations']])->assertSuccessful();
    expect(User::query()->where('email', 's.kelechi@example.test')->exists())->toBeTrue();
    Mail::assertQueued(StaffInviteMail::class);
    $this->artisan('gea:invite-staff', ['email' => 's.kelechi@example.test', '--resend' => true])->assertSuccessful();
    Mail::assertQueued(StaffInviteMail::class, 2);
});
