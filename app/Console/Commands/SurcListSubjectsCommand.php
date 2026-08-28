<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ResolvesSurcContext;
use App\Models\Subject;
use Illuminate\Console\Command;

class SurcListSubjectsCommand extends Command
{
    use ResolvesSurcContext;

    protected $signature = 'surc:list-subjects {network? : ID o slug opcional de la red}';

    protected $description = 'Lista sujetos con código, propietario, sede y estado';

    public function handle(): int
    {
        $networkFilter = $this->argument('network');
        $network = $networkFilter ? $this->resolveNetwork((string) $networkFilter, askWhenMissing: false) : null;

        $subjects = Subject::query()
            ->with(['network', 'owner', 'organization'])
            ->when($network, fn ($query) => $query->where('network_id', $network->id))
            ->orderBy('network_id')
            ->orderBy('label_name')
            ->get();

        if ($subjects->isEmpty()) {
            $this->warn('No hay sujetos cargados para el criterio indicado.');

            return self::SUCCESS;
        }

        $this->table(
            ['Red', 'Código', 'Nombre', 'Propietario', 'Sede', 'Activo'],
            $subjects->map(fn (Subject $subject) => [
                $subject->network?->slug ?? '-',
                $subject->code ?? '-',
                $subject->label_name,
                $subject->owner?->display_name ?? '-',
                $subject->organization?->name ?? '-',
                $subject->is_active ? 'Sí' : 'No',
            ])->all()
        );

        return self::SUCCESS;
    }
}
