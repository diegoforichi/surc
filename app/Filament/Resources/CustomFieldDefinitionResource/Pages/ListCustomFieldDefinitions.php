<?php

namespace App\Filament\Resources\CustomFieldDefinitionResource\Pages;

use App\Filament\Resources\CustomFieldDefinitionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCustomFieldDefinitions extends ListRecords
{
    protected static string $resource = CustomFieldDefinitionResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
