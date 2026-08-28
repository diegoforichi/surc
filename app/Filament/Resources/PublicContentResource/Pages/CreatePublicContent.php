<?php

namespace App\Filament\Resources\PublicContentResource\Pages;

use App\Filament\Resources\PublicContentResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePublicContent extends CreateRecord
{
    protected static string $resource = PublicContentResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data = PublicContentResource::assignNetworkId($data);

        return PublicContentResource::assignPublishedAt($data);
    }
}
