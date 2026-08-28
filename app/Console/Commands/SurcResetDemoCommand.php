<?php

namespace App\Console\Commands;

use App\Models\Network;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class SurcResetDemoCommand extends Command
{
    protected $signature = 'surc:reset-demo
        {--fresh : Ejecuta migrate:fresh --seed}
        {--force : Ejecuta sin confirmación interactiva}';

    protected $description = 'Limpia datos demo (o reinicia base completa) para preparar entorno';

    public function handle(): int
    {
        $isFresh = (bool) $this->option('fresh');

        if ($isFresh) {
            if (! $this->option('force') && ! $this->confirm('Esto ejecutará migrate:fresh --seed y borrará toda la base. ¿Continuar?', false)) {
                $this->info('Operación cancelada.');

                return self::SUCCESS;
            }

            $this->warn('Ejecutando migrate:fresh --seed ...');
            Artisan::call('migrate:fresh', ['--seed' => true, '--force' => true]);
            $this->line(Artisan::output());
            $this->info('Base reiniciada y seeded correctamente.');

            return self::SUCCESS;
        }

        $demoNetworks = Network::query()
            ->where(function ($query): void {
                $query->whereIn('slug', ['red-veterinaria', 'red-peluqueria'])
                    ->orWhere('name', 'like', '%Demo%');
            })
            ->orderBy('name')
            ->get();

        if ($demoNetworks->isEmpty()) {
            $this->info('No se encontraron redes demo para eliminar.');

            return self::SUCCESS;
        }

        $this->warn('Se eliminarán las siguientes redes demo:');
        foreach ($demoNetworks as $network) {
            $this->line("- {$network->name} ({$network->slug})");
        }

        if (! $this->option('force') && ! $this->confirm('¿Desea continuar?', false)) {
            $this->info('Operación cancelada.');

            return self::SUCCESS;
        }

        foreach ($demoNetworks as $network) {
            $network->delete();
        }

        $this->info('Redes demo eliminadas.');

        return self::SUCCESS;
    }
}
