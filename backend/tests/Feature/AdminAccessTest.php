<?php

declare(strict_types=1);

use App\Enums\Role;

it('sends guests to the login page', function () {
    $this->get('/admin')->assertRedirect('/admin/login');
});

it('lets back-office roles into the admin', function (Role $role) {
    $this->actingAs($this->userWithRole($role))
        ->get('/admin')
        ->assertOk();
})->with([
    'super admin' => Role::SuperAdmin,
    'management' => Role::Management,
    'operations' => Role::Operations,
    'sales' => Role::Sales,
    'finance' => Role::Finance,
]);

it('keeps drivers and customers out of the admin', function (Role $role) {
    $this->actingAs($this->userWithRole($role))
        ->get('/admin')
        ->assertForbidden();
})->with([
    'driver' => Role::Driver,
    'customer' => Role::Customer,
]);

it('keeps deactivated staff out', function () {
    $this->actingAs($this->userWithRole(Role::Operations, ['is_active' => false]))
        ->get('/admin')
        ->assertForbidden();
});

it('only lets users with users.manage open user management', function () {
    $this->actingAs($this->userWithRole(Role::SuperAdmin))->get('/admin/users')->assertOk();
    $this->actingAs($this->userWithRole(Role::Management))->get('/admin/users')->assertForbidden();
    $this->actingAs($this->userWithRole(Role::Operations))->get('/admin/users')->assertForbidden();
});

it('shows the audit log to management but not to sales', function () {
    $this->actingAs($this->userWithRole(Role::Management))->get('/admin/activities')->assertOk();
    $this->actingAs($this->userWithRole(Role::Sales))->get('/admin/activities')->assertForbidden();
});

it('only lets the super admin change company settings', function () {
    $this->actingAs($this->userWithRole(Role::SuperAdmin))->get('/admin/manage-company-settings')->assertOk();
    $this->actingAs($this->userWithRole(Role::Finance))->get('/admin/manage-company-settings')->assertForbidden();
});
