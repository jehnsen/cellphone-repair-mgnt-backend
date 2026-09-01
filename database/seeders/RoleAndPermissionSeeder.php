<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleAndPermissionSeeder extends Seeder
{
    /**
     * Action-level permissions per docs/design/01-domain-design.md §2.1 —
     * cost/margin fields and destructive actions are permission-gated in
     * the API resource/policy layer, not merely hidden by the client.
     */
    private const PERMISSIONS = [
        'tickets.view', 'tickets.create', 'tickets.update', 'tickets.release',
        'tickets.imei_override',
        'inventory.view', 'inventory.adjust', 'inventory.receive',
        'suppliers.manage',
        'sales.create', 'sales.void', 'sales.refund', 'sales.discount.override',
        'shifts.open', 'shifts.close',
        'customers.view', 'customers.manage', 'store_credit.manage',
        'catalog.view', 'catalog.manage',
        // branches.view_all is what lifts BranchScope — the holder can ask
        // for ?branch=all / ?branch={ulid} and see other branches' sales,
        // repairs, and inventory. Owner only; see BranchScope.
        'branches.view', 'branches.view_all', 'branches.manage',
        'users.view', 'users.manage',
        'reports.view', 'reports.margin.view',
        'settings.manage',
        'acquisitions.manage',
    ];

    private const ROLE_PERMISSIONS = [
        'owner' => self::PERMISSIONS,
        'manager' => [
            'tickets.view', 'tickets.create', 'tickets.update', 'tickets.release', 'tickets.imei_override',
            'inventory.view', 'inventory.adjust', 'inventory.receive',
            'suppliers.manage',
            'sales.create', 'sales.void', 'sales.refund', 'sales.discount.override',
            'shifts.open', 'shifts.close',
            'customers.view', 'customers.manage', 'store_credit.manage',
            'catalog.view', 'catalog.manage',
            'branches.view',
            'users.view',
            'reports.view', 'reports.margin.view',
            'acquisitions.manage',
        ],
        // A cashier runs the front counter end to end: POS, taking in a job
        // order, moving it across the status board, entering findings, and
        // releasing the device once it's paid. What they never get is
        // cross-branch sight (no branches.view_all) or cost/margin
        // (no reports.margin.view) — see BranchScope and ReportController.
        'cashier' => [
            'tickets.view', 'tickets.create', 'tickets.update', 'tickets.release',
            'inventory.view',
            'sales.create', 'sales.refund',
            'shifts.open', 'shifts.close',
            'customers.view', 'customers.manage',
            'catalog.view',
            'branches.view',
            'reports.view',
        ],
        'technician' => [
            'tickets.view', 'tickets.update',
            'inventory.view',
            'customers.view',
            'catalog.view',
            'branches.view',
        ],
    ];

    public function run(): void
    {
        foreach (self::PERMISSIONS as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        foreach (self::ROLE_PERMISSIONS as $role => $permissions) {
            Role::findOrCreate($role, 'web')->syncPermissions($permissions);
        }
    }
}
