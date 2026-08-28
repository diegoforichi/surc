<?php

namespace App\Filament\Resources\ActorTypeResource\Pages;

use App\Filament\Resources\ActorTypeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateActorType extends CreateRecord
{
    protected static string $resource = ActorTypeResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return ActorTypeResource::assignNetworkId($data);
    }
}
