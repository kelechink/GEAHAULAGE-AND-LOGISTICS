<?php

declare(strict_types=1);

use App\Enums\Permission;
use App\Enums\Role;
use Database\Seeders\RolesAndPermissionsSeeder;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\Models\Role as RoleModel;

it('creates every role and permission from the PRD', function () {
    expect(RoleModel::query()->pluck('name')->sort()->values()->all())
        ->toBe(collect(Role::cases())->pluck('value')->sort()->values()->all())
        ->and(PermissionModel::query()->count())->toBe(count(Permission::cases()));
});

it('can be run again without duplicating anything', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    expect(RoleModel::query()->count())->toBe(count(Role::cases()))
        ->and(PermissionModel::query()->count())->toBe(count(Permission::cases()));
});

it('gives each role only its own permissions', function () {
    $finance = $this->userWithRole(Role::Finance);
    $ops = $this->userWithRole(Role::Operations);
    $management = $this->userWithRole(Role::Management);

    expect($finance->can(Permission::FinanceManage->value))->toBeTrue()
        ->and($finance->can(Permission::ShipmentsView->value))->toBeTrue()
        ->and($finance->can(Permission::ShipmentsManage->value))->toBeFalse()
        ->and($ops->can(Permission::TrackingUpdate->value))->toBeTrue()
        ->and($ops->can(Permission::FinanceView->value))->toBeFalse()
        ->and($management->can(Permission::InvoicesApproveVoid->value))->toBeTrue()
        ->and($management->can(Permission::FinanceManage->value))->toBeFalse();
});

it('lets the super admin pass every check', function () {
    $admin = $this->userWithRole(Role::SuperAdmin);

    foreach (Permission::cases() as $permission) {
        expect($admin->can($permission->value))->toBeTrue();
    }
});
