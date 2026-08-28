<?php

namespace App\Filament\Resources\PublicContentResource\Pages;

use App\Filament\Resources\PublicContentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPublicContents extends ListRecords
{
    protected static string $resource = PublicContentResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
