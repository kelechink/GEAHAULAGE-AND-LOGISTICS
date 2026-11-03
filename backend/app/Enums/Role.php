<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Platform roles from PRD Section 4.
 */
enum Role: string
{
    case SuperAdmin = 'super_admin';
    case Management = 'management';
    case Operations = 'operations';
    case Sales = 'sales';
    case Finance = 'finance';
    case Driver = 'driver';
    case Customer = 'customer';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::Management => 'Management / MD',
            self::Operations => 'Operations',
            self::Sales => 'Sales / Customer Service',
            self::Finance => 'Finance / Accounts',
            self::Driver => 'Driver',
            self::Customer => 'Customer',
        };
    }

    /**
     * Roles that sign in to the Filament back office at /admin.
     * Drivers use the Android app; customers use the portal.
     */
    public function canUseAdmin(): bool
    {
        return ! in_array($this, [self::Driver, self::Customer], true);
    }

    /**
     * Permissions granted to the role. Super Admin gets everything through a Gate::before check.
     *
     * @return list<Permission>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::SuperAdmin => Permission::cases(),
            self::Management => [
                Permission::DashboardView,
                Permission::CrmView,
                Permission::ShipmentsView,
                Permission::FleetView,
                Permission::FinanceView,
                Permission::ReportsView,
                Permission::InvoicesApproveVoid,
                Permission::DiscountsApprove,
                Permission::AuditView,
                Permission::AiInbox,
                Permission::AiManage,
                Permission::PrivacyManage,
            ],
            self::Operations => [
                Permission::DashboardView,
                Permission::ShipmentsView,
                Permission::ShipmentsManage,
                Permission::TrackingUpdate,
                Permission::FleetView,
                Permission::FleetManage,
                Permission::DriversManage,
            ],
            self::Sales => [
                Permission::DashboardView,
                Permission::CrmView,
                Permission::CrmManage,
                Permission::QuotesManage,
                Permission::AiInbox,
            ],
            self::Finance => [
                Permission::DashboardView,
                Permission::ShipmentsView,
                Permission::FinanceView,
                Permission::FinanceManage,
                Permission::ReportsView,
            ],
            self::Driver => [
                Permission::DriverApp,
            ],
            self::Customer => [
                Permission::PortalAccess,
            ],
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $role) {
            $options[$role->value] = $role->label();
        }

        return $options;
    }
}
