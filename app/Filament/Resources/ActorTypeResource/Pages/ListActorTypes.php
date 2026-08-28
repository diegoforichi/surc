<?php

namespace App\Filament\Resources\ActorTypeResource\Pages;

use App\Filament\Resources\ActorTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListActorTypes extends ListRecords
{
    protected static string $resource = ActorTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
