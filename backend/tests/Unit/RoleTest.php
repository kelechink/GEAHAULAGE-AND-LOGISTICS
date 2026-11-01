<?php

declare(strict_types=1);

use App\Enums\Permission;
use App\Enums\Role;

it('only lets back-office roles use the admin', function () {
    $adminRoles = array_values(array_filter(Role::cases(), static fn (Role $r): bool => $r->canUseAdmin()));

    expect($adminRoles)->toBe([Role::SuperAdmin, Role::Management, Role::Operations, Role::Sales, Role::Finance]);
});

it('gives the super admin every permission', function () {
    expect(Role::SuperAdmin->permissions())->toBe(Permission::cases());
});

it('keeps finance editing away from management, which only approves', function () {
    expect(Role::Management->permissions())
        ->toContain(Permission::FinanceView, Permission::InvoicesApproveVoid)
        ->not->toContain(Permission::FinanceManage);
});

it('limits drivers to the app and customers to the portal', function () {
    expect(Role::Driver->permissions())->toBe([Permission::DriverApp])
        ->and(Role::Customer->permissions())->toBe([Permission::PortalAccess]);
});

it('has a readable label for every role', function () {
    expect(Role::options())->toHaveCount(count(Role::cases()))
        ->and(Role::Sales->label())->toBe('Sales / Customer Service');
});
