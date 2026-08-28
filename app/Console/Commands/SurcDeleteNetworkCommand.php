<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ResolvesSurcContext;
use App\Models\Network;
use Illuminate\Console\Command;

class SurcDeleteNetworkCommand extends Command
{
    use ResolvesSurcContext;

    protected $signature = 'surc:delete-network
        {network? : ID o slug de red}
        {--force : Ejecuta sin confirmación interactiva}';

    protected $description = 'Elimina una red y todos sus datos relacionados';

    public function handle(): int
    {
        /** @var Network $network */
        $network = $this->resolveNetwork($this->argument('network'));

        $this->warn("Se eliminará la red '{$network->name}' ({$network->slug}) y todos sus datos.");

        if (! $this->option('force') && ! $this->confirm('¿Desea continuar?', false)) {
            $this->info('Operación cancelada.');

            return self::SUCCESS;
        }

        $network->delete();

        $this->info('Red eliminada correctamente.');

        return self::SUCCESS;
    }
}
