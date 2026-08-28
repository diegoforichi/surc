<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ResolvesSurcContext;
use App\Models\Network;
use App\Models\WorkflowTemplate;
use Illuminate\Console\Command;

class SurcListWorkflowsCommand extends Command
{
    use ResolvesSurcContext;

    protected $signature = 'surc:list-workflows {network? : ID o slug opcional de la red}';

    protected $description = 'Lista plantillas de flujo con etapas y requisitos';

    public function handle(): int
    {
        $networkFilter = $this->argument('network');

        if ($networkFilter) {
            $networks = collect([$this->resolveNetwork((string) $networkFilter, askWhenMissing: false)]);
        } else {
            $networks = Network::query()->orderBy('name')->get();
        }

        if ($networks->isEmpty()) {
            $this->warn('No hay redes disponibles.');

            return self::SUCCESS;
        }

        foreach ($networks as $network) {
            $templates = WorkflowTemplate::query()
                ->where('network_id', $network->id)
                ->with(['stages' => fn ($stageQuery) => $stageQuery
                    ->orderBy('sort_order')
                    ->with(['requirements' => fn ($reqQuery) => $reqQuery->orderBy('sort_order')])])
                ->orderBy('name')
                ->get();

            $this->newLine();
            $this->info("Red: {$network->name} ({$network->slug})");

            if ($templates->isEmpty()) {
                $this->line('  Sin plantillas de flujo.');
                continue;
            }

            foreach ($templates as $template) {
                $this->line(sprintf(
                    '  - [%d] %s | default: %s | activa: %s',
                    $template->id,
                    $template->name,
                    $template->is_default ? 'Sí' : 'No',
                    $template->is_active ? 'Sí' : 'No',
                ));

                foreach ($template->stages as $stage) {
                    $terminal = $stage->is_terminal ? ' [terminal]' : '';
                    $this->line("      {$stage->sort_order}. {$stage->label} ({$stage->key}){$terminal}");

                    foreach ($stage->requirements as $requirement) {
                        $this->line(sprintf(
                            '         - %s (%s) | tipo: %s | obligatorio: %s',
                            $requirement->label,
                            $requirement->key,
                            $requirement->type?->value ?? '-',
                            $requirement->is_mandatory ? 'Sí' : 'No'
                        ));
                    }
                }
            }
        }

        return self::SUCCESS;
    }
}
