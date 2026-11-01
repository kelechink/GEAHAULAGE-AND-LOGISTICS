<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\User;

it('creates a super admin from the command line', function () {
    $this->artisan('gea:make-admin', ['name' => 'FigureWeb Admin', 'email' => 'admin@figureweb.ng'])
        ->expectsQuestion('Password (min 12 characters)', 'a-long-secret-password')
        ->assertSuccessful();

    expect(User::query()->where('email', 'admin@figureweb.ng')->first()?->hasRole(Role::SuperAdmin->value))->toBeTrue();
});

it('refuses a short password', function () {
    $this->artisan('gea:make-admin', ['name' => 'X', 'email' => 'x@figureweb.ng'])
        ->expectsQuestion('Password (min 12 characters)', 'short')
        ->assertFailed();

    expect(User::query()->where('email', 'x@figureweb.ng')->exists())->toBeFalse();
});

it('refuses an unknown role', function () {
    $this->artisan('gea:make-admin', ['name' => 'X', 'email' => 'x@figureweb.ng', '--role' => 'boss'])
        ->assertFailed();
});
