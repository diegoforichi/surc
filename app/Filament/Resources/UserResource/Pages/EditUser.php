<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function afterSave(): void
    {
        $this->record->syncRoles(
            \App\Support\Auth\AssignableRoles::filter(
                $this->record->getRoleNames()->all(),
                auth()->user(),
            ),
        );
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return UserResource::assignNetworkAndOrganization($data);
    }

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
