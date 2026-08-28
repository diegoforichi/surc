<?php

namespace App\Actions\Networks;

use App\Actions\Templates\ApplyIndustryTemplate;
use App\Domain\Templates\IndustryTemplateRegistry;
use App\Models\Network;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class CreateNetworkInstance
{
    /**
     * @param  array{
     *     name: string,
     *     slug?: string|null,
     *     template: string,
     *     color?: string|null,
     *     admin_name: string,
     *     admin_email: string,
     *     admin_password: string,
     *     organization?: string|null,
     *     inactive?: bool
     * }  $input
     * @return array{network: Network, admin: User, organization: ?Organization}
     */
    public function handle(array $input): array
    {
        $registry = app(IndustryTemplateRegistry::class);
        $name = trim((string) $input['name']);
        $slug = trim((string) ($input['slug'] ?? '')) ?: Str::slug($name);
        $template = (string) $input['template'];
        $adminName = trim((string) $input['admin_name']);
        $adminEmail = trim((string) $input['admin_email']);
        $password = (string) $input['admin_password'];
        $organizationName = trim((string) ($input['organization'] ?? ''));

        if ($name === '') {
            throw ValidationException::withMessages(['name' => 'El nombre de la red es obligatorio.']);
        }

        if (! $registry->has($template)) {
            throw ValidationException::withMessages([
                'template' => 'Plantilla inexistente: '.$template,
            ]);
        }

        if ($adminName === '' || $adminEmail === '') {
            throw ValidationException::withMessages([
                'admin_email' => 'Nombre y email del admin de red son obligatorios.',
            ]);
        }

        if ($password === '') {
            throw ValidationException::withMessages([
                'admin_password' => 'La contraseña del admin de red es obligatoria.',
            ]);
        }

        if (Network::query()->where('slug', $slug)->exists()) {
            throw ValidationException::withMessages([
                'slug' => "Ya existe una red con slug '{$slug}'.",
            ]);
        }

        if (User::query()->where('email', $adminEmail)->exists()) {
            throw ValidationException::withMessages([
                'admin_email' => "Ya existe un usuario con email '{$adminEmail}'.",
            ]);
        }

        if (Role::query()->where('name', 'network_admin')->doesntExist()) {
            throw ValidationException::withMessages([
                'role' => 'Rol network_admin inexistente. Ejecutar primero el seeder de roles.',
            ]);
        }

        return DB::transaction(function () use ($input, $name, $slug, $template, $adminName, $adminEmail, $password, $organizationName) {
            $network = Network::create([
                'name' => $name,
                'slug' => $slug,
                'industry_template_key' => $template,
                'primary_color' => $input['color'] ?? '#0d9488',
                'is_active' => ! (bool) ($input['inactive'] ?? false),
            ]);

            app(ApplyIndustryTemplate::class)->handle($network, $template);

            $admin = new User;
            $admin->forceFill([
                'network_id' => $network->id,
                'name' => $adminName,
                'email' => $adminEmail,
                'password' => $password,
                'is_platform_owner' => false,
                'is_active' => true,
            ])->save();
            $admin->assignRole('network_admin');

            $organization = null;
            if ($organizationName !== '') {
                $organization = Organization::create([
                    'network_id' => $network->id,
                    'name' => $organizationName,
                    'slug' => Str::slug($organizationName),
                    'is_active' => true,
                    'show_in_directory' => true,
                ]);
            }

            return compact('network', 'admin', 'organization');
        });
    }
}
