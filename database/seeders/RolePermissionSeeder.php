<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'networks.manage',
            'organizations.manage',
            'users.manage',
            'config.manage',
            'cases.manage',
            'cases.operate',
            'payments.confirm',
            'imports.run',
            'public.manage',
            'history.view',
            'history.manage',
            'history.finalize',
            'history.share',
            'history.print',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        Role::firstOrCreate(['name' => 'platform_owner'])
            ->syncPermissions([
                'networks.manage',
                'organizations.manage',
                'users.manage',
                'config.manage',
                'cases.manage',
                'cases.operate',
                'payments.confirm',
                'imports.run',
                'public.manage',
            ]);

        Role::firstOrCreate(['name' => 'network_admin'])
            ->syncPermissions([
                'organizations.manage',
                'users.manage',
                'config.manage',
                'cases.manage',
                'cases.operate',
                'payments.confirm',
                'imports.run',
                'public.manage',
            ]);

        Role::firstOrCreate(['name' => 'organization_admin'])
            ->syncPermissions([
                'users.manage',
                'cases.manage',
                'cases.operate',
                'payments.confirm',
                'imports.run',
                'history.view',
                'history.manage',
                'history.finalize',
                'history.share',
                'history.print',
            ]);

        Role::firstOrCreate(['name' => 'operator'])
            ->syncPermissions([
                'cases.operate',
                'payments.confirm',
                'history.view',
                'history.manage',
                'history.finalize',
                'history.share',
                'history.print',
            ]);

        Role::firstOrCreate(['name' => 'specialist'])
            ->syncPermissions(['cases.operate']);
    }
}
