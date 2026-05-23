<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Role -> permission mapping. super_admin gets every permission automatically.
     * Source: project brief ROLES section (admin / accountant / user scopes).
     */
    public static function rolePermissions(): array
    {
        return [
            // Full management of donors / students / projects / donations / notifications.
            'admin' => [
                'projects.view', 'projects.create', 'projects.edit', 'projects.delete',
                'project-types.view', 'project-types.create', 'project-types.edit', 'project-types.delete',
                'donors.view', 'donors.create', 'donors.edit', 'donors.delete',
                'donor-sources.view', 'donor-sources.create', 'donor-sources.edit', 'donor-sources.delete',
                'students.view', 'students.create', 'students.edit', 'students.delete',
                'donations.view', 'donations.create', 'donations.edit', 'donations.delete',
                'campaigns.view', 'campaigns.create', 'campaigns.edit', 'campaigns.delete',
                'expenses.view', 'expenses.create', 'expenses.edit', 'expenses.delete',
                'notifications.view', 'notifications.send', 'notifications.manage_templates',
                'emails.view', 'emails.create', 'emails.edit', 'emails.delete',
                'email-templates.view', 'email-templates.create', 'email-templates.edit', 'email-templates.delete',
                'sms.view', 'sms.create', 'sms.edit', 'sms.delete', 'sms.send',
                'sms-templates.view', 'sms-templates.create', 'sms-templates.edit', 'sms-templates.delete',
                'whatsapp.view', 'whatsapp.create', 'whatsapp.edit', 'whatsapp.delete', 'whatsapp.send',
                'whatsapp-templates.view', 'whatsapp-templates.create', 'whatsapp-templates.edit', 'whatsapp-templates.delete',
                'users.view', 'users.create', 'users.edit',
                'reports.view',
                'audit-logs.view',
                'recycle-bin.view', 'recycle-bin.restore',
                'receipts.download',
            ],

            // Collector: portfolio create, money collect, financial reports & reconciliation.
            'accountant' => [
                'projects.view', 'projects.create',
                'project-types.view',
                'donors.view',
                'donor-sources.view',
                'students.view',
                'donations.view', 'donations.create', 'donations.collect',
                'expenses.view', 'expenses.create', 'expenses.edit',
                'reports.view', 'reports.export',
                'reconciliation.view', 'reconciliation.upload', 'reconciliation.match',
                'receipts.download',
            ],

            // End-user: donor list+create, picture/video upload, raw-material picture upload.
            'user' => [
                'donors.view', 'donors.create',
                'donor-sources.view',
                'uploads.picture', 'uploads.video', 'uploads.raw_material',
            ],
        ];
    }

    public function run(): void
    {
        $roleNames = ['super_admin', 'admin', 'accountant', 'user'];

        foreach ($roleNames as $name) {
            Role::firstOrCreate(['name' => $name]);
        }

        // super_admin gets every permission
        $superAdmin = Role::where('name', 'super_admin')->first();
        $superAdmin->permissions()->sync(Permission::pluck('id')->all());

        foreach (self::rolePermissions() as $roleName => $permissionNames) {
            $role = Role::where('name', $roleName)->first();
            $role->syncPermissionsByName($permissionNames);
        }
    }
}
