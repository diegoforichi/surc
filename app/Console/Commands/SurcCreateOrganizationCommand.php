<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ResolvesSurcContext;
use App\Models\Organization;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class SurcCreateOrganizationCommand extends Command
{
    use ResolvesSurcContext;

    protected $signature = 'surc:create-org
        {network? : ID o slug de la red}
        {name? : Nombre de la sede}
        {slug? : Slug de la sede}
        {--address= : Dirección}
        {--phone= : Teléfono}
        {--email= : Email}
        {--inactive : Crea la sede desactivada}
        {--hide-directory : No mostrar en directorio público}';

    protected $description = 'Crea una sede/organización para una red';

    public function handle(): int
    {
        $network = $this->resolveNetwork($this->argument('network'));
        $name = (string) ($this->argument('name') ?: $this->ask('Nombre de la sede'));
        $slug = (string) ($this->argument('slug') ?: Str::slug($name));

        $exists = Organization::query()
            ->where('network_id', $network->id)
            ->where('slug', $slug)
            ->exists();

        if ($exists) {
            $this->error("Ya existe una sede con slug '{$slug}' en la red '{$network->slug}'.");

            return self::FAILURE;
        }

        $organization = Organization::create([
            'network_id' => $network->id,
            'name' => $name,
            'slug' => $slug,
            'address' => $this->option('address'),
            'phone' => $this->option('phone'),
            'email' => $this->option('email'),
            'is_active' => ! (bool) $this->option('inactive'),
            'show_in_directory' => ! (bool) $this->option('hide-directory'),
        ]);

        $this->info("Sede creada: {$organization->name} ({$organization->slug}) en {$network->name}");

        return self::SUCCESS;
    }
}
