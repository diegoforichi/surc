<?php

namespace App\Filament\Concerns;

use Filament\Forms;
use Illuminate\Database\Eloquent\Builder;

trait HasNetworkFormFields
{
    public static function networkIdFormFields(): array
    {
        return [
            Forms\Components\Select::make('network_id')
                ->label('Red')
                ->relationship('network', 'name')
                ->visible(fn () => auth()->user()?->is_platform_owner)
                ->required(),
            Forms\Components\Hidden::make('network_id')
                ->default(fn () => auth()->user()?->network_id)
                ->visible(fn () => ! auth()->user()?->is_platform_owner),
        ];
    }

    public static function assignNetworkId(array $data): array
    {
        if (! auth()->user()?->is_platform_owner) {
            $data['network_id'] = auth()->user()?->network_id;
        }

        return $data;
    }

    public static function scopeToUserNetwork($query)
    {
        $user = auth()->user();

        if ($user?->is_platform_owner) {
            return $query;
        }

        if ($user?->network_id) {
            return $query->where('network_id', $user->network_id);
        }

        return $query->whereRaw('1 = 0');
    }

    public static function scopeToUserOrganization(Builder $query, string $column = 'organization_id'): Builder
    {
        $fixedOrganizationId = auth()->user()?->fixedOrganizationId();

        if ($fixedOrganizationId === null) {
            return $query;
        }

        return $query->where($column, $fixedOrganizationId);
    }

    public static function organizationDefault(): ?int
    {
        return auth()->user()?->fixedOrganizationId();
    }

    public static function organizationHelpText(): string
    {
        if (static::organizationDefault() !== null) {
            return 'Queda fija en su '.strtolower((string) terminology('organization', 'sede')).'.';
        }

        return sprintf(
            '%s a la que pertenece este registro.',
            terminology('organization', 'Sede'),
        );
    }

    public static function organizationSelect(): Forms\Components\Select
    {
        $locked = static::organizationDefault() !== null;

        return Forms\Components\Select::make('organization_id')
            ->label(terminology('organization', 'Sede'))
            ->relationship(
                'organization',
                'name',
                function (Builder $query) {
                    return static::scopeOrganizationsForUser($query);
                },
            )
            ->default(fn (): ?int => static::organizationDefault())
            ->helperText(static::organizationHelpText())
            ->searchable()
            ->preload()
            ->disabled($locked)
            ->dehydrated();
    }

    public static function scopeOrganizationsForUser($query)
    {
        $query = static::scopeToUserNetwork($query);
        $fixed = static::organizationDefault();

        if ($fixed !== null) {
            $query->where('id', $fixed);
        }

        return $query;
    }

    public static function relatedRecordsQuery(Builder $query): Builder
    {
        /** @var Builder $query */
        $query = static::scopeToUserNetwork($query);

        return static::scopeToUserOrganization($query);
    }

    public static function assignOrganizationId(array $data): array
    {
        $fixed = static::organizationDefault();

        if ($fixed !== null) {
            $data['organization_id'] = $fixed;
        }

        return $data;
    }

    public static function assignNetworkAndOrganization(array $data): array
    {
        return static::assignOrganizationId(static::assignNetworkId($data));
    }
}
