<?php

namespace App\Console\Commands;

use Database\Seeders\RolePermissionSeeder;
use Illuminate\Console\Command;

class SurcSyncRolesCommand extends Command
{
    protected $signature = 'surc:sync-roles';

    protected $description = 'Sincroniza roles y permisos sin borrar datos operativos';

    public function handle(): int
    {
        $this->call('db:seed', [
            '--class' => RolePermissionSeeder::class,
            '--force' => true,
            '--no-interaction' => true,
        ]);

        $this->info('Roles y permisos sincronizados.');

        return self::SUCCESS;
    }
}
