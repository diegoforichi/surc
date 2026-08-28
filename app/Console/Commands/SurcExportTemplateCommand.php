<?php

namespace App\Console\Commands;

use App\Actions\Templates\ExportIndustryTemplate;
use App\Console\Commands\Concerns\ResolvesSurcContext;
use App\Domain\Templates\IndustryTemplateRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class SurcExportTemplateCommand extends Command
{
    use ResolvesSurcContext;

    protected $signature = 'surc:export-template
        {network : ID o slug de la red}
        {--key= : Clave del pack (slug)}
        {--name= : Nombre visible}
        {--output= : Ruta del JSON}
        {--force : Sobrescribir si el archivo existe}';

    protected $description = 'Exporta la configuración de una red como pack JSON de industria';

    public function handle(ExportIndustryTemplate $export, IndustryTemplateRegistry $registry): int
    {
        $network = $this->resolveNetwork((string) $this->argument('network'), askWhenMissing: false);
        $key = (string) ($this->option('key') ?: $network->industry_template_key ?: Str::slug($network->name));
        $name = (string) ($this->option('name') ?: $network->name);
        $output = (string) ($this->option('output') ?: $registry->path().DIRECTORY_SEPARATOR.$key.'.json');

        if (File::exists($output) && ! $this->option('force')) {
            $this->error("Ya existe el archivo '{$output}'. Usar --force para sobrescribir.");

            return self::FAILURE;
        }

        $pack = $export->handle($network, $key, $name);
        $json = json_encode($pack->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        File::ensureDirectoryExists(dirname($output));
        File::put($output, $json.PHP_EOL);
        $registry->flush();

        $this->info("Pack exportado: {$pack->name} ({$pack->key})");
        $this->line($output);

        return self::SUCCESS;
    }
}
