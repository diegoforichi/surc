<?php

namespace App\Filament\Resources\PublicContentResource\Pages;

use App\Filament\Resources\PublicContentResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPublicContent extends EditRecord
{
    protected static string $resource = PublicContentResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return PublicContentResource::assignPublishedAt($data);
    }
}
