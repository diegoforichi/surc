<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ResolvesSurcContext;
use App\Models\Network;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class SurcCreateUserCommand extends Command
{
    use ResolvesSurcContext;

    protected $signature = 'surc:create-user
        {email? : Email del usuario}
        {--network= : ID o slug de red}
        {--role= : platform_owner|network_admin|organization_admin|operator|specialist}
        {--org= : ID o slug de sede}
        {--name= : Nombre completo}
        {--password= : Contraseña}
        {--owner : Crear como dueño de plataforma}';

    protected $description = 'Crea un usuario con rol y alcance operativo';

    public function handle(): int
    {
        $email = (string) ($this->argument('email') ?: $this->ask('Email del usuario'));

        if (User::query()->where('email', $email)->exists()) {
            $this->error("Ya existe un usuario con email '{$email}'.");

            return self::FAILURE;
        }

        $isOwner = (bool) $this->option('owner');
        $roleName = $isOwner
            ? 'platform_owner'
            : (string) ($this->option('role') ?: $this->choice(
                'Rol del usuario',
                ['network_admin', 'organization_admin', 'operator', 'specialist'],
                'network_admin'
            ));

        $role = Role::query()->where('name', $roleName)->first();
        if ($role === null) {
            $this->error("Rol inexistente: {$roleName}. Ejecutar primero el seeder de roles.");

            return self::FAILURE;
        }

        $network = null;
        if (! $isOwner) {
            $network = $this->resolveNetwork($this->option('network'));
        }

        $organization = null;
        if (! $isOwner && in_array($roleName, ['organization_admin', 'operator'], true)) {
            $orgIdentifier = $this->option('org');

            if ($orgIdentifier === null) {
                $organizations = Organization::query()
                    ->where('network_id', $network?->id)
                    ->orderBy('name')
                    ->get();

                if ($organizations->isNotEmpty()) {
                    $options = $organizations
                        ->mapWithKeys(fn (Organization $org) => [$org->id => "{$org->name} ({$org->slug})"])
                        ->all();
                    $selected = $this->choice('Seleccione sede', $options, array_key_first($options));
                    $orgIdentifier = (string) $selected;
                }
            }

            $organization = $this->resolveOrganizationForNetwork($network, $orgIdentifier);
        }

        $name = (string) ($this->option('name') ?: Str::headline(Str::before($email, '@')));
        $password = (string) ($this->option('password') ?: '');

        if ($password === '') {
            if ($this->option('no-interaction')) {
                $this->error('La contraseña es obligatoria.');

                return self::FAILURE;
            }

            $password = (string) ($this->secret('Contraseña') ?: '');
        }

        if ($password === '') {
            $this->error('La contraseña es obligatoria.');

            return self::FAILURE;
        }

        $user = new User;
        $user->forceFill([
            'network_id' => $network?->id,
            'organization_id' => $organization?->id,
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'is_platform_owner' => $isOwner,
            'is_active' => true,
        ])->save();

        $user->assignRole($roleName);

        $this->info("Usuario creado: {$user->name} ({$user->email})");
        $this->line("Rol: {$roleName}");
        if ($network instanceof Network) {
            $this->line("Red: {$network->name}");
        }
        if ($organization instanceof Organization) {
            $this->line("Sede: {$organization->name}");
        }

        return self::SUCCESS;
    }

    protected function resolveOrganizationForNetwork(?Network $network, ?string $identifier): ?Organization
    {
        if (! $network instanceof Network) {
            return null;
        }

        if ($identifier === null || $identifier === '') {
            return null;
        }

        return Organization::query()
            ->where('network_id', $network->id)
            ->where(function ($query) use ($identifier): void {
                $query->where('id', $identifier)
                    ->orWhere('slug', $identifier);
            })
            ->firstOrFail();
    }
}
