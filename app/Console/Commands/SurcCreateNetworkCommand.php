<?php

namespace App\Console\Commands;

use App\Actions\Templates\ApplyIndustryTemplate;
use App\Domain\Templates\IndustryTemplateRegistry;
use App\Models\Network;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class SurcCreateNetworkCommand extends Command
{
    protected $signature = 'surc:create-network
        {name? : Nombre de la red}
        {slug? : Slug único}
        {template? : Clave del pack de industria}
        {--color= : Color primario HEX}
        {--inactive : Crea la red desactivada}';

    protected $description = 'Crea una red y aplica plantilla de industria';

    public function handle(ApplyIndustryTemplate $applyTemplate, IndustryTemplateRegistry $registry): int
    {
        $options = $registry->options();

        if ($options === []) {
            $this->error('No hay packs de industria disponibles.');

            return self::FAILURE;
        }

        $name = (string) ($this->argument('name') ?: $this->ask('Nombre de la red'));
        $slug = (string) ($this->argument('slug') ?: Str::slug($name));
        $defaultTemplate = array_key_exists('veterinary', $options) ? 'veterinary' : array_key_first($options);
        $template = (string) ($this->argument('template') ?: $this->choice(
            'Plantilla de industria',
            $options,
            $defaultTemplate,
        ));
        $primaryColor = (string) ($this->option('color') ?: '#0d9488');

        if (! $registry->has($template)) {
            $this->error("Plantilla inexistente: {$template}. Disponibles: ".implode(', ', array_keys($options)));

            return self::FAILURE;
        }

        if (Network::query()->where('slug', $slug)->exists()) {
            $this->error("Ya existe una red con slug '{$slug}'.");

            return self::FAILURE;
        }

        $network = Network::create([
            'name' => $name,
            'slug' => $slug,
            'industry_template_key' => $template,
            'primary_color' => $primaryColor,
            'is_active' => ! (bool) $this->option('inactive'),
        ]);

        $applyTemplate->handle($network, $template);

        $this->info("Red creada: {$network->name} ({$network->slug})");
        $this->line("Plantilla aplicada: {$registry->find($template)->name} ({$template})");

        return self::SUCCESS;
    }
}
