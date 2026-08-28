<?php

namespace App\Filament\Resources\HistoryEntryTypeResource\Pages;

use App\Filament\Resources\HistoryEntryTypeResource;
use Filament\Resources\Pages\ListRecords;

class ListHistoryEntryTypes extends ListRecords
{
    protected static string $resource = HistoryEntryTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\CreateAction::make(),
        ];
    }
}
