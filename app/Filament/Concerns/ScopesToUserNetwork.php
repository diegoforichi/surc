<?php

namespace App\Filament\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

trait ScopesToUserNetwork
{
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if ($user?->is_platform_owner) {
            return $query;
        }

        if ($user?->network_id) {
            $query->where('network_id', $user->network_id);

            $fixedOrganizationId = $user->fixedOrganizationId();

            if ($fixedOrganizationId !== null && static::scopesToOrganization()) {
                $table = $query->getModel()->getTable();

                if ($table === 'organizations') {
                    $query->where('id', $fixedOrganizationId);
                } elseif (Schema::hasColumn($table, 'organization_id')) {
                    $query->where('organization_id', $fixedOrganizationId);
                }
            }

            return $query;
        }

        return $query->whereRaw('1 = 0');
    }

    protected static function scopesToOrganization(): bool
    {
        return true;
    }
}
