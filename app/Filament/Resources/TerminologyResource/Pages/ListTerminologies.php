<?php

namespace App\Filament\Resources\TerminologyResource\Pages;

use App\Filament\Resources\TerminologyResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTerminologies extends ListRecords
{
    protected static string $resource = TerminologyResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
