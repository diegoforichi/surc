<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ResolvesSurcContext;
use App\Models\Party;
use Illuminate\Console\Command;

class SurcListActorsCommand extends Command
{
    use ResolvesSurcContext;

    protected $signature = 'surc:list-actors {network? : ID o slug opcional de la red}';

    protected $description = 'Lista actores con tipo, sede, usuario vinculado y cantidad de sujetos asignados';

    public function handle(): int
    {
        $networkFilter = $this->argument('network');
        $network = $networkFilter ? $this->resolveNetwork((string) $networkFilter, askWhenMissing: false) : null;

        $actors = Party::query()
            ->with(['network', 'actorType', 'organization', 'user'])
            ->withCount('ownedSubjects')
            ->when($network, fn ($query) => $query->where('network_id', $network->id))
            ->orderBy('network_id')
            ->orderBy('display_name')
            ->get();

        if ($actors->isEmpty()) {
            $this->warn('No hay actores cargados para el criterio indicado.');

            return self::SUCCESS;
        }

        $this->table(
            ['Red', 'Tipo', 'Nombre', 'Sede', 'Usuario', 'Sujetos'],
            $actors->map(fn (Party $party) => [
                $party->network?->slug ?? '-',
                $party->actorType?->label ?? '-',
                $party->display_name,
                $party->organization?->name ?? '-',
                $party->user?->email ?? '-',
                (string) $party->owned_subjects_count,
            ])->all()
        );

        return self::SUCCESS;
    }
}
