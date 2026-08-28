<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return UserResource::assignNetworkAndOrganization($data);
    }

    protected function afterCreate(): void
    {
        $this->record->syncRoles(
            \App\Support\Auth\AssignableRoles::filter(
                $this->record->getRoleNames()->all(),
                auth()->user(),
            ),
        );
    }
}
