<?php

namespace App\Support\Auth;

class RoleLabels
{
    public static function label(string $name): string
    {
        return match ($name) {
            'platform_owner' => 'Dueño de plataforma',
            'network_admin' => 'Admin de red',
            'organization_admin' => 'Admin de sede',
            'operator' => 'Operador',
            'specialist' => 'Especialista',
            default => $name,
        };
    }

    /**
     * @param  array<int, string>  $names
     * @return array<string, string>
     */
    public static function options(array $names): array
    {
        return collect($names)
            ->mapWithKeys(fn (string $name): array => [$name => self::label($name)])
            ->all();
    }
}
