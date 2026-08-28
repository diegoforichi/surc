<?php

namespace App\Console\Commands;

use App\Actions\Templates\ApplyIndustryTemplate;
use App\Domain\Templates\IndustryTemplateRegistry;
use App\Models\Network;
use Illuminate\Console\Command;

class ApplyTemplateToNetwork extends Command
{
    protected $signature = 'surc:apply-template {network : ID o slug de la red} {template? : Clave del pack de industria}';

    protected $description = 'Aplica una plantilla de industria a una red';

    public function handle(ApplyIndustryTemplate $action, IndustryTemplateRegistry $registry): int
    {
        $network = Network::query()
            ->where('id', $this->argument('network'))
            ->orWhere('slug', $this->argument('network'))
            ->firstOrFail();

        $template = (string) ($this->argument('template') ?? $network->industry_template_key ?? '');

        if ($template === '' || ! $registry->has($template)) {
            $available = implode(', ', array_keys($registry->options()));
            $this->error("Plantilla inexistente: {$template}. Disponibles: {$available}");

            return self::FAILURE;
        }

        $action->handle($network, $template);

        $this->info("Plantilla '{$registry->find($template)->name}' aplicada a la red '{$network->name}'.");

        return self::SUCCESS;
    }
}
