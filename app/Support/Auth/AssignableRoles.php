<?php

namespace App\Support\Auth;

use App\Models\User;

class AssignableRoles
{
    /**
     * @return array<int, string>
     */
    public static function names(?User $user): array
    {
        if ($user === null) {
            return [];
        }

        if ($user->is_platform_owner || $user->hasRole('platform_owner')) {
            return [
                'platform_owner',
                'network_admin',
                'organization_admin',
                'operator',
                'specialist',
            ];
        }

        if ($user->hasRole('network_admin')) {
            return [
                'organization_admin',
                'operator',
                'specialist',
            ];
        }

        if ($user->hasRole('organization_admin')) {
            return [
                'operator',
                'specialist',
            ];
        }

        return [];
    }

    /**
     * @param  array<int, string>  $roleNames
     * @return array<int, string>
     */
    public static function filter(array $roleNames, ?User $user): array
    {
        $allowed = self::names($user);

        return array_values(array_intersect($roleNames, $allowed));
    }
}
