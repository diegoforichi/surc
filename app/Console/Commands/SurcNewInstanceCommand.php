<?php

namespace App\Console\Commands;

use App\Actions\Networks\CreateNetworkInstance;
use App\Domain\Templates\IndustryTemplateRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SurcNewInstanceCommand extends Command
{
    protected $signature = 'surc:new-instance
        {--name= : Nombre de la red}
        {--slug= : Slug único}
        {--template= : Clave del pack de industria}
        {--color= : Color primario HEX}
        {--admin-name= : Nombre del admin de red}
        {--admin-email= : Email del admin de red}
        {--admin-password= : Contraseña del admin de red}
        {--org= : Nombre de la primera sede (opcional)}';

    protected $description = 'Crea una red, aplica un pack de industria y da de alta al admin de red';

    public function handle(CreateNetworkInstance $createInstance, IndustryTemplateRegistry $registry): int
    {
        try {
            return $this->create($createInstance, $registry);
        } catch (ValidationException $exception) {
            $this->error(collect($exception->errors())->flatten()->first() ?: 'No se pudo crear la instancia.');

            return self::FAILURE;
        }
    }

    protected function create(CreateNetworkInstance $createInstance, IndustryTemplateRegistry $registry): int
    {
        $options = $registry->options();

        if ($options === []) {
            $this->error('No hay packs de industria disponibles.');

            return self::FAILURE;
        }

        $name = $this->requiredOption('name', 'Nombre de la red');
        $slug = (string) $this->option('slug');
        if ($slug === '') {
            $slug = $this->option('no-interaction')
                ? Str::slug($name)
                : (string) $this->ask('Slug', Str::slug($name));
        }

        $defaultTemplate = array_key_exists('veterinary', $options) ? 'veterinary' : array_key_first($options);
        $template = (string) $this->option('template');
        if ($template === '') {
            if ($this->option('no-interaction')) {
                $template = (string) $defaultTemplate;
            } else {
                $template = (string) $this->choice(
                    'Plantilla de industria',
                    $options,
                    $defaultTemplate,
                );
            }
        }

        $color = (string) $this->option('color');
        if ($color === '') {
            $color = $this->option('no-interaction')
                ? '#0d9488'
                : (string) $this->ask('Color primario HEX', '#0d9488');
        }
        $adminName = $this->requiredOption('admin-name', 'Nombre del dueño de la red');
        $adminEmail = $this->requiredOption('admin-email', 'Email del dueño de la red');
        $password = $this->resolvePassword();

        if ($password === null) {
            return self::FAILURE;
        }

        $organization = $this->option('org');
        if ($organization === null && ! $this->option('no-interaction')) {
            $organization = $this->ask('Nombre de la primera sede (vacío para omitir)');
        }

        $created = $createInstance->handle([
            'name' => $name,
            'slug' => $slug,
            'template' => $template,
            'color' => $color,
            'admin_name' => $adminName,
            'admin_email' => $adminEmail,
            'admin_password' => $password,
            'organization' => is_string($organization) ? $organization : null,
        ]);

        $network = $created['network'];
        $admin = $created['admin'];
        $org = $created['organization'];

        $this->info("Red creada: {$network->name} ({$network->slug})");
        $this->line("Plantilla: {$registry->find($network->industry_template_key)->name}");
        $this->line("Admin de red: {$admin->name} ({$admin->email})");

        if ($org !== null) {
            $this->line("Sede: {$org->name} ({$org->slug})");
        }

        return self::SUCCESS;
    }

    protected function requiredOption(string $option, string $question): string
    {
        $value = trim((string) ($this->option($option) ?: ''));

        if ($value !== '') {
            return $value;
        }

        if ($this->option('no-interaction')) {
            throw ValidationException::withMessages([$option => "{$question} es obligatorio."]);
        }

        $value = trim((string) ($this->ask($question) ?: ''));

        if ($value === '') {
            throw ValidationException::withMessages([$option => "{$question} es obligatorio."]);
        }

        return $value;
    }

    protected function resolvePassword(): ?string
    {
        $password = (string) $this->option('admin-password');

        if ($password !== '') {
            return $password;
        }

        if ($this->option('no-interaction')) {
            $this->error('La contraseña del admin de red es obligatoria.');

            return null;
        }

        $password = (string) ($this->secret('Contraseña del dueño de la red') ?: '');
        $confirm = (string) ($this->secret('Confirmar contraseña') ?: '');

        if ($password === '' || $password !== $confirm) {
            $this->error('La contraseña es obligatoria y debe coincidir con la confirmación.');

            return null;
        }

        return $password;
    }
}
