<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PermissionSeeder extends Seeder
{
    /**
     * Permission catalog grouped by module.
     * Convention: "{module}.{action}" — used everywhere (FE gating, BE middleware).
     */
    public static function catalog(): array
    {
        return [
            'projects'       => ['view', 'create', 'edit', 'delete'],
            'project-types'  => ['view', 'create', 'edit', 'delete'],
            'donors'         => ['view', 'create', 'edit', 'delete'],
            'donor-sources'  => ['view', 'create', 'edit', 'delete'],
            'students'       => ['view', 'create', 'edit', 'delete'],
            'donations'      => ['view', 'create', 'edit', 'delete', 'collect'],
            'campaigns'      => ['view', 'create', 'edit', 'delete'],
            'expenses'       => ['view', 'create', 'edit', 'delete'],
            'notifications'  => ['view', 'send', 'manage_templates'],
            'emails'           => ['view', 'create', 'edit', 'delete'],
            'email-templates'  => ['view', 'create', 'edit', 'delete'],
            'sms'              => ['view', 'create', 'edit', 'delete', 'send'],
            'sms-templates'    => ['view', 'create', 'edit', 'delete'],
            'whatsapp'           => ['view', 'create', 'edit', 'delete', 'send'],
            'whatsapp-templates' => ['view', 'create', 'edit', 'delete'],
            'reports'        => ['view', 'export'],
            'reconciliation' => ['view', 'upload', 'match'],
            'users'          => ['view', 'create', 'edit', 'delete'],
            'recycle-bin'    => ['view', 'restore', 'force_delete'],
            'backup'         => ['create'],
            'receipts'       => ['download'],
            'uploads'        => ['picture', 'video', 'raw_material'],
            'audit-logs'     => ['view'],
        ];
    }

    public function run(): void
    {
        foreach (self::catalog() as $module => $actions) {
            foreach ($actions as $action) {
                $name = "{$module}.{$action}";
                Permission::updateOrCreate(
                    ['name' => $name],
                    [
                        'module' => $module,
                        'label'  => Str::headline(str_replace(['-', '_'], ' ', "{$module} {$action}")),
                    ]
                );
            }
        }
    }
}
