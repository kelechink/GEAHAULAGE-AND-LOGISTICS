<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Permission names checked by policies. Later phases attach these to their resources.
 */
enum Permission: string
{
    // Platform
    case DashboardView = 'dashboard.view';
    case UsersManage = 'users.manage';
    case SettingsManage = 'settings.manage';
    case IntegrationsManage = 'integrations.manage';
    case AuditView = 'audit.view';
    case ContentManage = 'content.manage';
    case PrivacyManage = 'privacy.manage';

    // CRM (Phase 2)
    case CrmView = 'crm.view';
    case CrmManage = 'crm.manage';
    case QuotesManage = 'quotes.manage';

    // Operations and tracking (Phase 3)
    case ShipmentsView = 'shipments.view';
    case ShipmentsManage = 'shipments.manage';
    case TrackingUpdate = 'tracking.update';
    case FleetView = 'fleet.view';
    case FleetManage = 'fleet.manage';
    case DriversManage = 'drivers.manage';

    // Finance (Phase 5)
    case FinanceView = 'finance.view';
    case FinanceManage = 'finance.manage';
    case ReportsView = 'reports.view';
    case InvoicesApproveVoid = 'invoices.approve_void';
    case DiscountsApprove = 'discounts.approve';

    // AI (Phase 6)
    case AiInbox = 'ai.inbox';
    case AiManage = 'ai.manage';

    // Driver app and customer portal
    case DriverApp = 'driver.app';
    case PortalAccess = 'portal.access';
}
